<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

namespace Secomm\VietNamAddress\Api;

use Secomm\VietNamAddress\Api\Data\VnOperationalNameResolutionInterface;

/**
 * DEC-FEATYA2C0W-004 (D5 name-entry) / TASK-7AJ3K8 — TRANSITIONAL name-based entry of the
 * operational ↔ canonical bridge, sibling to {@see VnOperationalAddressResolverInterface}
 * (that contract stays frozen — this is a separate interface on the same resolver family).
 *
 * Needed until persisted addresses carry canonical codes (DEC-FEATYA2C0W-003 §23 snapshot):
 * runtime quote/sales addresses carry only (region_id, ward-name). The bridge resolves the
 * name against the ACTIVE scheme's reference layer, region-scoped, and returns AMBIGUOUS
 * (with candidates, NEVER auto-picked) when several units share the name — replacing every
 * carrier-local first-match policy.
 *
 * Name matching is EXACT after trimming, against `name_vi` OR `name_en` of the units — the
 * storefront submits the locale name the address dropdown rendered (vi on the vi store,
 * en elsewhere), never a dataset code.
 */
interface VnOperationalNameResolverInterface
{
    /**
     * @param int $regionId Magento region PK of the containing province
     * @param string $wardName Ward (locality) name exactly as supplied by the address
     */
    public function resolveWardByName(int $regionId, string $wardName): VnOperationalNameResolutionInterface;
}
