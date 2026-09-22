<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * TASK-Y3X6H5 (address-shipping architecture Revision v4 §5/§6) — address representation
 * CATEGORIES an operation may require. A category, never a provider value: ShippingCore knows
 * the operation needs a UNIT_ID or a TEXT_NAME; the concrete value (GHN district_id, GHN ward
 * name, …) is rendered carrier-owned at Stage 2.
 *
 * GEOPOINT (Type B geocode carriers) is deliberately absent — deferred P2, reopen only when a
 * Type B consumer proves the requirement (§8/§28 hard stop).
 */
final class AddressRepresentation
{
    public const UNIT_ID = 'UNIT_ID';
    public const TEXT_NAME = 'TEXT_NAME';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::UNIT_ID, self::TEXT_NAME];
    }

    public static function exists(string $representation): bool
    {
        return in_array($representation, self::all(), true);
    }
}
