<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Block\Adminhtml\Shipment\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Shipment;
use Secomm\ShippingCore\Api\Shipment\FulfillmentMode;
use Secomm\ShippingCore\Model\Shipment\FulfillmentMetadataPersister;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — "Fulfillment" read-only section on the Admin Shipment View,
 * carrier-neutral. Renders ONLY when a fulfillment metadata marker exists (an OFFLINE record) —
 * legacy and ONLINE shipments render nothing, so the view is unchanged for every existing flow.
 */
class FulfillmentStatus extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly FulfillmentMetadataPersister $metadataPersister,
        array $data = [],
        ?\Magento\Framework\Json\Helper\Data $jsonHelper = null,
        ?\Magento\Directory\Helper\Data $directoryHelper = null
    ) {
        parent::__construct($context, $data, $jsonHelper, $directoryHelper);
    }

    public function canShow(): bool
    {
        return $this->getShipment() !== null && $this->getMetadata() !== null;
    }

    public function getShipment(): ?Shipment
    {
        $shipment = $this->registry->registry('current_shipment');

        return $shipment instanceof Shipment ? $shipment : null;
    }

    /**
     * @return array<string, string>|null the secomm_fulfillment marker (null = no OFFLINE record)
     */
    public function getMetadata(): ?array
    {
        $shipment = $this->getShipment();

        return $shipment === null ? null : $this->metadataPersister->read($shipment);
    }

    public function getIntendedCarrier(): string
    {
        return (string) ($this->getMetadata()[FulfillmentMetadataPersister::INTENDED_CARRIER] ?? '');
    }

    public function getReasonCode(): string
    {
        return (string) ($this->getMetadata()[FulfillmentMetadataPersister::REASON_CODE] ?? '');
    }

    public function getReasonMessage(): string
    {
        return (string) ($this->getMetadata()[FulfillmentMetadataPersister::REASON_MESSAGE] ?? '');
    }

    public function getNote(): string
    {
        return (string) ($this->getMetadata()[FulfillmentMetadataPersister::NOTE] ?? '');
    }

    public function isOffline(): bool
    {
        return FulfillmentMode::isOffline(
            isset($this->getMetadata()[FulfillmentMetadataPersister::MODE])
                ? (string) $this->getMetadata()[FulfillmentMetadataPersister::MODE]
                : null
        );
    }
}
