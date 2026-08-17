<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Address;

/**
 * Resolved GHTK pickup (merchant warehouse) address value object.
 * When hasPickAddressId() is true, only pick_address_id is sent to GHTK.
 */
final class PickupAddress
{
    public function __construct(
        public readonly ?string $pickAddressId,
        public readonly ?string $province,
        public readonly ?string $district,
        public readonly ?string $ward
    ) {
    }

    public function hasPickAddressId(): bool
    {
        return $this->pickAddressId !== null && $this->pickAddressId !== '';
    }
}
