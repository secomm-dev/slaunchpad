<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Api;

use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;

/**
 * DEC-FEATYA2C0W-003 / TASK-9394A9 — read access to the historical unit reference layer.
 * Works for ANY scheme ever imported (not just the active runtime one) — this is what
 * keeps old administrative knowledge resolvable after a runtime swap (Phase D mapping +
 * Phase E carrier resolution consume it).
 */
interface VnAddressUnitProviderInterface
{
    public function getUnit(string $schemeCode, string $code): ?VnAddressUnitInterface;

    /**
     * @return VnAddressUnitInterface[] ordered by name (vi) ASC
     */
    public function getChildren(string $schemeCode, string $parentCode): array;

    public function countByScheme(string $schemeCode): int;
}
