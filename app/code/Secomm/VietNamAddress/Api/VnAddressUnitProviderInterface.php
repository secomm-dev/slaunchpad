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

    /**
     * TASK-G3K9V2 — all units of one hierarchy level (e.g. level 1 = provinces). Serves
     * level listings that have no parent anchor; independent of `parent_code` (seeded rows
     * may carry NULL parent codes on child levels — the region_code/level columns are the
     * reliable access path).
     *
     * @return VnAddressUnitInterface[] ordered by name (vi) ASC
     */
    public function getByLevel(string $schemeCode, int $level): array;

    /**
     * TASK-G3K9V2 — units of one level attributed to a region code (e.g. level-2 wards of
     * province `VN-15` via the ward row's `region_code`). Independent of `parent_code`
     * (seeded rows may carry NULL parent codes on child levels).
     *
     * @return VnAddressUnitInterface[] ordered by name (vi) ASC
     */
    public function getByRegion(string $schemeCode, string $regionCode, int $level): array;

    public function countByScheme(string $schemeCode): int;
}
