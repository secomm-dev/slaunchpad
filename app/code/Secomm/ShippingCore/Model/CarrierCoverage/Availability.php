<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\CarrierCoverage;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 — ADMIN availability enum for the Shipping Coverage screen
 * (directive §5/§9): All Vietnam, Only Selected Zones, All Except Selected Zones.
 *
 * Values are string-aligned 1:1 with the runtime enum
 * `Secomm\ShippingCore\Api\Address\DestinationScope` (3 values since TASK-R8WR1R) — every
 * availability persisted from this admin surface is honored by CarrierEligibilityEvaluator
 * with no reader coercion. The runtime enum stays the semantic owner (evaluation order,
 * empty-zone and unknown-reference semantics); this class is only the admin-side vocabulary
 * plus the "which modes require picking zones" rule. Note: the admin surface deliberately
 * REQUIRES ≥1 zone for both zone modes (stricter than runtime's empty≡ALL equivalence for
 * ALL_EXCEPT) so a zone-requiring availability can never be saved in a state that silently
 * behaves like ALL.
 */
final class Availability
{
    public const ALL = 'ALL';
    public const SELECTED_ZONES = 'SELECTED_ZONES';
    public const ALL_EXCEPT_SELECTED_ZONES = 'ALL_EXCEPT_SELECTED_ZONES';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::ALL, self::SELECTED_ZONES, self::ALL_EXCEPT_SELECTED_ZONES];
    }

    public static function exists(string $availability): bool
    {
        return in_array($availability, self::all(), true);
    }

    /**
     * Zone picking is required for every mode other than plain "All Vietnam".
     */
    public static function requiresZones(string $availability): bool
    {
        return $availability !== self::ALL;
    }
}
