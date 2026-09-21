<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Physical;

use Secomm\ShippingCore\Api\Physical\PhysicalPackageInterface;

/**
 * DEC-TASK9Q5ZAK-001 — immutable physical package fact (grams / centimetres, integers).
 * Fail-fast on non-positive values: an unmeasurable package must never reach a carrier adapter.
 */
final class PhysicalPackage implements PhysicalPackageInterface
{
    public function __construct(
        private readonly int $weightG,
        private readonly int $lengthCm,
        private readonly int $widthCm,
        private readonly int $heightCm
    ) {
        if ($this->weightG <= 0 || $this->lengthCm <= 0 || $this->widthCm <= 0 || $this->heightCm <= 0) {
            throw new \InvalidArgumentException(
                'Physical package requires positive weight (grams) and dimensions (cm); '
                . sprintf('got %d g, %dx%dx%d cm.', $this->weightG, $this->lengthCm, $this->widthCm, $this->heightCm)
            );
        }
    }

    public function getWeightG(): int
    {
        return $this->weightG;
    }

    public function getLengthCm(): int
    {
        return $this->lengthCm;
    }

    public function getWidthCm(): int
    {
        return $this->widthCm;
    }

    public function getHeightCm(): int
    {
        return $this->heightCm;
    }
}
