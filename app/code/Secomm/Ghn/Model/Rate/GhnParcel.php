<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

/**
 * TASK-FMBBSD — parcel the GHN fee API is asked to price (current official contract,
 * developer.ghn.vn/en/docs/order/calculate-fee, fetched 2026-09-11):
 *   - weight in grams, must be non-zero (USER_ERR_COMMON otherwise);
 *   - length/width/height in cm are OPTIONAL at RATE time — omitted, never faked 1×1×1
 *     (Create Order requires them; RATE does not).
 *
 * GHN service_type_id is a WEIGHT CLASS, not a speed tier: 2 = total weight under 20 kg,
 * 5 = 20 kg or more / multi-parcel. There is no express/same-day service type in the current
 * contract — semantic service levels must NOT be derived from it.
 */
final class GhnParcel
{
    public const SERVICE_TYPE_LIGHT_PARCEL = 2;
    public const SERVICE_TYPE_HEAVY_GOODS = 5;
    public const HEAVY_WEIGHT_THRESHOLD_GRAMS = 20000;

    public function __construct(
        private readonly float $weightGrams,
        private readonly ?int $lengthCm = null,
        private readonly ?int $widthCm = null,
        private readonly ?int $heightCm = null
    ) {
    }

    public function getWeightGrams(): float
    {
        return $this->weightGrams;
    }

    public function getLengthCm(): ?int
    {
        return $this->lengthCm;
    }

    public function getWidthCm(): ?int
    {
        return $this->widthCm;
    }

    public function getHeightCm(): ?int
    {
        return $this->heightCm;
    }

    /**
     * GHN rejects a zero/missing weight before anything business-shaped can be quoted
     * (USER_ERR_COMMON) — rejected locally so no provider call is spent on it.
     */
    public function isDeliverable(): bool
    {
        return $this->weightGrams > 0.0;
    }

    /**
     * Deterministic weight-class selection per the current docs: under 20 kg → 2, otherwise 5.
     */
    public function getServiceTypeId(): int
    {
        return $this->weightGrams < self::HEAVY_WEIGHT_THRESHOLD_GRAMS
            ? self::SERVICE_TYPE_LIGHT_PARCEL
            : self::SERVICE_TYPE_HEAVY_GOODS;
    }
}
