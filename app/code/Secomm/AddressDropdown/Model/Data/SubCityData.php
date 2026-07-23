<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Data;

use Magento\Framework\DataObject;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;

class SubCityData extends DataObject implements SubCityInterface
{
    /**
     * Getter for SubCityId.
     *
     * @return int|null
     */
    public function getSubCityId(): ?int
    {
        return $this->getData(self::SUB_CITY_ID) === null ? null
            : (int)$this->getData(self::SUB_CITY_ID);
    }

    /**
     * Setter for SubCityId.
     *
     * @param int|null $subCityId
     *
     * @return void
     */
    public function setSubCityId(?int $subCityId): void
    {
        $this->setData(self::SUB_CITY_ID, $subCityId);
    }

    /**
     * Getter for CityId.
     *
     * @return int|null
     */
    public function getCityId(): ?int
    {
        return $this->getData(self::CITY_ID) === null ? null
            : (int)$this->getData(self::CITY_ID);
    }

    /**
     * Setter for CityId.
     *
     * @param int|null $cityId
     *
     * @return void
     */
    public function setCityId(?int $cityId): void
    {
        $this->setData(self::CITY_ID, $cityId);
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
