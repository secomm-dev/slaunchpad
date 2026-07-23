<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api\Data;

interface RegionInterface
{
    /**
     * String constants for property names
     */
    public const REGION_ID = "region_id";
    public const COUNTRY_ID = "country_id";
    public const CODE = "code";
    public const DEFAULT_NAME = "default_name";
    public const IS_DEFAULT = "is_default";
    public const LOCALE = "locale";
    public const NAME = "name";

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
     * Getter for IsDefault.
     *
     * @return int|null
     */
    public function getIsDefault(): ?int;

    /**
     * Setter for IsDefault.
     *
     * @param int|null $isDefault
     *
     * @return void
     */
    public function setIsDefault(?int $isDefault): void;

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
