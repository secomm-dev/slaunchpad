<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Inbound;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Secomm\FulfillmentCore\Api\Data\InboundUpdate;
use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Api\NormalizedFulfillmentStatus;
use Secomm\FulfillmentCore\Model\FulfillmentExport;
use Secomm\FulfillmentCore\Model\FulfillmentState;
use Secomm\FulfillmentCore\Model\FulfillmentStateFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory as ExportCollectionFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState as StateResource;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState\CollectionFactory as StateCollectionFactory;
use Throwable;

/**
 * Applies inbound OMS status updates to Magento-origin orders only.
 */
class InboundUpdateApplier
{
    public function __construct(
        private readonly MapperPool $mapperPool,
        private readonly ExportCollectionFactory $exportCollectionFactory,
        private readonly StateCollectionFactory $stateCollectionFactory,
        private readonly FulfillmentStateFactory $stateFactory,
        private readonly StateResource $stateResource,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Apply inbound update: map status, persist state, add order comment.
     * No-op when no Magento-origin export mapping exists for the external id.
     *
     * @param InboundUpdate $update Parsed adapter inbound DTO
     */
    public function apply(InboundUpdate $update): void
    {
        try {
            $export = $this->findMagentoOriginExport(
                $update->getServiceCode(),
                $update->getExternalOrderId()
            );
            if ($export === null) {
                return;
            }

            $state = $this->loadOrCreateState((int) $export->getMagentoOrderId());

            $eventId = $update->getEventId();
            if ($eventId !== null && $eventId !== '' && $state->getLastEventId() === $eventId) {
                return;
            }

            $normalized = $this->mapperPool->map($update->getServiceCode(), $update->getRawStatus());

            if ($state->getEntityId()
                && !$this->mayApplyStatus($state->getNormalizedStatus(), $normalized)
            ) {
                return;
            }

            $state->setMagentoOrderId((int) $export->getMagentoOrderId());
            $state->setServiceCode($update->getServiceCode());
            $state->setExternalOrderId($update->getExternalOrderId());
            $state->setNormalizedStatus($normalized);
            $state->setRawStatus($update->getRawStatus());
            if ($eventId !== null && $eventId !== '') {
                $state->setLastEventId($eventId);
            }
            if ($update->getCarrierName() !== null) {
                $state->setCarrierName($update->getCarrierName());
            }
            if ($update->getTrackingNumber() !== null) {
                $state->setTrackingNumber($update->getTrackingNumber());
            }
            if ($update->getTrackingUrl() !== null) {
                $state->setTrackingUrl($update->getTrackingUrl());
            }

            $this->stateResource->save($state);
            $this->addOrderComment($export, $update, $normalized);
        } catch (Throwable $e) {
            $this->logger->error(
                'FulfillmentCore InboundUpdateApplier failed',
                [
                    'service' => $update->getServiceCode(),
                    'external_order_id' => $update->getExternalOrderId(),
                    'exception' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Block terminal downgrade (e.g. DELIVERED → SHIPPED). Same/higher rank or
     * non-terminal side moves (SHIPPED → RETURNING) are allowed.
     */
    private function mayApplyStatus(string $current, string $incoming): bool
    {
        if (!NormalizedFulfillmentStatus::isTerminal($current)) {
            return true;
        }

        return NormalizedFulfillmentStatus::rank($incoming)
            >= NormalizedFulfillmentStatus::rank($current);
    }

    private function findMagentoOriginExport(string $serviceCode, string $externalOrderId): ?FulfillmentExport
    {
        $collection = $this->exportCollectionFactory->create();
        $collection->addFieldToFilter('service_code', $serviceCode);
        $collection->addFieldToFilter('external_order_id', $externalOrderId);
        $collection->addFieldToFilter('origin', ExportPushStatus::ORIGIN_MAGENTO);
        $collection->setPageSize(1);

        /** @var FulfillmentExport $item */
        $item = $collection->getFirstItem();
        return $item->getEntityId() ? $item : null;
    }

    private function loadOrCreateState(int $magentoOrderId): FulfillmentState
    {
        $collection = $this->stateCollectionFactory->create();
        $collection->addFieldToFilter('magento_order_id', $magentoOrderId);
        $collection->setPageSize(1);

        /** @var FulfillmentState $existing */
        $existing = $collection->getFirstItem();
        if ($existing->getEntityId()) {
            return $existing;
        }

        return $this->stateFactory->create();
    }

    private function addOrderComment(
        FulfillmentExport $export,
        InboundUpdate $update,
        string $normalized
    ): void {
        /** @var Order $order */
        $order = $this->orderRepository->get($export->getMagentoOrderId());
        $parts = [
            sprintf('Fulfillment [%s]: %s', $update->getServiceCode(), $normalized),
        ];
        if ($update->getRawStatus() !== '') {
            $parts[] = 'raw=' . $update->getRawStatus();
        }
        if ($update->getCarrierName()) {
            $parts[] = 'carrier=' . $update->getCarrierName();
        }
        if ($update->getTrackingNumber()) {
            $parts[] = 'tracking=' . $update->getTrackingNumber();
        }
        $order->addCommentToStatusHistory(implode(' | ', $parts), false, false);
        $this->orderRepository->save($order);
    }
}
