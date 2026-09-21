<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Block\Adminhtml\Shipment\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Shipment;
use Secomm\Ghn\Model\Shipment\GhnShipmentRepository;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\CollectionFactory as StateCollectionFactory;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

/**
 * TASK-PWHG0V (GHN-E3-B) — "Cancel GHN Shipment" / "Request GHN Return" actions on the Admin
 * Shipment View. LOCAL-state visibility only (brief §30: rendering never calls the provider):
 * provider row (SUBMITTED + order_code) and the latest E1 normalized state gate the buttons
 * with the conservative matrix from brief §16; the provider remains the final authority —
 * the services re-verify eligibility and GHN's answer wins.
 */
class Actions extends Template
{
    /** Hide Cancel when a terminal/late state makes it obviously invalid. */
    private const CANCEL_BLOCKED = [
        NormalizedTrackingStatus::DELIVERED,
        NormalizedTrackingStatus::RETURNED,
        NormalizedTrackingStatus::CANCELLED,
        NormalizedTrackingStatus::LOST,
        NormalizedTrackingStatus::DAMAGED,
    ];

    /** Hide Return only where the provider would trivially reject it. */
    private const RETURN_BLOCKED = [
        NormalizedTrackingStatus::CANCELLED,
        NormalizedTrackingStatus::RETURNED,
    ];

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly GhnShipmentRepository $shipmentRepository,
        private readonly StateCollectionFactory $stateCollectionFactory,
        array $data = [],
        ?\Magento\Framework\Json\Helper\Data $jsonHelper = null,
        ?\Magento\Directory\Helper\Data $directoryHelper = null
    ) {
        parent::__construct($context, $data, $jsonHelper, $directoryHelper);
    }

    public function getShipment(): ?Shipment
    {
        $shipment = $this->registry->registry('current_shipment');

        return $shipment instanceof Shipment ? $shipment : null;
    }

    public function canShowActions(): bool
    {
        return $this->getProviderRow() !== null && $this->isGhnShipment();
    }

    public function canCancel(): bool
    {
        $row = $this->getProviderRow();

        return $row !== null
            && ($row['provider_status'] ?? '') === GhnShipmentRepository::STATUS_SUBMITTED
            && !in_array($this->latestNormalizedStatus(), self::CANCEL_BLOCKED, true);
    }

    public function canReturn(): bool
    {
        $row = $this->getProviderRow();

        return $row !== null
            && ($row['ghn_order_code'] ?? '') !== ''
            && !in_array($this->latestNormalizedStatus(), self::RETURN_BLOCKED, true);
    }

    /**
     * Provider-verified cancel reason enum (GHN-CO001/CO002/CO003/GHN-CANCEL-OTHER) with
     * merchant-friendly labels (brief §14 — never code-only).
     *
     * @return array<string, string>
     */
    public function getCancelReasons(): array
    {
        return [
            'GHN-CO001' => (string) __('Pickup is overdue'),
            'GHN-CO002' => (string) __('Out of stock'),
            'GHN-CO003' => (string) __('Customer cancelled the order'),
            'GHN-CANCEL-OTHER' => (string) __('Other reason'),
        ];
    }

    public function getCancelUrl(): string
    {
        return $this->getUrl('secomm_ghn/shipment/cancel', ['shipment_id' => $this->getShipmentId()]);
    }

    public function getReturnUrl(): string
    {
        return $this->getUrl('secomm_ghn/shipment/returnShipment', ['shipment_id' => $this->getShipmentId()]);
    }

    public function getShipmentId(): int
    {
        return (int) ($this->getShipment()?->getEntityId() ?? 0);
    }

    private function isGhnShipment(): bool
    {
        $method = (string) ($this->getShipment()?->getOrder()?->getShippingMethod() ?? '');

        return str_starts_with($method, 'secomm_ghn_');
    }

    private function getProviderRow(): ?array
    {
        $shipmentId = $this->getShipmentId();
        if ($shipmentId <= 0) {
            return null;
        }

        return $this->shipmentRepository->findByShipmentId($shipmentId);
    }

    /**
     * Latest local E1 normalized state for this GHN order ('' when never tracked).
     */
    private function latestNormalizedStatus(): string
    {
        $orderCode = (string) ($this->getProviderRow()['ghn_order_code'] ?? '');
        if ($orderCode === '') {
            return '';
        }

        $state = $this->stateCollectionFactory->create()
            ->addFieldToFilter('carrier_code', 'secomm_ghn')
            ->addFieldToFilter('tracking_number', $orderCode)
            ->getFirstItem();

        return (string) ($state->getData('normalized_status') ?? '');
    }
}
