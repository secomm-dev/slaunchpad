<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Api;

use Secomm\VietNamAddress\Api\Data\VnOperationalResolutionInterface;

/**
 * DEC-FEATYA2C0W-004 (D5) / TASK-Q4B98P — operational ↔ canonical identity bridge.
 *
 * Bridges the Magento runtime address tree (directory_country_region /
 * directory_region_city — which hold exactly ONE active VN scheme, DEC-FEATYA2C0W-002)
 * and the canonical Vietnam administrative identity (scheme_code + unit_code, kept in the
 * secomm_vietnam_address_* reference layer, DEC-FEATYA2C0W-003).
 *
 * Both directions are pure CODE lookups — the runtime rows carry the dataset codes
 * (directory_country_region.code = "VN-XX", directory_region_city.code = "VNA25-* / VNAP25-*")
 * since the import. Names are never join keys (SPEC-FEAT-YA2C0W-canonical-identity-bridge §3.4).
 *
 * Cross-scheme translation stays with VnAdminAddressResolverInterface; this contract only
 * maps runtime ids ↔ canonical identity of the ACTIVE scheme. Business-level misses return
 * an unresolved result with a reason — exceptions are reserved for hard failures.
 */
interface VnOperationalAddressResolverInterface
{
    /**
     * Runtime identity → canonical identity of the active scheme.
     *
     * @param int $regionId Magento region PK (0 = derive from $cityId)
     * @param int $cityId   Magento city/ward node PK (0 = region-only resolution)
     */
    public function resolveFromRuntime(int $regionId = 0, int $cityId = 0): VnOperationalResolutionInterface;

    /**
     * Canonical identity → runtime identity. Only resolves when $schemeCode is the ACTIVE
     * scheme (a non-active scheme has no runtime rows by the swap model — never fabricate ids).
     */
    public function resolveFromCanonical(string $schemeCode, string $unitCode): VnOperationalResolutionInterface;
}
