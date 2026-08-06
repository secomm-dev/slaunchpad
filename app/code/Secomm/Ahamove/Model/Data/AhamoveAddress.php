<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Data;

use Magento\Framework\DataObject;
use Secomm\Ahamove\Api\Data\AhamoveAddressInterface;

class AhamoveAddress extends DataObject implements AhamoveAddressInterface
{
    const COUNTRY_ID_FROM = 'country_id_from';
    const CITY_FROM = 'city_from';
    const REGION_CODE_FROM = 'region_code_from';
    const STREET_FROM = 'street_from';
    const POST_CODE_FROM = 'post_code_from';
    const NAME_FROM = 'name_from';
    const PHONE_FROM = 'phone_from';
    const COUNTRY_ID_TO = 'country_id_to';
    const CITY_TO = 'city_to';
    const REGION_CODE_TO = 'region_code_to';
    const STREET_TO = 'street_to';
    const POST_CODE_TO = 'post_code_to';
    const NAME_TO = 'name_to';
    const PHONE_TO = 'phone_to';
    const REMARK = 'remark';

    const REQUIRE_FIELDS = [
        self::STREET_TO,
        self::STREET_FROM
    ];

    const FULL_ADDRESS = [
        'street',
        'city',
        'region_code',
    ];

    /**
     * @param string $countryId
     * @return AhamoveAddressInterface
     */
    public function setCountryIdFrom(string $countryId): AhamoveAddressInterface
    {
        $this->setData(self::COUNTRY_ID_FROM, $countryId);
        return $this;
    }

    /**
     * @return string
     */
    public function getCountryIdFrom(): string
    {
        return $this->getData(self::COUNTRY_ID_FROM);
    }

    /**
     * @param string $city
     * @return AhamoveAddressInterface
     */
    public function setCityFrom(string $city): AhamoveAddressInterface
    {
        $this->setData(self::CITY_FROM, $city);
        return $this;
    }

    /**
     * @return string
     */
    public function getCityFrom(): string
    {
        return $this->getData(self::CITY_FROM);
    }

    /**
     * @param string $regionCode
     * @return AhamoveAddressInterface
     */
    public function setRegionCodeFrom(string $regionCode): AhamoveAddressInterface
    {
        $this->setData(self::REGION_CODE_FROM, $regionCode);
        return $this;
    }

    /**
     * @return string
     */
    public function getRegionCodeFrom(): string
    {
        return $this->getData(self::REGION_CODE_FROM);
    }

    /**
     * @param string $street
     * @return AhamoveAddressInterface
     */
    public function setStreetFrom(string $street): AhamoveAddressInterface
    {
        $this->setData(self::STREET_FROM, $street);
        return $this;
    }

    /**
     * @return string
     */
    public function getStreetFrom(): string
    {
        return $this->getData(self::STREET_FROM);
    }

    /**
     * @param string $postCode
     * @return AhamoveAddressInterface
     */
    public function setPostCodeFrom(string $postCode): AhamoveAddressInterface
    {
        $this->setData(self::POST_CODE_FROM, $postCode);
        return $this;
    }

    /**
     * @return string
     */
    public function getPostCodeFrom(): string
    {
        return $this->getData(self::POST_CODE_FROM);
    }

    /**
     * @param string $name
     * @return AhamoveAddressInterface
     */
    public function setNameFrom(string $name): AhamoveAddressInterface
    {
        $this->setData(self::NAME_FROM, $name);
        return $this;
    }

    /**
     * @return string
     */
    public function getNameFrom(): string
    {
        return $this->getData(self::NAME_FROM);
    }

    /**
     * @param string $phone
     * @return AhamoveAddressInterface
     */
    public function setPhoneFrom(string $phone): AhamoveAddressInterface
    {
        $this->setData(self::PHONE_FROM, $phone);
        return $this;
    }

    /**
     * @return string
     */
    public function getPhoneFrom(): string
    {
        return $this->getData(self::PHONE_FROM);
    }

    /**
     * @param string $countryId
     * @return AhamoveAddressInterface
     */
    public function setCountryIdTo(string $countryId): AhamoveAddressInterface
    {
        $this->setData(self::COUNTRY_ID_TO, $countryId);
        return $this;
    }

    /**
     * @return string
     */
    public function getCountryIdTo(): string
    {
        return $this->getData(self::COUNTRY_ID_TO);
    }

    /**
     * @param string $city
     * @return AhamoveAddressInterface
     */
    public function setCityTo(string $city): AhamoveAddressInterface
    {
        $this->setData(self::CITY_TO, $city);
        return $this;
    }

    /**
     * @return string
     */
    public function getCityTo(): string
    {
        return $this->getData(self::CITY_TO);
    }

    /**
     * @param string $regionCode
     * @return AhamoveAddressInterface
     */
    public function setRegionCodeTo(string $regionCode): AhamoveAddressInterface
    {
        $this->setData(self::REGION_CODE_TO, $regionCode);
        return $this;
    }

    /**
     * @return string
     */
    public function getRegionCodeTo(): string
    {
        return $this->getData(self::REGION_CODE_TO);
    }

    /**
     * @param string $street
     * @return AhamoveAddressInterface
     */
    public function setStreetTo(string $street): AhamoveAddressInterface
    {
        $this->setData(self::STREET_TO, $street);
        return $this;
    }

    /**
     * @return string
     */
    public function getStreetTo(): string
    {
        return $this->getData(self::STREET_TO);
    }

    /**
     * @param string $postCode
     * @return AhamoveAddressInterface
     */
    public function setPostCodeTo(string $postCode): AhamoveAddressInterface
    {
        $this->setData(self::POST_CODE_TO, $postCode);
        return $this;
    }

    /**
     * @return string
     */
    public function getPostCodeTo(): string
    {
        return $this->getData(self::POST_CODE_TO);
    }

    /**
     * @param string $name
     * @return AhamoveAddressInterface
     */
    public function setNameTo(string $name): AhamoveAddressInterface
    {
        $this->setData(self::NAME_TO, $name);
        return $this;
    }

    /**
     * @return string
     */
    public function getNameTo(): string
    {
        return $this->getData(self::NAME_TO);
    }

    /**
     * @param string $phone
     * @return AhamoveAddressInterface
     */
    public function setPhoneTo(string $phone): AhamoveAddressInterface
    {
        $this->setData(self::PHONE_TO, $phone);
        return $this;
    }

    /**
     * @return string
     */
    public function getPhoneTo(): string
    {
        return $this->getData(self::PHONE_TO);
    }

    /**
     * @param string $remark
     * @return AhamoveAddressInterface
     */
    public function setRemark(string $remark): AhamoveAddressInterface
    {
        $this->setData(self::REMARK, $remark);
        return $this;
    }

    /**
     * @return string
     */
    public function getRemark(): string
    {
        return $this->getData(self::REMARK);
    }

    /**
     * @param string $type
     * @return string
     */
    public function buildAddress(string $type = 'from'): string
    {
        $fullAddress = [];
        foreach (self::FULL_ADDRESS as $k) {
            if ($this->getData("{$k}_{$type}") !== '' && $this->getData("{$k}_{$type}") !== null) {
                $fullAddress["{$k}_{$type}"] = $this->getData("{$k}_{$type}");
            }
        }
        return implode(', ', $fullAddress);
    }
}
