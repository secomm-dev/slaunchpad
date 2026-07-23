<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Data;

use Magento\Framework\DataObject;
use Secomm\AddressDropdown\Api\Data\CityNameInterface;

class CityNameData extends DataObject implements CityNameInterface
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
}
