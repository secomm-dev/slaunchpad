<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api\Data;

interface CountryInterface
{
    /**
     * String constants for property names
     */
    public const COUNTRY_ID = "country_id";
    public const ISO2_CODE = "iso2_code";
    public const ISO3_CODE = "iso3_code";
    public const DEFAULT_NAME = "default_name";

    /**
     * Getter for CountryId.
     *
     * @return string|null
     */
    public function getCountryId(): ?string;

    /**
     * Setter for CountryId.
     *
     * @param string|null $countryId
     *
     * @return void
     */
    public function setCountryId(?string $countryId): void;

    /**
     * Getter for Iso2Code.
     *
     * @return string|null
     */
    public function getIso2Code(): ?string;

    /**
     * Setter for Iso2Code.
     *
     * @param string|null $iso2Code
     *
     * @return void
     */
    public function setIso2Code(?string $iso2Code): void;

    /**
     * Getter for Iso3Code.
     *
     * @return string|null
     */
    public function getIso3Code(): ?string;

    /**
     * Setter for Iso3Code.
     *
     * @param string|null $iso3Code
     *
     * @return void
     */
    public function setIso3Code(?string $iso3Code): void;

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
