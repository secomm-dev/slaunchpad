<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Shipment;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Sales\Model\Order\Shipment;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Config\Source\WeightUnit;

/**
 * Computes the shipment weight in grams for the GHTK fee API (DEC-022).
 *
 * - only shippable items (virtual skipped);
 * - configurable/bundle PARENT items are containers (weight carried by children) — skipped;
 * - child items use their own qty (Magento quote children already carry the effective qty,
 *   so no parent-qty multiply — avoids an N² double-count);
 * - missing/zero product weight falls back to the configured min/default;
 * - the project has NO weight-unit convention (Magento `weight` is unitless), so the unit
 *   is read from config and normalized to grams.
 */
class ShipmentWeightCalculator
{
    public function __construct(
        private GhtkConfig $config
    ) {
    }

    public function calculate(RateRequest $request, ?int $storeId = null): int
    {
        $unit = $this->config->getWeightUnit($storeId);
        $minWeight = $this->config->getMinWeight($storeId);

        $totalInUnit = 0.0;
        foreach ($request->getAllItems() as $item) {
            if ($item->getIsVirtual()) {
                continue;
            }
            $type = (string) $item->getProductType();
            $hasParent = $item->getParentItem() !== null;
            if (!$hasParent && ($type === 'configurable' || $type === 'bundle')) {
                continue; // container parent; children carry the weight
            }

            $product = $item->getProduct();
            $weight = $product !== null ? (float) $product->getWeight() : 0.0;
            if ($weight <= 0) {
                $weight = $minWeight;
            }
            $totalInUnit += $weight * (float) $item->getQty();
        }

        if ($totalInUnit <= 0) {
            $totalInUnit = $minWeight; // global floor (e.g. cart with only virtual items)
        }

        $grams = $unit === WeightUnit::GRAM ? $totalInUnit : $totalInUnit * 1000;

        // round() kills float-epsilon before the whole-gram ceiling (e.g. 1.4kg -> 1400.0000..2
        // would otherwise ceil to 1401). 6dp = sub-microgram precision (immaterial for shipping).
        return (int) ceil(round(max(1.0, $grams), 6));
    }

    /**
     * Shipment-path weight (SL-016): same contract as the rate path (DEC-022)
     * applied to a shipment's items — used when the merchant did not enter
     * package weights in the native package popup.
     */
    public function calculateForShipment(Shipment $shipment, ?int $storeId = null): int
    {
        $unit = $this->config->getWeightUnit($storeId);
        $minWeight = $this->config->getMinWeight($storeId);

        $totalInUnit = 0.0;
        foreach ($shipment->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            if ($orderItem !== null && $orderItem->getIsVirtual()) {
                continue;
            }
            $type = $orderItem !== null ? (string) $orderItem->getProductType() : '';
            $hasParent = $orderItem !== null && $orderItem->getParentItem() !== null;
            if (!$hasParent && ($type === 'configurable' || $type === 'bundle')) {
                continue; // container parent; children carry the weight
            }

            // getProduct() lives on the ORDER item (not the shipment item).
            $product = $orderItem !== null ? $orderItem->getProduct() : null;
            $weight = $product !== null ? (float) $product->getWeight() : 0.0;
            if ($weight <= 0) {
                $weight = $minWeight;
            }
            $totalInUnit += $weight * (float) $item->getQty();
        }

        if ($totalInUnit <= 0) {
            $totalInUnit = $minWeight;
        }

        $grams = $unit === WeightUnit::GRAM ? $totalInUnit : $totalInUnit * 1000;

        return (int) ceil(round(max(1.0, $grams), 6));
    }
}
