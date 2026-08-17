<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Tracking;

/**
 * Carrier-agnostic normalized tracking statuses (SL-017 / DEC-SL017-001).
 * Carrier modules map THEIR status codes onto these via their own
 * CarrierStatusMapperInterface implementation — no carrier code ever lives here.
 *
 * Terminal statuses are sticky: the processor never downgrades out of them
 * (out-of-order webhook safety). DELIVERY_FAILED is intentionally NOT terminal
 * (carriers commonly re-attempt delivery → IN_TRANSIT again).
 */
final class NormalizedTrackingStatus
{
    public const CREATED = 'CREATED';
    public const PICKING = 'PICKING';
    public const PICKED_UP = 'PICKED_UP';
    public const IN_TRANSIT = 'IN_TRANSIT';
    public const OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    public const DELIVERED = 'DELIVERED';
    public const DELIVERY_FAILED = 'DELIVERY_FAILED';
    public const RETURNING = 'RETURNING';
    public const RETURNED = 'RETURNED';
    public const CANCELLED = 'CANCELLED';
    public const UNKNOWN = 'UNKNOWN';

    /**
     * @return string[] Statuses the tracking pipeline treats as final.
     */
    public static function terminal(): array
    {
        return [self::DELIVERED, self::RETURNED, self::CANCELLED];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::terminal(), true);
    }

    /**
     * @return string[] All statuses in declaration order.
     */
    public static function all(): array
    {
        return [
            self::CREATED,
            self::PICKING,
            self::PICKED_UP,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
            self::DELIVERY_FAILED,
            self::RETURNING,
            self::RETURNED,
            self::CANCELLED,
            self::UNKNOWN,
        ];
    }

    /**
     * Statuses worth a shipment comment when first entered (merchant-visible
     * transitions in the native Comments History).
     */
    public static function commentable(): array
    {
        return [self::DELIVERED, self::DELIVERY_FAILED, self::RETURNED];
    }
}
