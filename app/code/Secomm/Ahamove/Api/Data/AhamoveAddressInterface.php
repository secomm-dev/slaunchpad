<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Api\Data;

interface AhamoveAddressInterface
{


    /**
     * @param string $countryId
     * @return AhamoveAddressInterface
     */
    public function setCountryIdFrom(string $countryId): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getCountryIdFrom(): string;

    /**
     * @param string $city
     * @return AhamoveAddressInterface
     */
    public function setCityFrom(string $city): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getCityFrom(): string;

    /**
     * @param string $regionCode
     * @return AhamoveAddressInterface
     */
    public function setRegionCodeFrom(string $regionCode): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getRegionCodeFrom(): string;

    /**
     * @param string $street
     * @return AhamoveAddressInterface
     */
    public function setStreetFrom(string $street): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getStreetFrom(): string;

    /**
     * @param string $postCode
     * @return AhamoveAddressInterface
     */
    public function setPostCodeFrom(string $postCode): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getPostCodeFrom(): string;

    /**
     * @param string $name
     * @return AhamoveAddressInterface
     */
    public function setNameFrom(string $name): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getNameFrom(): string;

    /**
     * @param string $phone
     * @return AhamoveAddressInterface
     */
    public function setPhoneFrom(string $phone): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getPhoneFrom(): string;

    /**
     * @param string $countryId
     * @return AhamoveAddressInterface
     */
    public function setCountryIdTo(string $countryId): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getCountryIdTo(): string;

    /**
     * @param string $city
     * @return AhamoveAddressInterface
     */
    public function setCityTo(string $city): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getCityTo(): string;

    /**
     * @param string $regionCode
     * @return AhamoveAddressInterface
     */
    public function setRegionCodeTo(string $regionCode): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getRegionCodeTo(): string;

    /**
     * @param string $street
     * @return AhamoveAddressInterface
     */
    public function setStreetTo(string $street): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getStreetTo(): string;

    /**
     * @param string $postCode
     * @return AhamoveAddressInterface
     */
    public function setPostCodeTo(string $postCode): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getPostCodeTo(): string;

    /**
     * @param string $name
     * @return AhamoveAddressInterface
     */
    public function setNameTo(string $name): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getNameTo(): string;

    /**
     * @param string $phone
     * @return AhamoveAddressInterface
     */
    public function setPhoneTo(string $phone): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getPhoneTo(): string;

    /**
     * @param string $remark
     * @return AhamoveAddressInterface
     */
    public function setRemark(string $remark): AhamoveAddressInterface;

    /**
     * @return string
     */
    public function getRemark(): string;
}
