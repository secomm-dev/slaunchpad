<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Data;

use Magento\Framework\DataObject;
use Secomm\AddressDropdown\Api\Data\CityInterface;

class CityData extends DataObject implements CityInterface
{
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
