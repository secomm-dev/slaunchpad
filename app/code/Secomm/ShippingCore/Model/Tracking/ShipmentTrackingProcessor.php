<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Tracking;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track as TrackResource;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory as TrackCollectionFactory;
use Psr\Log\LoggerInterface;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingProcessorInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;
use Secomm\ShippingCore\Api\Tracking\TrackingUpdateInterface;
use Secomm\ShippingCore\Model\CarrierTrackingState;
use Secomm\ShippingCore\Model\CarrierTrackingStateFactory;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\CollectionFactory as StateCollectionFactory;

/**
 * THE tracking-status pipeline (SL-017 / DEC-SL017-001 §3) — webhook, Tracking
 * API and cron all feed updates here. Never throws for ordinary conditions
 * (unknown track, duplicate, out-of-order); never mutates Magento order state;
 * updates the carrier state row, the native Track description, adds a comment
 * on key transitions and dispatches domain events for downstream modules.
 *
 * Ordering rules: duplicate (same normalized + same carrier code, not newer)
 * → no-op; terminal statuses are sticky (never downgraded — out-of-order
 * webhook safety); an older explicit timestamp than the stored one → skip;
 * DELIVERY_FAILED → IN_TRANSIT is allowed (carriers re-attempt delivery).
 */
class ShipmentTrackingProcessor implements CarrierTrackingProcessorInterface
{
    public const EVENT_UPDATED = 'secomm_shipping_tracking_updated';

    public function __construct(
        private TrackCollectionFactory $trackCollectionFactory,
        private TrackResource $trackResource,
        private ShipmentRepositoryInterface $shipmentRepository,
        private StateCollectionFactory $stateCollectionFactory,
        private \Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState $stateResource,
        private CarrierTrackingStateFactory $stateFactory,
        private ManagerInterface $eventManager,
        private DateTime $dateTime,
        private LoggerInterface $logger
    ) {
    }

    public function process(TrackingUpdateInterface $update): bool
    {
        $track = $this->findTrack($update->getCarrierCode(), $update->getTrackingNumber());
        if ($track === null) {
            $this->logger->info(
                'Tracking update skipped: no Magento track matches.',
                ['carrier' => $update->getCarrierCode(), 'tracking_number' => $update->getTrackingNumber(), 'source' => $update->getSource()]
            );
            return false;
        }

        try {
            $applied = $this->apply($update, $track);
        } catch (\Throwable $e) {
            // Never let processing failures escape to a webhook caller.
            $this->logger->error(
                'Tracking update processing failed.',
                ['carrier' => $update->getCarrierCode(), 'tracking_number' => $update->getTrackingNumber(), 'exception' => $e->getMessage()]
            );
            return true;
        }

        return true;
    }

    private function apply(TrackingUpdateInterface $update, Track $track): bool
    {
        $state = $this->loadState($update->getCarrierCode(), $update->getTrackingNumber());
        $oldStatus = $state->getId() !== null ? (string) $state->getNormalizedStatus() : null;

        if (!$this->shouldApply($state, $update)) {
            $this->logger->info(
                'Tracking update skipped: duplicate or out-of-order.',
                [
                    'carrier' => $update->getCarrierCode(),
                    'tracking_number' => $update->getTrackingNumber(),
                    'stored_status' => $oldStatus,
                    'incoming_status' => $update->getNormalizedStatus(),
                    'source' => $update->getSource(),
                ]
            );
            return false;
        }

        $now = date('Y-m-d H:i:s', $this->dateTime->gmtTimestamp());
        $occurredAt = $update->getOccurredAt() !== null
            ? date('Y-m-d H:i:s', $update->getOccurredAt())
            : null;

        $state->setCarrierCode($update->getCarrierCode())
            ->setTrackingNumber($update->getTrackingNumber())
            ->setShipmentEntityId((int) $track->getParentId())
            ->setNormalizedStatus($update->getNormalizedStatus())
            ->setCarrierStatusCode($update->getCarrierStatusCode())
            ->setCarrierStatusMessage($this->truncate($update->getCarrierStatusMessage()))
            ->setCarrierStatusUpdatedAt($occurredAt ?? $now)
            ->setLastSyncedAt($now)
            ->setSource($update->getSource());
        $this->stateResource->save($state);

        // Native admin visibility: the track row carries the latest status.
        $track->setDescription(sprintf(
            '%s: %s%s (%s)',
            strtoupper($update->getCarrierCode()),
            $update->getNormalizedStatus(),
            $update->getCarrierStatusMessage() !== null ? ' — ' . $this->truncate($update->getCarrierStatusMessage(), 120) : '',
            date('d/m H:i', strtotime($state->getCarrierStatusUpdatedAt() ?? $now))
        ));
        $this->trackResource->save($track);

        if (in_array($update->getNormalizedStatus(), NormalizedTrackingStatus::commentable(), true)) {
            $this->addShipmentComment((int) $track->getParentId(), $update, (string) $state->getCarrierStatusUpdatedAt());
        }

        $this->eventManager->dispatch(self::EVENT_UPDATED, [
            'update' => $update,
            'old_status' => $oldStatus,
            'new_status' => $update->getNormalizedStatus(),
            'shipment_id' => (int) $track->getParentId(),
        ]);
        $this->dispatchSpecific($update, (int) $track->getParentId());

        $this->logger->info(
            'Tracking state updated.',
            [
                'carrier' => $update->getCarrierCode(),
                'tracking_number' => $update->getTrackingNumber(),
                'shipment_id' => (int) $track->getParentId(),
                'old_status' => $oldStatus,
                'new_status' => $update->getNormalizedStatus(),
                'carrier_status_code' => $update->getCarrierStatusCode(),
                'source' => $update->getSource(),
            ]
        );

        return true;
    }

    /**
     * Duplicate / out-of-order guard — the ordering rules of DEC-SL017-001 §3.
     */
    private function shouldApply(CarrierTrackingState $state, TrackingUpdateInterface $update): bool
    {
        if ($state->getId() === null) {
            return true;
        }

        $storedStatus = (string) $state->getNormalizedStatus();

        // Duplicate: same normalized status AND same raw code AND no distinct-occurrence
        // evidence. A provider may legitimately re-emit the SAME status later (e.g. a second
        // delivery_fail attempt) — the provider dedupe identity is order_code + event type +
        // occurrence time, so an event with a DIFFERENT occurrence time is a distinct event
        // (re-applied: row timestamp/message refresh + event re-emission), while an exact
        // re-send (same time, or no comparable timestamps) is absorbed (TASK-GKHXY1 r2).
        $sameOccurrence = $update->getOccurredAt() === null
            || $state->getCarrierStatusUpdatedAt() === null
            || $update->getOccurredAt() === strtotime((string) $state->getCarrierStatusUpdatedAt());

        if ($storedStatus === $update->getNormalizedStatus()
            && (string) $state->getCarrierStatusCode() === (string) $update->getCarrierStatusCode()
            && $sameOccurrence
        ) {
            return false;
        }

        // Sticky terminal: never downgrade out of DELIVERED/RETURNED/CANCELLED/LOST/DAMAGED.
        if (NormalizedTrackingStatus::isTerminal($storedStatus) && $storedStatus !== $update->getNormalizedStatus()) {
            return false;
        }

        // Explicit timestamps: an older observation never overwrites a newer one.
        $occurredAt = $update->getOccurredAt();
        if ($occurredAt !== null && $state->getCarrierStatusUpdatedAt() !== null) {
            $stored = strtotime((string) $state->getCarrierStatusUpdatedAt());
            if ($stored !== false && $occurredAt < $stored) {
                return false;
            }
        }

        return true;
    }

    private function findTrack(string $carrierCode, string $trackingNumber): ?Track
    {
        $collection = $this->trackCollectionFactory->create();
        $collection->addFieldToFilter('carrier_code', $carrierCode)
            ->addFieldToFilter('track_number', $trackingNumber)
            ->setPageSize(1)
            ->setCurPage(1);

        foreach ($collection as $track) {
            return $track;
        }

        return null;
    }

    private function loadState(string $carrierCode, string $trackingNumber): CarrierTrackingState
    {
        /** @var CarrierTrackingState $state */
        $state = $this->stateFactory->create();
        $collection = $this->stateCollectionFactory->create();
        $collection->addFieldToFilter('carrier_code', $carrierCode)
            ->addFieldToFilter('tracking_number', $trackingNumber)
            ->setPageSize(1)
            ->setCurPage(1);
        foreach ($collection as $existing) {
            return $existing;
        }

        return $state;
    }

    private function addShipmentComment(int $shipmentId, TrackingUpdateInterface $update, string $occurredAt): void
    {
        try {
            $shipment = $this->shipmentRepository->get($shipmentId);
        } catch (\Throwable $e) {
            $this->logger->warning('Tracking comment skipped: shipment unavailable.', ['shipment_id' => $shipmentId]);
            return;
        }

        try {
            $occurredTs = strtotime($occurredAt);
            $shipment->addComment(
                sprintf(
                    '%s shipment status: %s%s (%s).',
                    strtoupper($update->getCarrierCode()),
                    $update->getNormalizedStatus(),
                    $update->getCarrierStatusMessage() !== null ? ' — ' . $this->truncate($update->getCarrierStatusMessage(), 160) : '',
                    date('d/m/Y H:i', $occurredTs !== false ? $occurredTs : $this->dateTime->gmtTimestamp())
                ),
                false,
                false
            );
            $this->shipmentRepository->save($shipment);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Tracking comment could not be saved.',
                ['shipment_id' => $shipmentId, 'exception' => $e->getMessage()]
            );
        }
    }

    private function dispatchSpecific(TrackingUpdateInterface $update, int $shipmentId): void
    {
        $events = [
            NormalizedTrackingStatus::DELIVERED => 'secomm_shipment_carrier_delivered',
            NormalizedTrackingStatus::RETURNED => 'secomm_shipment_carrier_returned',
            NormalizedTrackingStatus::DELIVERY_FAILED => 'secomm_shipment_carrier_delivery_failed',
        ];
        $name = $events[$update->getNormalizedStatus()] ?? null;
        if ($name !== null) {
            $this->eventManager->dispatch($name, ['update' => $update, 'shipment_id' => $shipmentId]);
        }
    }

    private function truncate(?string $value, int $max = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
