<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

use Secomm\Ghn\Model\GhnShipmentConstraints;

/**
 * TASK-WAWNDS — transient quote-time GHN rate estimation: the estimated packages plus the
 * derived GHN weight class. NOT `ShipmentPhysicalData`/`PhysicalPackage` (CREATE); never
 * persisted (brief §35).
 *
 * Type selection FROZEN by TASK-WNQCRW (DEC-TASKWNQCRW-001, 2026-10-01): RATE
 * service_type_id depends ONLY on the order's TOTAL weight — `total < 20kg → 2`,
 * `total >= 20kg → 5`. No package/item/SKU/qty count may influence it (the former
 * "multi-parcel → type 5" clause of the docs is an OR trigger for type 5, never a
 * requirement, and is deliberately not implemented).
 */
final class QuoteParcelEstimate
{
    public const HEAVY_WEIGHT_THRESHOLD_GRAMS = GhnShipmentConstraints::TYPE_2_MAX_WEIGHT_G;
    public const SERVICE_TYPE_LIGHT_PARCEL = 2;
    public const SERVICE_TYPE_HEAVY_GOODS = 5;

    /**
     * @param list<EstimatedPackage> $packages
     * @param int $maxLengthCm TASK-ZS2B41 (rev. 3-path) — shared per-dimension hard limits
     *        (cm), merchant-tunable via system config; defaults = {@see GhnPackageLimits::MAX_DIMENSION_CM}.
     * @param int $maxWeightG TASK-WNQCRW — per-package weight display gate (grams),
     *        merchant-tunable via system config; default = {@see GhnPackageLimits::MAX_WEIGHT_G}.
     */
    public function __construct(
        private readonly array $packages,
        private readonly int $maxLengthCm = GhnPackageLimits::MAX_DIMENSION_CM,
        private readonly int $maxWidthCm = GhnPackageLimits::MAX_DIMENSION_CM,
        private readonly int $maxHeightCm = GhnPackageLimits::MAX_DIMENSION_CM,
        private readonly int $maxWeightG = GhnPackageLimits::MAX_WEIGHT_G
    ) {
    }

    /** TASK-ZS2B41 — the configured RATE hard limits this estimate validates against (cm). */
    public function getMaxLengthCm(): int
    {
        return $this->maxLengthCm;
    }

    public function getMaxWidthCm(): int
    {
        return $this->maxWidthCm;
    }

    public function getMaxHeightCm(): int
    {
        return $this->maxHeightCm;
    }

    /** TASK-WNQCRW — the configured RATE per-package weight gate this estimate validates against (grams). */
    public function getMaxWeightG(): int
    {
        return $this->maxWeightG;
    }

    /** @return list<EstimatedPackage> */
    public function getPackages(): array
    {
        return $this->packages;
    }

    public function getPackageCount(): int
    {
        return count($this->packages);
    }

    public function getTotalWeightGrams(): float
    {
        $total = 0.0;
        foreach ($this->packages as $package) {
            $total += $package->getWeightGrams();
        }

        return $total;
    }

    public function isEmpty(): bool
    {
        return $this->packages === [];
    }

    /**
     * FROZEN (TASK-WNQCRW, DEC-TASKWNQCRW-001): RATE classification by TOTAL quote weight
     * only — `< 20000g → 2`, `>= 20000g → 5`. Package/item/SKU/qty counts are NEVER
     * consulted (the type-2 payload carries the root aggregate weight only).
     */
    public function getServiceTypeId(): int
    {
        return $this->getTotalWeightGrams() < self::HEAVY_WEIGHT_THRESHOLD_GRAMS
            ? self::SERVICE_TYPE_LIGHT_PARCEL
            : self::SERVICE_TYPE_HEAVY_GOODS;
    }

    /**
     * TASK-WAWNDS — hard-limit check per package: per-package WEIGHT (TASK-WNQCRW —
     * merchant-tunable, default = the CREATE contract 50000g) and per-dimension cm limits
     * (defaults = {@see GhnPackageLimits::MAX_DIMENSION_CM} — 200, shared Create contract;
     * TASK-ZS2B41 rev. 2026-10-01: limits are merchant-tunable per LENGTH/WIDTH/HEIGHT via
     * system config SHARED by RATE and CREATE). Weight is checked BEFORE the dimensions
     * (always present, deterministic first-violation). Only violating trusted data rejects:
     * missing/untrusted dimensions never misclassify as a carrier rejection (brief §13);
     * aggregate weight is NEVER capped — each unit is checked individually. Returns null
     * when eligible, or [reasonCode, packageIndex, dimension, value, limit].
     *
     * @return array{0: string, 1: int, 2: string, 3: int, 4: int}|null
     */
    public function findHardLimitViolation(): ?array
    {
        foreach ($this->packages as $index => $package) {
            // TASK-WNQCRW — per-package weight gate (grams), strictly `>`: a unit AT the
            // limit still quotes. One violating unit hides GHN regardless of the aggregate.
            if ($package->getWeightGrams() > $this->maxWeightG) {
                return [
                    GhnPackageLimits::REASON_PACKAGE_WEIGHT_LIMIT_EXCEEDED,
                    $index,
                    'weight',
                    (int) $package->getWeightGrams(),
                    $this->maxWeightG,
                ];
            }

            foreach (['length' => $package->getLengthCm(), 'width' => $package->getWidthCm(), 'height' => $package->getHeightCm()] as $dimension => $value) {
                $limit = match ($dimension) {
                    'length' => $this->maxLengthCm,
                    'width' => $this->maxWidthCm,
                    default => $this->maxHeightCm,
                };

                if ($value !== null && $value > $limit) {
                    $reason = match ($dimension) {
                        'length' => GhnPackageLimits::REASON_PACKAGE_LENGTH_LIMIT_EXCEEDED,
                        'width' => GhnPackageLimits::REASON_PACKAGE_WIDTH_LIMIT_EXCEEDED,
                        default => GhnPackageLimits::REASON_PACKAGE_HEIGHT_LIMIT_EXCEEDED,
                    };

                    return [$reason, $index, $dimension, (int) $value, $limit];
                }
            }
        }

        return null;
    }
}
