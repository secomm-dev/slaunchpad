<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Secomm\Ghn\Model\Rate\GhnParcel;
use Secomm\Ghn\Model\Shipment\GhnCreateValidationException;
use Secomm\ShippingCore\Api\Physical\PhysicalPackageInterface;
use Secomm\ShippingCore\Api\Physical\ShipmentPhysicalDataInterface;

/**
 * TASK-9Q5ZAK r3 (DEC-TASK9Q5ZAK-001) — THE GHN interpretation of ShippingCore physical facts.
 * The interpreter consumes ONLY the carrier-neutral facts (it never touches Magento products or
 * packaging heuristics — no cartonization, no dimension summing, no auto-splitting, no aggregate
 * virtual parcel) and decides:
 *
 *   service_type_id 2 (Hàng nhẹ)  → exactly ONE physical package under the 20,000 g class
 *                                   boundary → root weight/dims = that package, no items
 *   service_type_id 5 (Hàng nặng) → heavy shipment OR more than one physical package →
 *                                   items[] = ONE entry per physical package ("Package N",
 *                                   quantity 1, exact values — each GHN heavy item IS one
 *                                   physical package, never a catalog product). Root `weight` =
 *                                   the factual Σ (SANDBOX-MANDATORY: provider validates the
 *                                   root field as required; >50,000 g accepted with items[]);
 *                                   root length/width/height are OMITTED — sandbox-verified NOT
 *                                   required when items[] carries the dimensions (r3).
 *
 * Per-package provider limits are enforced here, BEFORE any HTTP call: any violating package →
 * INVALID_PARCEL (fail-closed — no splitting, no clamping, no downgrades). The limits apply PER
 * PACKAGE only — ShipmentPhysicalData.totalWeightG is never limited (a 30kg + 30kg two-package
 * shipment is valid even though the total exceeds 50,000 g).
 */
class GhnPhysicalParcelInterpreter
{
    /** GHN weight-class boundary in grams (<= this → type 2). */
    private const HEAVY_BOUNDARY_G = 20000;

    public function __construct(private readonly GhnPhysicalLimit $limit)
    {
    }

    /**
     * @throws GhnCreateValidationException INVALID_PARCEL when any package violates GHN hard limits
     */
    public function interpret(ShipmentPhysicalDataInterface $physical): GhnParcelPlan
    {
        $packages = $physical->getPackages();
        foreach ($packages as $index => $package) {
            $this->assertWithinLimits($index + 1, $package);
        }

        if (count($packages) === 1 && $packages[0]->getWeightG() < self::HEAVY_BOUNDARY_G) {
            return new GhnParcelPlan(
                GhnParcel::SERVICE_TYPE_LIGHT_PARCEL,
                $packages[0]->getWeightG(),
                $packages[0]->getLengthCm(),
                $packages[0]->getWidthCm(),
                $packages[0]->getHeightCm()
            );
        }

        return new GhnParcelPlan(
            GhnParcel::SERVICE_TYPE_HEAVY_GOODS,
            $physical->getTotalWeightG(),
            null,
            null,
            null,
            $this->buildItems($packages)
        );
    }

    private function assertWithinLimits(int $packageNumber, PhysicalPackageInterface $package): void
    {
        if ($package->getWeightG() > $this->limit->getMaxPackageWeightG()) {
            throw new GhnCreateValidationException(
                GhnCreateValidationException::REASON_INVALID_PARCEL,
                __(
                    'GHN create: package #%1 weighs %2 g — above the %3 g per-package limit. '
                    . 'Split it into multiple packages.',
                    (string) $packageNumber,
                    (string) $package->getWeightG(),
                    (string) $this->limit->getMaxPackageWeightG()
                )
            );
        }
        foreach (
            [
                'length' => $package->getLengthCm(),
                'width' => $package->getWidthCm(),
                'height' => $package->getHeightCm(),
            ] as $side => $cm
        ) {
            if ($cm > $this->limit->getMaxLengthCm()) {
                throw new GhnCreateValidationException(
                    GhnCreateValidationException::REASON_INVALID_PARCEL,
                    __(
                        'GHN create: package #%1 %2 is %3 cm — above the %4 cm per-side limit.',
                        (string) $packageNumber,
                        $side,
                        (string) $cm,
                        (string) $this->limit->getMaxLengthCm()
                    )
                );
            }
        }
    }

    /**
     * Each GHN heavy item represents one physical package — never a catalog product.
     *
     * @return array<int, array{name: string, quantity: int, weight: int, length: int, width: int, height: int}>
     */
    private function buildItems(array $packages): array
    {
        $items = [];
        foreach (array_values($packages) as $index => $package) {
            $items[] = [
                'name' => sprintf('Package %d', $index + 1),
                'quantity' => 1,
                'weight' => $package->getWeightG(),
                'length' => $package->getLengthCm(),
                'width' => $package->getWidthCm(),
                'height' => $package->getHeightCm(),
            ];
        }

        return $items;
    }
}
