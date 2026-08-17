<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Fee;

/**
 * Parsed GHTK fee response (DEC-022). `delivery` false means GHTK will not deliver —
 * the carrier must not return a method in that case (AC-007).
 */
final class FeeResult
{
    public function __construct(
        public readonly float $fee,
        public readonly float $insuranceFee,
        public readonly float $extFees,
        public readonly bool $delivery,
        public readonly ?string $name
    ) {
    }
}
