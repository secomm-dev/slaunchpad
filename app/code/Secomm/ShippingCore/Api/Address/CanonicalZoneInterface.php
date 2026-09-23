<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * TASK-8MQHJX (Phase A, architecture v10 §35.2) — a canonical destination zone: static
 * geographic membership defined by canonical VN_ADMIN_2025 province/ward codes.
 *
 * Matching precedence (fail-closed):
 *  1. zone must be enabled
 *  2. if includeProvinceCodes is non-empty: destination province must match
 *  3. if includeWardCodes is non-empty: destination ward must be included
 *  4. if includeWardCodes is empty: no positive ward restriction
 *  5. if destination ward is in excludeWardCodes: zone does NOT match (exclude always wins)
 *
 * Canonical unit codes only — provider IDs, localized text, fuzzy matching, GIS/polygon,
 * origin-relative rules and distance calculations are all out of scope for P1.
 *
 * Zone CODES are merchant/composition data — ShippingCore owns the generic evaluation only.
 */
interface CanonicalZoneInterface
{
    /** Stable machine identity (e.g. "HCM_INNER") — never a display label. */
    public function getCode(): string;

    /** Human-readable label (e.g. "Nội thành TP.HCM"). */
    public function getLabel(): string;

    public function isEnabled(): bool;

    /** @return string[] canonical VN_ADMIN_2025 province codes; empty = no positive province restriction */
    public function getIncludeProvinceCodes(): array;

    /** @return string[] canonical VN_ADMIN_2025 ward codes; empty = no positive ward restriction */
    public function getIncludeWardCodes(): array;

    /** @return string[] canonical VN_ADMIN_2025 ward codes always excluded from matching */
    public function getExcludeWardCodes(): array;
}
