<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api\Data;

interface CityInterface
{
    /**
     * String constants for property names
     */
    public const CITY_ID = "city_id";
    public const REGION_ID = "region_id";
    public const DEFAULT_NAME = "default_name";
    /** TASK-9EX975 Slice B: recursive hierarchy — NULL parent = root directly below region (FEAT-2PZQKJ). */
    public const PARENT_CITY_ID = "parent_city_id";
    /** TASK-9EX975 Slice B: stable code, unique per (region_id, parent_city_id). */
    public const CODE = "code";
    /** Grid decoration (computed server-side, never persisted): parent default_name. */
    public const PARENT_NAME = "parent";
    /** Grid decoration (computed server-side, never persisted): 1-based depth below region ("⚠" suffix beyond profile). */
    public const LEVEL = "level";

    /**
     * Getter for CityId.
     *
     * @return int|null
     */
    public function getCityId(): ?int;

    /**
     * Setter for CityId.
     *
     * @param int|null $cityId
     *
     * @return void
     */
    public function setCityId(?int $cityId): void;

    /**
     * Getter for RegionId.
     *
     * @return int|null
     */
    public function getRegionId(): ?int;

    /**
     * Setter for RegionId.
     *
     * @param int|null $regionId
     *
     * @return void
     */
    public function setRegionId(?int $regionId): void;

    /**
     * Getter for DefaultName.
     *
     * @return string|null
     */
    public function getDefaultName(): ?string;

    /**
     * Setter for DefaultName.
     *
     * @param string|null $defaultName
     *
     * @return void
     */
    public function setDefaultName(?string $defaultName): void;

    /**
     * Getter for ParentCityId.
     *
     * @return int|null
     */
    public function getParentCityId(): ?int;

    /**
     * Setter for ParentCityId.
     *
     * @param int|null $parentCityId
     *
     * @return void
     */
    public function setParentCityId(?int $parentCityId): void;

    /**
     * Getter for Code.
     *
     * @return string|null
     */
    public function getCode(): ?string;

    /**
     * Setter for Code.
     *
     * @param string|null $code
     *
     * @return void
     */
    public function setCode(?string $code): void;
}
