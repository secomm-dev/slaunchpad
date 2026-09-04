<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api\Data;

/**
 * FEAT-2PZQKJ / TASK-J49PRZ — one canonical node of the recursive city hierarchy
 * (directory_region_city). `city_id` is the canonical identity (DEC-FEAT2PZQKJ-001 Decision 5).
 */
interface LocationNodeInterface
{
    public const CITY_ID = 'city_id';
    public const DEFAULT_NAME = 'default_name';
    public const NAME = 'name';
    public const DEPTH = 'depth';
    public const PARENT_CITY_ID = 'parent_city_id';
    public const REGION_ID = 'region_id';
    public const HAS_CHILDREN = 'has_children';

    /**
     * Canonical directory_region_city.city_id.
     *
     * @return int
     */
    public function getCityId(): int;

    /**
     * Locale-independent default name (identity key of legacy data).
     *
     * @return string
     */
    public function getDefaultName(): string;

    /**
     * Locale-resolved name (falls back to default_name when no translation exists).
     *
     * @return string
     */
    public function getName(): string;

    /**
     * City depth counted from region (1 = directly below region).
     *
     * @return int
     */
    public function getDepth(): int;

    /**
     * Parent city id — NULL for root nodes directly below a region.
     *
     * @return int|null
     */
    public function getParentCityId(): ?int;

    /**
     * @return int
     */
    public function getRegionId(): int;

    /**
     * Whether this node has any child cities (structural — NOT profile-filtered).
     *
     * @return bool
     */
    public function hasChildren(): bool;
}
