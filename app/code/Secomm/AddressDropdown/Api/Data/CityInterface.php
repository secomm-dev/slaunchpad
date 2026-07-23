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
}
