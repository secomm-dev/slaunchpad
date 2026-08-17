<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Address;

/**
 * Resolved GHTK address value object.
 *
 * $isExact = true when sourced from a mapping hit; false when sourced from the
 * best-effort vi_VN fallback (GHTK may still reject it).
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
