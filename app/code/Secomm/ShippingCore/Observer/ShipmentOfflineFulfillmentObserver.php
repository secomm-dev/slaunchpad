<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order\Shipment;
use Psr\Log\LoggerInterface;
use Secomm\ShippingCore\Api\Shipment\FulfillmentMode;
use Secomm\ShippingCore\Model\Physical\PhysicalPackage;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalData;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalPersister;
use Secomm\ShippingCore\Model\Physical\StoreWeightConverter;
use Secomm\ShippingCore\Model\Shipment\FulfillmentMetadataPersister;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;
use Secomm\ShippingCore\Model\Shipment\OfflineEligibilitySession;
use Secomm\ShippingCore\Model\Shipment\OfflineRecordingState;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — records the offline fulfillment AFTER the native shipment
 * save commits (`sales_order_shipment_save_commit_after`, same event the carriers use for their
 * create trigger): fulfillment metadata marker + the confirmed package facts (RECORDED, never
 * validated — an offline save exists precisely because the facts violate carrier constraints)
 * + one structured operational log line (no PII).
 *
 * Guard: OfflineRecordingState (per process) — persisting the metadata re-saves the shipment,
 * which synchronously re-fires THIS event (and the save_before intent observer, which stands
 * down while recording); without intent the observer is a no-op, so ordinary ONLINE saves and
 * later re-saves (comment/track) never enter it.
 */
class ShipmentOfflineFulfillmentObserver implements ObserverInterface
{
    /** Admin form field carrying the confirmed physical packages (rows of weight/l/w/h). */
    private const POSTED_PACKAGES_PARAM = 'physical_packages';

    public function __construct(
        private readonly FulfillmentModeResolver $resolver,
        private readonly FulfillmentMetadataPersister $metadataPersister,
        private readonly ShipmentPhysicalPersister $physicalPersister,
        private readonly StoreWeightConverter $weightConverter,
        private readonly OfflineEligibilitySession $eligibilitySession,
        private readonly HttpRequest $request,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $shipment = $observer->getData('shipment');
        if (!$shipment instanceof Shipment || !$this->resolver->isOfflineIntent()) {
            return;
        }

        $shipmentId = (int) $shipment->getEntityId();
        if ($shipmentId <= 0 || OfflineRecordingState::isRecording($shipmentId)) {
            return;
        }
        OfflineRecordingState::begin($shipmentId);

        try {
            $this->record($shipment, $shipmentId);
        } finally {
            OfflineRecordingState::end($shipmentId);
        }
    }

    private function record(Shipment $shipment, int $shipmentId): void
    {
        $orderId = (int) $shipment->getOrderId();
        [$reasonCode, $reasonMessage] = $this->resolveReason($orderId);
        $carrierCode = (string) ($this->resolver->resolveCarrierCode(
            (string) ($shipment->getOrder()?->getShippingMethod() ?? '')
        ) ?? '');

        $this->metadataPersister->persist($shipment, [
            FulfillmentMetadataPersister::MODE => FulfillmentMode::OFFLINE,
            FulfillmentMetadataPersister::INTENDED_CARRIER => $carrierCode,
            FulfillmentMetadataPersister::REASON_CODE => $reasonCode,
            FulfillmentMetadataPersister::REASON_MESSAGE => $reasonMessage,
            FulfillmentMetadataPersister::NOTE => $this->sanitize($this->posted('offline_note'), 1000),
            FulfillmentMetadataPersister::CREATED_AT => $this->dateTime->gmtDate('Y-m-d H:i:s'),
        ]);

        $this->recordPackageFacts($shipment);

        // §25 — one structured operational log line; no customer data, no payloads.
        $this->logger->info('Offline shipment recorded.', [
            'order_id' => $orderId,
            'shipment_id' => $shipmentId,
            'fulfillment_mode' => FulfillmentMode::OFFLINE,
            'intended_carrier' => $carrierCode,
            'reason_code' => $reasonCode,
        ]);
    }

    /**
     * @return array{0: string, 1: string} reason code + human message (posted form first,
     *         then the carrier gate's session stash from the blocked online attempt)
     */
    private function resolveReason(int $orderId): array
    {
        $reasonCode = $this->sanitize($this->posted('offline_reason_code'), 64);
        if ($reasonCode !== '') {
            return [$reasonCode, $this->sanitize($this->posted('offline_reason_message'), 500)];
        }

        $stashed = $this->eligibilitySession->pull($orderId);

        return $stashed === null ? ['', ''] : [$stashed['reason_code'], $stashed['message']];
    }

    /**
     * Records the admin-confirmed packages as physical FACTS (grams/cm) — no carrier validation
     * here: the whole point of the offline path is that these facts may violate the carrier
     * limits. Unusable rows/conversion failures are contained (warn + skip) — the metadata
     * marker is the contract, the facts are best-effort evidence.
     */
    private function recordPackageFacts(Shipment $shipment): void
    {
        $posted = $this->request->getParam('shipment');
        $rows = is_array($posted) ? ($posted[self::POSTED_PACKAGES_PARAM] ?? null) : null;
        if (!is_array($rows) || $rows === []) {
            return;
        }

        try {
            $packages = [];
            foreach (array_values($rows) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $packages[] = new PhysicalPackage(
                    (int) round($this->weightConverter->toGrams((float) ($row['weight'] ?? 0), $this->storeId($shipment))),
                    (int) ($row['length'] ?? 0),
                    (int) ($row['width'] ?? 0),
                    (int) ($row['height'] ?? 0)
                );
            }

            if ($packages !== []) {
                $this->physicalPersister->persist($shipment, ShipmentPhysicalData::fromPackages($packages));
            }
        } catch (LocalizedException | \InvalidArgumentException $exception) {
            $this->logger->warning('Offline shipment: package facts not recorded.', [
                'shipment_id' => (int) $shipment->getEntityId(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    private function storeId(Shipment $shipment): ?int
    {
        return $shipment->getStoreId() !== null ? (int) $shipment->getStoreId() : null;
    }

    private function sanitize(string $value, int $maxLength): string
    {
        return mb_substr(trim(strip_tags($value)), 0, $maxLength);
    }

    private function posted(string $key): string
    {
        $posted = $this->request->getParam('shipment');

        return is_array($posted) && isset($posted[$key]) ? (string) $posted[$key] : '';
    }
}
