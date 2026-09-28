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
 * Type selection per the CURRENT GHN contract (docs 2026-09-18): the 20kg threshold applies
 * to the order's TOTAL weight (request root) — and any multi-parcel estimate is type 5
 * regardless of aggregate weight ("5: 20 kg or more, or multi-parcel orders").
 */
final class QuoteParcelEstimate
{
    public const HEAVY_WEIGHT_THRESHOLD_GRAMS = GhnShipmentConstraints::TYPE_2_MAX_WEIGHT_G;
    public const SERVICE_TYPE_LIGHT_PARCEL = 2;
    public const SERVICE_TYPE_HEAVY_GOODS = 5;

    /** @param list<EstimatedPackage> $packages */
    public function __construct(
        private readonly array $packages
    ) {
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
     * Deterministic weight-class + multi-parcel selection: under 20kg total AND exactly one
     * estimated package → type 2; otherwise type 5 (heavy or multi-parcel).
     */
    public function getServiceTypeId(): int
    {
        return $this->getPackageCount() === 1
            && $this->getTotalWeightGrams() < self::HEAVY_WEIGHT_THRESHOLD_GRAMS
            ? self::SERVICE_TYPE_LIGHT_PARCEL
            : self::SERVICE_TYPE_HEAVY_GOODS;
    }

    /**
     * TASK-WAWNDS — SANDBOX-verified hard-limit check (150cm per trusted dimension; see
     * {@see GhnPackageLimits} for provenance). Only PRESENT-and-violating trusted dimensions
     * reject: missing/untrusted data never misclassifies as a carrier rejection (brief §13).
     * Returns null when eligible, or [reasonCode, packageIndex, dimension, value, limit].
     *
     * @return array{0: string, 1: int, 2: string, 3: int, 4: int}|null
     */
    public function findHardLimitViolation(): ?array
    {
        foreach ($this->packages as $index => $package) {
            foreach (['length' => $package->getLengthCm(), 'width' => $package->getWidthCm(), 'height' => $package->getHeightCm()] as $dimension => $value) {
                if ($value !== null && $value > GhnPackageLimits::MAX_DIMENSION_CM) {
                    $reason = match ($dimension) {
                        'length' => GhnPackageLimits::REASON_PACKAGE_LENGTH_LIMIT_EXCEEDED,
                        'width' => GhnPackageLimits::REASON_PACKAGE_WIDTH_LIMIT_EXCEEDED,
                        default => GhnPackageLimits::REASON_PACKAGE_HEIGHT_LIMIT_EXCEEDED,
                    };

                    return [$reason, $index, $dimension, (int) $value, GhnPackageLimits::MAX_DIMENSION_CM];
                }
            }
        }

        return null;
    }

    /**
     * TASK-MQ2DRG — deterministic weight pre-validation (TL directive 2026-09-23; SUPERSEDES
     * the TASK-WAWNDS "no weight pre-rejection at RATE" stance — DEC-TASKMQ2DRG-001). Pure,
     * idempotent, no I/O; consumed by BOTH calculator entry points.
     *
     * Order: HARD_UNIT_OVER_WEIGHT first (directive A before E) — a single unit above the
     * per-package limit is a REAL carrier rejection and must never be masked as a
     * representational gap. AGGREGATE_UNREPRESENTABLE is reachable only when every unit is
     * individually valid but the total exceeds the representable request weight.
     *
     * Boundary semantics: limits are STRICTLY greater-than — exactly 50000g (single unit or
     * total) is representable; the 20000g type boundary (`<` in getServiceTypeId) is
     * untouched. Dimensions are not read here (no unit contract at RATE — see
     * {@see GhnPackageLimits}).
     */
    public function findWeightLimitViolation(): ?GhnWeightConstraintViolation
    {
        $limit = GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G;
        foreach ($this->packages as $index => $package) {
            if ($package->getWeightGrams() > $limit) {
                return new GhnWeightConstraintViolation(
                    GhnWeightConstraintViolation::KIND_HARD_UNIT_OVER_WEIGHT,
                    $index,
                    $package->getWeightGrams(),
                    $limit
                );
            }
        }
        $total = $this->getTotalWeightGrams();
        if ($total > $limit) {
            return new GhnWeightConstraintViolation(
                GhnWeightConstraintViolation::KIND_AGGREGATE_UNREPRESENTABLE,
                -1,
                $total,
                $limit
            );
        }

        return null;
    }
}
