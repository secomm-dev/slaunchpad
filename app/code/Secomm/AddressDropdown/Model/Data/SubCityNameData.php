<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Data;

use Magento\Framework\DataObject;
use Secomm\AddressDropdown\Api\Data\SubCityNameInterface;

class SubCityNameData extends DataObject implements SubCityNameInterface
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
