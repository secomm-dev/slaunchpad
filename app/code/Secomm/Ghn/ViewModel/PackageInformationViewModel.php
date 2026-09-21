<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Store\Model\ScopeInterface;
use Secomm\Ghn\Model\Carrier\Ghn;
use Secomm\Ghn\Model\Shipment\GhnPhysicalLimit;
use Secomm\ShippingCore\Model\Physical\ConfiguredDefaultPackageDimensions;

/**
 * TASK-9Q5ZAK r2 (DEC-TASK9Q5ZAK-001) — prefill + limits for the admin package-information
 * section. PREFILL ONLY: what the admin confirms on the shipment is what carriers receive;
 * these values are never injected server-side when physical data is missing (fail-closed).
 */
class PackageInformationViewModel implements ArgumentInterface
{
    public function __construct(
        private readonly \Magento\Framework\Registry $registry,
        private readonly \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        private readonly ConfiguredDefaultPackageDimensions $defaultDimensions,
        private readonly GhnPhysicalLimit $limit
    ) {
    }

    /**
     * The section renders only for shipments already travelling on the secomm_ghn carrier
     * (raw shipping-method prefix — Order::getShippingMethod(true) splits on the first
     * underscore and would misattribute).
     */
    public function shouldRender(): bool
    {
        $shipment = $this->registry->registry('current_shipment');
        if (!$shipment instanceof Shipment) {
            return false;
        }

        $shippingMethod = (string) $shipment->getOrder()->getShippingMethod();

        return str_starts_with($shippingMethod, Ghn::CARRIER_CODE . '_');
    }

    /**
     * Suggested total weight in the STORE weight unit (one decimal) — an editable prefill,
     * Σ shipment-item weight × qty (Magento never populates total_weight on the manual flow).
     */
    public function getSuggestedWeight(): float
    {
        $shipment = $this->registry->registry('current_shipment');
        if (!$shipment instanceof Shipment) {
            return 0.0;
        }

        $weight = 0.0;
        foreach ($shipment->getItems() as $item) {
            $itemWeight = (float) $item->getWeight();
            if ($itemWeight > 0.0) {
                $weight += $itemWeight * (float) $item->getQty();
            }
        }

        return round($weight, 3);
    }

    /**
     * Merchant default dimensions (cm) — prefill only, 0 = no prefill for that side.
     *
     * @return array{length: int, width: int, height: int}
     */
    public function getDefaultDimensions(): array
    {
        return $this->defaultDimensions->getDimensions();
    }

    /**
     * Store weight-unit label for the weight field ('kgs' | 'lbs').
     */
    public function getWeightUnitLabel(): string
    {
        return (string) $this->scopeConfig->getValue(
            'general/locale/weight_unit',
            ScopeInterface::SCOPE_STORE
        ) ?: 'kgs';
    }

    /**
     * GHN physical limits — displayed so the admin can validate before submit. ShippingCore
     * renders the capability; it never knows WHY GHN has these values.
     *
     * @return array{maxWeightG: int, maxLengthCm: int, maxWidthCm: int, maxHeightCm: int, multiple: bool}
     */
    public function getLimits(): array
    {
        return [
            'maxWeightG' => $this->limit->getMaxPackageWeightG(),
            'maxLengthCm' => $this->limit->getMaxLengthCm(),
            'maxWidthCm' => $this->limit->getMaxWidthCm(),
            'maxHeightCm' => $this->limit->getMaxHeightCm(),
            'multiple' => $this->limit->supportsMultiplePackages(),
        ];
    }
}
