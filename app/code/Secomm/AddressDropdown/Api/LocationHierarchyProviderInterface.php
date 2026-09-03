<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api;

use Secomm\AddressDropdown\Api\Data\LocationNodeInterface;

/**
 * FEAT-2PZQKJ / TASK-J49PRZ — ID-canonical traversal of the recursive city hierarchy,
 * filtered by Address Profile dataset membership (DEC-FEAT2PZQKJ-001 D5: subtree-claim +
 * inheritance). No table structure leaks through this contract.
 *
 * Membership semantics:
 * - A profile with NO membership rows at all is in all-nodes (BC) mode: every node qualifies.
 * - Otherwise a node qualifies when the NEAREST membership entry up its ancestor chain
 *   (city chain, then region claim) has include_subtree = 1, OR the node carries its own
 *   entry (either flag) — a claimed node is always a member of its claiming profile.
 */
interface LocationHierarchyProviderInterface
{
    /**
     * Root cities directly below a region (parent_city_id IS NULL), membership-filtered.
     *
     * @param int $regionId
     * @param string $profileCode
     * @return LocationNodeInterface[] depth 1, name-sorted
     */
    public function getRootLocations(int $regionId, string $profileCode): array;

    /**
     * Direct children of a city, membership-filtered.
     *
     * @param int $parentCityId
     * @param string $profileCode
     * @return LocationNodeInterface[] depth = parent depth + 1, name-sorted
     */
    public function getChildLocations(int $parentCityId, string $profileCode): array;

    /**
     * Whether the city has children qualifying for the profile (render stop condition).
     *
     * @param int $cityId
     * @param string $profileCode
     * @return bool
     */
    public function hasChildren(int $cityId, string $profileCode): bool;

    /**
     * Structural path root → node (NO profile filter — for edit-mode prefill).
     *
     * @param int $cityId
     * @return LocationNodeInterface[] empty when the id does not exist
     */
    public function getLocationPath(int $cityId): array;
}
