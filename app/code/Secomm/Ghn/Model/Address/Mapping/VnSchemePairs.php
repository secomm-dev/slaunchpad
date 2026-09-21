<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Mapping;

/**
 * SPEC-FEAT-FQWEQ3 §6 — the two canonical↔GHN scheme pairs this feature bridges.
 */
final class VnSchemePairs
{
    public const SECOMM_2025 = 'VN_ADMIN_2025';
    public const SECOMM_PRE_2025 = 'VN_ADMIN_PRE_2025';

    public const GHN_2025 = 'GHN_ADMIN_2025';
    public const GHN_PRE_2025 = 'GHN_ADMIN_PRE_2025';

    private function __construct()
    {
    }
}
