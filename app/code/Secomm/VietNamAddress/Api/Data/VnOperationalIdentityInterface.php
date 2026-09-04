<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Api\Data;

/**
 * DEC-FEATYA2C0W-004 (D5) / TASK-Q4B98P — immutable result of the operational ↔ canonical
 * bridge. Canonical side comes from the runtime `code` columns + the reference layer;
 * runtime ids are populated when known (always on forward resolution, only on reverse
 * resolution when the scheme is active — never fabricated, SPEC §3.2).
 */
interface VnOperationalIdentityInterface
{
    public function getSchemeCode(): string;

    /** Canonical unit code, e.g. "VN-01" (region) or "VNA25-3D6A6CF4D0". */
    public function getUnitCode(): string;

    /** Reference-layer level: 1 = region, 2 = first sub-level, 3 = second sub-level. */
    public function getLevel(): ?int;

    /** Canonical dataset region identity, e.g. "VN-01". */
    public function getRegionCode(): ?string;

    /** Canonical parent unit code, null for region-level units. */
    public function getParentUnitCode(): ?string;

    /** Runtime directory_country_region.region_id (null when not known). */
    public function getRegionId(): ?int;

    /** Runtime directory_region_city.city_id (null for region-level identities). */
    public function getCityId(): ?int;
}
