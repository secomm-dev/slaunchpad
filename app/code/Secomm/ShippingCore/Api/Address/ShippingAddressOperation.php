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
 * TASK-Y3X6H5 (address-shipping architecture Revision v4 §5) — shipping operations that have
 * an address capability, P1 scope.
 *
 * The SAME carrier may need a different canonical scheme and a different address representation
 * per operation (GHN: RATE = PRE-2025 + UNIT_ID, CREATE = 2025 + TEXT_NAME). The operation is
 * therefore part of the capability contract — never inferred from the carrier.
 *
 * CANCEL/TRACK have no address capability in P1 (they operate on provider order/tracking codes);
 * add a new constant only when a real carrier proves the need.
 */
final class ShippingAddressOperation
{
    public const RATE = 'RATE';
    public const CREATE = 'CREATE';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::RATE, self::CREATE];
    }

    public static function exists(string $operation): bool
    {
        return in_array($operation, self::all(), true);
    }

    /**
     * @throws LocalizedException unknown operation code
     */
    public static function assertKnown(string $operation): void
    {
        if (!self::exists($operation)) {
            throw new LocalizedException(
                __('Unknown shipping address operation "%1". Known operations: %2.', $operation, implode(', ', self::all()))
            );
        }
    }
}
