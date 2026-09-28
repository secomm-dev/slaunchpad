<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

/**
 * TASK-MQ2DRG — result of the deterministic weight pre-validation query
 * ({@see QuoteParcelEstimate::findWeightLimitViolation()}). Carries FACTS only — the
 * reason-string mapping (carrier-owned hard rejection vs shared
 * RATE_REQUEST_UNREPRESENTABLE) deliberately stays at the outcome-building site
 * (GhnRateCalculator) so ShippingCore constants never leak into the estimate layer.
 */
final class GhnWeightConstraintViolation
{
    /** A single sellable unit exceeds the per-package cap — real carrier rejection. */
    public const KIND_HARD_UNIT_OVER_WEIGHT = 'HARD_UNIT_OVER_WEIGHT';

    /** Every unit is individually valid but the aggregate is beyond the representable RATE request. */
    public const KIND_AGGREGATE_UNREPRESENTABLE = 'AGGREGATE_UNREPRESENTABLE';

    public function __construct(
        private readonly string $kind,
        private readonly int $packageIndex,
        private readonly float $weightGrams,
        private readonly int $limitGrams
    ) {
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    /** 0-based offending package index; -1 for AGGREGATE_UNREPRESENTABLE. */
    public function getPackageIndex(): int
    {
        return $this->packageIndex;
    }

    /** Offending unit weight, or the aggregate total for AGGREGATE_UNREPRESENTABLE. */
    public function getWeightGrams(): float
    {
        return $this->weightGrams;
    }

    public function getLimitGrams(): int
    {
        return $this->limitGrams;
    }
}
