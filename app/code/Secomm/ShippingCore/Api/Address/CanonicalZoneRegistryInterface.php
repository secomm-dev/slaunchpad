<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * TASK-8MQHJX (Phase A, architecture v10 §35.2) — shared registry of canonical destination
 * zones. Zone definitions are merchant/composition data contributed via DI; carriers reference
 * zone codes only and never duplicate ward/province lists.
 *
 * Zero zones is a valid state (no zone restrictions applied to any carrier).
 */
interface CanonicalZoneRegistryInterface
{
    public function getByCode(string $zoneCode): ?CanonicalZoneInterface;

    /** @return CanonicalZoneInterface[] all registered zones (enabled + disabled), registration order */
    public function getAll(): array;

    /** @return CanonicalZoneInterface[] enabled zones only, registration order */
    public function getEnabled(): array;
}
