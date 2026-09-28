<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

use Magento\Framework\Exception\LocalizedException;

/**
 * TASK-8MQHJX (architecture v10 §35.1) — Destination Scope per carrier.
 *
 * ALL: carrier eligible for every valid canonical destination (no zone restriction — but generic
 * prerequisites such as origin readiness still apply). The selected zone list is ignored.
 *
 * SELECTED_ZONES: carrier eligible only when the destination matches at least one allowed
 * canonical zone code (from the CanonicalZoneRegistry). Empty list → ineligible (fail closed).
 *
 * ALL_EXCEPT_SELECTED_ZONES (TASK-R8WR1R): carrier eligible for every destination that matches
 * NONE of the allowed canonical zone codes ("serve everywhere except"). Empty list → equivalent
 * to ALL (nothing is excluded). Unknown/disabled zone references do NOT exclude on their own —
 * only valid enabled matching zones exclude.
 *
 * Zone codes are merchant/composition data — ShippingCore owns the generic evaluation only and
 * NEVER hardcodes zone identities (HCM_INNER, etc. are examples, not domain enums).
 */
final class DestinationScope
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

    public static function exists(string $scope): bool
    {
        return in_array($scope, self::all(), true);
    }

    /**
     * @throws LocalizedException unknown destination scope
     */
    public static function assertKnown(string $scope): void
    {
        if (!self::exists($scope)) {
            throw new LocalizedException(
                __('Unknown destination scope "%1". Known scopes: %2.', $scope, implode(', ', self::all()))
            );
        }
    }
}
