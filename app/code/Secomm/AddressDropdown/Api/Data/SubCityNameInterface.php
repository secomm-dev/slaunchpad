<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api\Data;

interface SubCityNameInterface
{
    /**
     * String constants for property names
     */
    public const SUB_CITY_ID = "sub_city_id";
    public const LOCALE = "locale";
    public const NAME = "name";

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
