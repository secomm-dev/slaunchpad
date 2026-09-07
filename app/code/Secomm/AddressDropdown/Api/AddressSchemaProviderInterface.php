<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api;

use Secomm\AddressDropdown\Api\Data\SchemaLevelInterface;

/**
 * FEAT-2PZQKJ / TASK-NW66H9 — render order + content of the address schema for a profile.
 */
interface AddressSchemaProviderInterface
{
    /**
     * Schema levels for the profile, sorted ascending by sort_order (stable: declaration order
     * breaks ties). Labels/placeholders are translation keys — translate at render time.
     *
     * @param string $profileCode
     * @return SchemaLevelInterface[]
     * @throws \Secomm\AddressDropdown\Api\NoSuchProfileException when no module declares the profile
     *         (programmatic misuse — the resolver path falls back to native instead of throwing).
     */
    public function getSchema(string $profileCode): array;
}
