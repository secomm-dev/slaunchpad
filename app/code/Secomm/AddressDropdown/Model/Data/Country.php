<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\AddressDropdown\Model\Data;

use Magento\Framework\DataObject;
use Secomm\AddressDropdown\Api\Data\CountryInterface;

class Country extends DataObject implements CountryInterface
{
    /**
     * Getter for CountryId.
     *
     * @return string|null
     */
    public function getCountryId(): ?string
    {
        return $this->getData(self::COUNTRY_ID);
    }

    /**
     * Setter for CountryId.
     *
     * @param string|null $countryId
     *
     * @return void
     */
    public function setCountryId(?string $countryId): void
    {
        $this->setData(self::COUNTRY_ID, $countryId);
    }

    /**
     * Getter for Iso2Code.
     *
     * @return string|null
     */
    public function getIso2Code(): ?string
    {
        return $this->getData(self::ISO2_CODE);
    }

    /**
     * Setter for Iso2Code.
     *
     * @param string|null $iso2Code
     *
     * @return void
     */
    public function setIso2Code(?string $iso2Code): void
    {
        $this->setData(self::ISO2_CODE, $iso2Code);
    }

    /**
     * Getter for Iso3Code.
     *
     * @return string|null
     */
    public function getIso3Code(): ?string
    {
        return $this->getData(self::ISO3_CODE);
    }

    /**
     * Setter for Iso3Code.
     *
     * @param string|null $iso3Code
     *
     * @return void
     */
    public function setIso3Code(?string $iso3Code): void
    {
        $this->setData(self::ISO3_CODE, $iso3Code);
    }

    /**
     * Getter for DefaultName.
     *
     * @return string|null
     */
    public function getDefaultName(): ?string
    {
        return $this->getData(self::DEFAULT_NAME);
    }

    /**
     * Setter for DefaultName.
     *
     * @param string|null $defaultName
     *
     * @return void
     */
    public function setDefaultName(?string $defaultName): void
    {
        $this->setData(self::DEFAULT_NAME, $defaultName);
    }
}
