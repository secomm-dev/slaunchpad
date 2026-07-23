<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api\Data;

interface CityNameInterface
{
    /**
     * String constants for property names
     */
    public const CITY_ID = "city_id";
    public const LOCALE = "locale";
    public const NAME = "name";

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
     * Getter for Locale.
     *
     * @return string|null
     */
    public function getLocale(): ?string;

    /**
     * Setter for Locale.
     *
     * @param string|null $locale
     *
     * @return void
     */
    public function setLocale(?string $locale): void;

    /**
     * Getter for Name.
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Setter for Name.
     *
     * @param string|null $name
     *
     * @return void
     */
    public function setName(?string $name): void;
}
