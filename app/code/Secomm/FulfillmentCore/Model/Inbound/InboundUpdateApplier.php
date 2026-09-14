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
use Magento\Sales\Model\Order\Config as OrderConfig;
use Secomm\FulfillmentCore\Api\Data\InboundUpdate;
use Secomm\FulfillmentCore\Api\Data\StatusMapInterface;
use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Api\NormalizedFulfillmentStatus;
use Secomm\FulfillmentCore\Api\StatusMapResolverInterface;
use Secomm\FulfillmentCore\Model\FulfillmentExport;
use Secomm\FulfillmentCore\Model\FulfillmentState;
use Secomm\FulfillmentCore\Model\FulfillmentStateFactory;
use Secomm\FulfillmentCore\Model\Log\FulfillmentLogger;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory as ExportCollectionFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState as StateResource;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState\CollectionFactory as StateCollectionFactory;
use Throwable;

/**
 * Applies inbound OMS status updates to Magento-origin orders only.
 *
 * Admin Status Mapping table (like warehouse map):
 * - Active row for POS status → change Magento order status + comment
 * - No active row → comment only
 * Normalized timeline status always comes from adapter MapperPool (not the map table).
 */
class InboundUpdateApplier
{
    public function __construct(
        private readonly MapperPool $mapperPool,
        private readonly StatusMapResolverInterface $statusMapResolver,
        private readonly OrderConfig $orderConfig,
        private readonly OrderDocumentsApplier $orderDocumentsApplier,
        private readonly ExportCollectionFactory $exportCollectionFactory,
        private readonly StateCollectionFactory $stateCollectionFactory,
        private readonly FulfillmentStateFactory $stateFactory,
        private readonly StateResource $stateResource,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly FulfillmentLogger $fulfillmentLogger
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
                $this->fulfillmentLogger->info(
                    $update->getServiceCode(),
                    'FulfillmentCore inbound skipped: no Magento-origin mapping',
                    ['external_order_id' => $update->getExternalOrderId()]
                );
                return;
            }

            $this->applyToExport($export, $update);
        } catch (Throwable $e) {
            $this->fulfillmentLogger->error(
                $update->getServiceCode(),
                'FulfillmentCore InboundUpdateApplier failed',
                [
                    'external_order_id' => $update->getExternalOrderId(),
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Apply inbound status to a known Magento-origin export row.
     * Used by poll when the POS payload id differs from the stored external id.
     *
     * @param FulfillmentExport $export Mapping row already loaded for this order
     * @param InboundUpdate $update Parsed adapter inbound DTO
     * @return string Result code for CLI/cron: applied_status|applied_comment_only|skipped_same_event|skipped_downgrade|error
     */
    public function applyToExport(FulfillmentExport $export, InboundUpdate $update): string
    {
        try {
            $this->writeCurrentPosStatus($export, $update->getRawStatus());

            $state = $this->loadOrCreateState((int) $export->getMagentoOrderId());

            $eventId = $update->getEventId();
            if ($eventId !== null && $eventId !== '' && $state->getLastEventId() === $eventId) {
                $this->fulfillmentLogger->info(
                    $update->getServiceCode(),
                    'FulfillmentCore inbound skipped: same event_id (no comment)',
                    [
                        'order_id' => (int) $export->getMagentoOrderId(),
                        'raw_status' => $update->getRawStatus(),
                    ]
                );
                return 'skipped_same_event';
            }

            $statusMap = $this->resolveStatusMap($update);
            $normalized = $this->mapperPool->map($update->getServiceCode(), $update->getRawStatus());

            if ($state->getEntityId()
                && !$this->mayApplyStatus($state->getNormalizedStatus(), $normalized)
            ) {
                $this->fulfillmentLogger->info(
                    $update->getServiceCode(),
                    'FulfillmentCore inbound skipped: terminal downgrade blocked',
                    [
                        'order_id' => (int) $export->getMagentoOrderId(),
                        'current' => $state->getNormalizedStatus(),
                        'incoming' => $normalized,
                    ]
                );
                return 'skipped_downgrade';
            }

            // Persist fulfillment fields first WITHOUT last_event_id so a failed Magento
            // status/comment write can be retried on the next poll.
            $state->setMagentoOrderId((int) $export->getMagentoOrderId());
            $state->setServiceCode($update->getServiceCode());
            $state->setExternalOrderId($update->getExternalOrderId());
            $state->setNormalizedStatus($normalized);
            $state->setRawStatus($update->getRawStatus());
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

            $magentoStatus = $statusMap !== null ? $statusMap->getMagentoOrderStatus() : null;
            $this->addOrderComment($export, $update, $normalized, $magentoStatus);

            if ($eventId !== null && $eventId !== '') {
                $state->setLastEventId($eventId);
                $this->stateResource->save($state);
            }

            return $magentoStatus !== null && $magentoStatus !== ''
                ? 'applied_status'
                : 'applied_comment_only';
        } catch (Throwable $e) {
            $this->fulfillmentLogger->error(
                $update->getServiceCode(),
                'FulfillmentCore InboundUpdateApplier failed',
                [
                    'external_order_id' => $update->getExternalOrderId(),
                    'error' => $e->getMessage(),
                ]
            );
            return 'error:' . $e->getMessage();
        }
    }

    /**
     * Active DB status map for this service + raw POS code, or null when unmapped.
     */
    private function resolveStatusMap(InboundUpdate $update): ?StatusMapInterface
    {
        $raw = $this->normalizeRawCode($update->getRawStatus());
        if ($raw === '') {
            return null;
        }

        return $this->statusMapResolver->resolve($update->getServiceCode(), $raw);
    }

    /**
     * Persist the POS raw status on the export mapping row.
     *
     * @param FulfillmentExport $export Mapping row for this Magento order and service
     * @param string $rawStatus Vendor status code from the inbound payload
     */
    private function writeCurrentPosStatus(FulfillmentExport $export, string $rawStatus): void
    {
        if ($rawStatus === '' || $export->getCurrentStatus() === $rawStatus) {
            return;
        }
        if (!$export->getEntityId()) {
            return;
        }

        $export->setCurrentStatus($rawStatus);
        $export->getResource()->save($export);
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

    /**
     * When Status Mapping applies: create invoice/shipment if needed, then set Magento
     * order status + comment. Without map: comment only (no documents).
     *
     * @param FulfillmentExport $export Mapping row
     * @param InboundUpdate $update Inbound DTO
     * @param string $normalized NormalizedFulfillmentStatus constant
     * @param string|null $magentoStatus Magento sales_order.status, or null for comment-only
     */
    private function addOrderComment(
        FulfillmentExport $export,
        InboundUpdate $update,
        string $normalized,
        ?string $magentoStatus
    ): void {
        /** @var Order $order */
        $order = $this->orderRepository->get($export->getMagentoOrderId());
        $comment = $this->buildStorefrontComment($order, $update, $normalized);

        if ($magentoStatus !== null && $magentoStatus !== '') {
            $state = $this->resolveStateForStatus($magentoStatus);

            // Invoice + shipment before forcing status (needed for Magento complete, etc.).
            $order = $this->orderDocumentsApplier->apply($order, $magentoStatus, $state, $update);

            if ($state !== null) {
                $order->setState($state);
            }
            $order->addCommentToStatusHistory($comment, $magentoStatus, true);
            $this->fulfillmentLogger->info(
                $update->getServiceCode(),
                'FulfillmentCore order status + comment saved',
                [
                    'order_id' => (int) $order->getEntityId(),
                    'normalized' => $normalized,
                    'magento_status' => $magentoStatus,
                    'magento_state' => $state,
                    'can_invoice_after' => $order->canInvoice() ? 1 : 0,
                    'can_ship_after' => $order->canShip() ? 1 : 0,
                ]
            );
        } else {
            $order->addCommentToStatusHistory($comment, false, true);
            $this->fulfillmentLogger->info(
                $update->getServiceCode(),
                'FulfillmentCore order comment saved (no status map)',
                [
                    'order_id' => (int) $order->getEntityId(),
                    'normalized' => $normalized,
                    'raw_status' => $update->getRawStatus(),
                ]
            );
        }

        $this->orderRepository->save($order);
    }

    /**
     * Find Magento order state that owns the given status code.
     * Defensive: some config entries may be Phrase objects, not arrays.
     *
     * @param string $status Magento sales_order.status
     */
    private function resolveStateForStatus(string $status): ?string
    {
        foreach ($this->orderConfig->getStates() as $state => $info) {
            if (!is_array($info)) {
                continue;
            }
            $statuses = $info['statuses'] ?? null;
            if (!is_array($statuses) || !array_key_exists($status, $statuses)) {
                continue;
            }

            return (string) $state;
        }

        return null;
    }

    /**
     * Normalize vendor codes for stable DB lookup (trim; numeric → int string).
     *
     * @param string $rawStatus Raw vendor status from payload
     */
    private function normalizeRawCode(string $rawStatus): string
    {
        $raw = trim($rawStatus);
        if ($raw === '') {
            return '';
        }
        if (is_numeric($raw)) {
            return (string) (int) $raw;
        }

        return $raw;
    }

    /**
     * Order-history comment in the order storefront locale (end-user language).
     * Does not use store emulation so cron can instantiate this class without extra DI.
     *
     * @param Order $order Magento order whose store view selects vi_VN or en_US
     * @param InboundUpdate $update Inbound carrier and tracking values
     * @param string $normalized NormalizedFulfillmentStatus constant
     */
    private function buildStorefrontComment(Order $order, InboundUpdate $update, string $normalized): string
    {
        $vietnamese = $this->isVietnameseStore($order);
        $posLabel = $this->posLabel($update->getServiceCode());
        $parts = [
            $vietnamese
                ? $posLabel . ': Trạng thái giao hàng hiện tại: ' . $this->statusLabel($normalized, true) . '.'
                : $posLabel . ': Fulfillment status is now ' . $this->statusLabel($normalized, false) . '.',
        ];
        if ($update->getCarrierName()) {
            $parts[] = $vietnamese
                ? 'Đơn vị vận chuyển: ' . $update->getCarrierName()
                : 'Carrier: ' . $update->getCarrierName();
        }
        if ($update->getTrackingNumber()) {
            $parts[] = $vietnamese
                ? 'Mã vận đơn: ' . $update->getTrackingNumber()
                : 'Tracking: ' . $update->getTrackingNumber();
        }

        return implode(' ', $parts);
    }

    /**
     * Display name used as the comment prefix. service_code pancake → Pancake.
     *
     * @param string $serviceCode Adapter service_code
     */
    private function posLabel(string $serviceCode): string
    {
        $serviceCode = strtolower(trim($serviceCode));
        if ($serviceCode === '') {
            return 'POS';
        }

        return ucfirst($serviceCode);
    }

    /**
     * Whether the order storefront locale is Vietnamese.
     *
     * @param Order $order Magento sales order
     */
    private function isVietnameseStore(Order $order): bool
    {
        $store = $order->getStore();
        $locale = $store ? (string) $store->getConfig('general/locale/code') : '';

        return str_starts_with($locale, 'vi');
    }

    /**
     * Customer-facing label for a normalized fulfillment status.
     *
     * @param string $normalized NormalizedFulfillmentStatus constant
     * @param bool $vietnamese True when the order store locale is vi_*
     */
    private function statusLabel(string $normalized, bool $vietnamese): string
    {
        $labels = $vietnamese
            ? [
                NormalizedFulfillmentStatus::CONFIRMED => 'Đã xác nhận',
                NormalizedFulfillmentStatus::PACKING => 'Đang đóng gói',
                NormalizedFulfillmentStatus::WAITING_PICKUP => 'Chờ lấy hàng',
                NormalizedFulfillmentStatus::SHIPPED => 'Đang giao',
                NormalizedFulfillmentStatus::DELIVERED => 'Đã giao',
                NormalizedFulfillmentStatus::RETURNING => 'Đang hoàn',
                NormalizedFulfillmentStatus::RETURNED => 'Đã hoàn',
                NormalizedFulfillmentStatus::CANCELLED => 'Đã hủy',
            ]
            : [
                NormalizedFulfillmentStatus::CONFIRMED => 'Confirmed',
                NormalizedFulfillmentStatus::PACKING => 'Packing',
                NormalizedFulfillmentStatus::WAITING_PICKUP => 'Waiting for pickup',
                NormalizedFulfillmentStatus::SHIPPED => 'Shipped',
                NormalizedFulfillmentStatus::DELIVERED => 'Delivered',
                NormalizedFulfillmentStatus::RETURNING => 'Returning',
                NormalizedFulfillmentStatus::RETURNED => 'Returned',
                NormalizedFulfillmentStatus::CANCELLED => 'Cancelled',
            ];

        return $labels[$normalized] ?? $normalized;
    }
}
