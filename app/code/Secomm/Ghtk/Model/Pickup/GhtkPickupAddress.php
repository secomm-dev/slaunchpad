<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Pickup;

/**
 * TASK-3HPB76 — one merchant pickup address from `list_pick_add`
 * (carrier-owned lean representation: exactly the fields needed for
 * configured-id validation + admin diagnostics — NOT an administrative
 * province/district/ward model, NOT a persistence entity).
 */
final class GhtkPickupAddress
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?string $telephone = null,
        public readonly ?string $address = null
    ) {
    }
}
