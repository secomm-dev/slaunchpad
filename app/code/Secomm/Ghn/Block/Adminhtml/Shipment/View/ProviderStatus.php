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
use Secomm\Ghn\Model\Shipment\GhnCreateReasonLabel;
use Secomm\Ghn\Model\Shipment\GhnShipmentRepository;
use Secomm\ShippingCore\Api\Shipment\FulfillmentMode;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalPersister;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;

/**
 * TASK-W5BW4F — "GHN Shipment" read-only status section on the Admin Shipment View: the
 * provider row (secomm_ghn_shipment — status, reason, order code, fee) and the persisted
 * physical packages (the secomm_physical snapshot, read via the ShippingCore persister so the
 * marker key stays owned in one place). LOCAL state only — rendering never calls the provider.
 *
 * This is the surface the create-failure contract points at: a FAILED/UNKNOWN row shows the
 * human reason and the retry CLI hint (no admin retry action in this slice).
 *
 * TASK-S52DGA (DEC-TASKS52DGA-001): an OFFLINE shipment (generic fulfillment metadata — no
 * provider row can exist) renders the offline banner instead of the "not attempted" text, so
 * offline never looks like a live GHN integration.
 */
class ProviderStatus extends Template
{
    /** Row statuses that can be reconciled by the retry CLI. */
    private const RETRYABLE = [GhnShipmentRepository::STATUS_FAILED, GhnShipmentRepository::STATUS_UNKNOWN];

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly GhnShipmentRepository $shipmentRepository,
        private readonly ShipmentPhysicalPersister $physicalPersister,
        private readonly GhnCreateReasonLabel $reasonLabel,
        private readonly FulfillmentModeResolver $fulfillmentModeResolver,
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

    public function canShow(): bool
    {
        return $this->isGhnShipment()
            && ($this->getProviderRow() !== null || $this->getPackages() !== []);
    }

    /**
     * TASK-S52DGA — the shipment carries a persisted OFFLINE fulfillment record (no provider
     * row can exist for it).
     */
    public function isOffline(): bool
    {
        $shipment = $this->getShipment();

        return $shipment !== null
            && $this->fulfillmentModeResolver->forShipment($shipment) === FulfillmentMode::OFFLINE;
    }

    /**
     * @return array<string, mixed>|null the secomm_ghn_shipment anchor row (null = never attempted)
     */
    public function getProviderRow(): ?array
    {
        $shipmentId = $this->getShipmentId();
        if ($shipmentId <= 0) {
            return null;
        }

        return $this->shipmentRepository->findByShipmentId($shipmentId);
    }

    public function getStatus(): string
    {
        return (string) ($this->getProviderRow()['provider_status'] ?? '');
    }

    /**
     * @return array<int, array<int, int>> the confirmed physical packages [[weightG, lengthCm, widthCm, heightCm], ...]
     */
    public function getPackages(): array
    {
        $shipment = $this->getShipment();
        if ($shipment === null) {
            return [];
        }

        $physical = $this->physicalPersister->read($shipment);

        $packages = [];
        foreach ($physical?->getPackages() ?? [] as $package) {
            $packages[] = [
                $package->getWeightG(),
                $package->getLengthCm(),
                $package->getWidthCm(),
                $package->getHeightCm(),
            ];
        }

        return $packages;
    }

    public function getReasonText(): string
    {
        $row = $this->getProviderRow();
        if ($row === null) {
            return '';
        }

        return $this->reasonLabel->label((string) ($row['provider_reason_code'] ?? ''));
    }

    public function getGhnOrderCode(): string
    {
        return (string) ($this->getProviderRow()['ghn_order_code'] ?? '');
    }

    public function getTotalFee(): ?float
    {
        $fee = $this->getProviderRow()['actual_fee'] ?? null;

        return is_numeric($fee) ? (float) $fee : null;
    }

    public function needsRetry(): bool
    {
        return in_array($this->getStatus(), self::RETRYABLE, true);
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
}
