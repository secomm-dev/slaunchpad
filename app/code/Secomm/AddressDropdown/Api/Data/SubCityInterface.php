<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api\Data;

interface SubCityInterface
{
    /**
     * String constants for property names
     */
    public const SUB_CITY_ID = "sub_city_id";
    public const CITY_ID = "city_id";
    public const DEFAULT_NAME = "default_name";

    /**
     * Getter for SubCityId.
     *
     * @return int|null
     */
    public function getSubCityId(): ?int;

    /**
     * Setter for SubCityId.
     *
     * @param int|null $subCityId
     *
     * @return void
     */
    public function setSubCityId(?int $subCityId): void;

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
