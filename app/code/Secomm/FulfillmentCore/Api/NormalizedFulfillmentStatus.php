<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api;

/**
 * Vendor-agnostic fulfillment milestones shown to customers and stored on state rows.
 */
final class NormalizedFulfillmentStatus
{
    public const CONFIRMED = 'confirmed';
    public const PACKING = 'packing';
    public const WAITING_PICKUP = 'waiting_pickup';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const RETURNING = 'returning';
    public const RETURNED = 'returned';
    public const CANCELLED = 'cancelled';
    public const UNKNOWN = 'unknown';

    /**
     * Ordered timeline points for storefront stepper (excludes UNKNOWN).
     *
     * @return string[]
     */
    public static function customerVisible(): array
    {
        return [
            self::CONFIRMED,
            self::PACKING,
            self::WAITING_PICKUP,
            self::SHIPPED,
            self::DELIVERED,
            self::RETURNING,
            self::RETURNED,
            self::CANCELLED,
        ];
    }

    /**
     * Whether status is terminal (no further forward progression expected).
     */
    public static function isTerminal(string $status): bool
    {
        return in_array(
            $status,
            [self::DELIVERED, self::RETURNED, self::CANCELLED],
            true
        );
    }

    /**
     * Monotonic rank for downgrade protection. Higher = further along / terminal.
     * UNKNOWN ranks lowest so it never overwrites a known milestone.
     */
    public static function rank(string $status): int
    {
        return match ($status) {
            self::UNKNOWN => 0,
            self::CONFIRMED => 10,
            self::PACKING => 20,
            self::WAITING_PICKUP => 30,
            self::SHIPPED => 40,
            self::DELIVERED => 50,
            self::RETURNING => 45,
            self::RETURNED => 55,
            self::CANCELLED => 60,
            default => 0,
        };
    }

    private function __construct()
    {
    }
}
