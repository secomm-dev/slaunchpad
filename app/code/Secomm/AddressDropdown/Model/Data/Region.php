<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\AddressDropdown\Model\Data;

use Magento\Framework\DataObject;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Api\Data\RegionNameInterface;

class Region extends DataObject implements RegionInterface
{
    /**
     * @var RegionNameInterface
     */
    private $regionName;

    /**
     * Getter for RegionId.
     *
     * @return int|null
     */
    public function getRegionId(): ?int
    {
        return $this->getData(self::REGION_ID) === null ? null
            : (int)$this->getData(self::REGION_ID);
    }

    /**
     * Setter for RegionId.
     *
     * @param int|null $regionId
     *
     * @return void
     */
    public function setRegionId(?int $regionId): void
    {
        $this->setData(self::REGION_ID, $regionId);
    }

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
     * Getter for Code.
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->getData(self::CODE);
    }

    /**
     * Setter for Code.
     *
     * @param string|null $code
     *
     * @return void
     */
    public function setCode(?string $code): void
    {
        $this->setData(self::CODE, $code);
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

    /**
     * Getter for IsDefault.
     *
     * @return int|null
     */
    public function getIsDefault(): ?int
    {
        return $this->getData(self::IS_DEFAULT) === null ? null
            : (int)$this->getData(self::IS_DEFAULT);
    }

    /**
     * Setter for IsDefault.
     *
     * @param int|null $isDefault
     *
     * @return void
     */
    public function setIsDefault(?int $isDefault): void
    {
        $this->setData(self::IS_DEFAULT, $isDefault);
    }

    /**
     * Getter for Locale.
     *
     * @return string|null
     */
    public function getLocale(): ?string
    {
        return $this->getData(self::LOCALE);
    }

    /**
     * Setter for Locale.
     *
     * @param string|null $locale
     *
     * @return void
     */
    public function setLocale(?string $locale): void
    {
        $this->setData(self::LOCALE, $locale);
    }

    /**
     * Getter for Name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    /**
     * Setter for Name.
     *
     * @param string|null $name
     *
     * @return void
     */
    public function setName(?string $name): void
    {
        $this->setData(self::NAME, $name);
    }

    public function getRegionName()
    {
        return $this->regionName;
    }

    /**
     * Set Region Name
     *
     * @param RegionNameInterface $regionName
     * @return void
     */
    public function setRegionName(RegionNameInterface $regionName)
    {
        $this->regionName = $regionName;
    }
}
