<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Address;

/**
 * GHTK-bound textual address value object (TEXT_NATIVE mode — DEC-TASK7AJ3K8-002).
 *
 * province/ward are the canonical Vietnamese names (name_vi) of the carrier-required
 * scheme by default; district is nullable (only from an override row — the VN 2-level
 * canonical model has no district level).
 *
 * $isExact = true when an explicit GHTK address override was applied (merchant-confirmed
 * exception text); false when the text is the canonical native representation.
 */
final class GhtkAddress
{
    public function __construct(
        public readonly string $province,
        public readonly ?string $district,
        public readonly string $ward,
        public readonly bool $isExact
    ) {
    }
}
