<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

/**
 * TASK-WAWNDS — one ESTIMATED rate-time package (PRODUCT_UNIT_AS_PACKAGE: 1 sellable unit =
 * 1 estimated package), serialized to a GHN Fee `items[]` row with `quantity: 1`.
 *
 * Strictly transient quote-time estimation — NEVER persisted as shipment physical truth
 * (CREATE keeps its own confirmed `ShipmentPhysicalData`/`secomm_physical` layer; RATE
 * estimate and CREATE physical data may legitimately diverge, brief §36).
 *
 * Dimensions: OPTIONAL trusted values only (cm — the Secomm_Base shipping-dimension
 * contract is the trusted source since TASK-RT50KH; the estimator fills them from the
 * product attributes when complete-and-valid, else null). When
 * present they are (a) used for the shared per-dimension hard-limit check (default 200cm)
 * and (b) STILL
 * omitted from the fee payload (unproven dimensions distort pricing — root dims changed the
 * type-2 sandbox fee materially).
 */
final class EstimatedPackage
{
    public function __construct(
        private readonly int $sourceItemId,
        private readonly string $sourceSku,
        private readonly float $weightGrams,
        private readonly string $source,
        private readonly ?int $lengthCm = null,
        private readonly ?int $widthCm = null,
        private readonly ?int $heightCm = null
    ) {
    }

    public function getSourceItemId(): int
    {
        return $this->sourceItemId;
    }

    public function getSourceSku(): string
    {
        return $this->sourceSku;
    }

    public function getWeightGrams(): float
    {
        return $this->weightGrams;
    }

    public function getSource(): string
    {
        return $this->source;
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
}
