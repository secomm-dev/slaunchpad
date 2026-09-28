<?php
/**
 * Copyright © Secomm All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\Base\Api\Data;

/**
 * TASK-RT50KH — authoritative packed shipping dimensions for ONE sellable unit, in
 * centimeters (P1 product shipping-dimension contract, DEC-TASKRT50KH-001).
 *
 * Complete-only by construction: the reader returns this VO only when all three values are
 * present, numeric and positive — there is no partial/zero/null state to interpret. Anything
 * less authoritative is reported as "missing" (null from the reader), never as a rejection.
 */
final class ShippingDimensions
{
    public function __construct(
        private readonly int $lengthCm,
        private readonly int $widthCm,
        private readonly int $heightCm
    ) {
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
