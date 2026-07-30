<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Boolfly. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    info@boolfly.com
 * *  @project   Giao hang nhanh
 */
namespace Boolfly\GiaoHangNhanh\Model\Service\Request;

use Boolfly\GiaoHangNhanh\Model\Config;
use Boolfly\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Magento\Framework\Exception\NoSuchEntityException;
use Boolfly\GiaoHangNhanh\Helper\Rate;
use Boolfly\IntegrationBase\Model\Service\ConfigInterface;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Store\Model\Information;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ShippingDetailsDataBuilder
 *
 * @package Boolfly\GiaoHangNhanh\Model\Service\Request
 */
class ShippingDetailsDataBuilder extends AbstractDataBuilder
{
    const DEFAULT_WEIGHT_UNIT = 'kg';
    const DEFAULT_WEIGHT = 500; // default weight 500g if Magento weight is 0
    const DEFAULT_LENGTH = 10; // default min length 10cm
    const DEFAULT_WIDTH = 10;   // default min width 10cm
    const DEFAULT_HEIGHT = 10; // default min height 10cm

    public function __construct(
        ConfigInterface       $config,
        StoreManagerInterface $storeManager,
        Information           $storeInformation,
        AddressFactory        $addressFactory,
        Config                $baseConfig,
        Rate                  $helperRate
    ) {
        parent::__construct(
            $config,
            $storeManager,
            $storeInformation,
            $addressFactory,
            $baseConfig,
            $helperRate
        );
    }


    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        $rateRequest = SubjectReader::readRateRequest($buildSubject);
        $rate = $this->baseConfig->getWeightUnit() == self::DEFAULT_WEIGHT_UNIT ? Config::KGS_G : Config::LBS_G;
        if ($this->getIsDevelopMode()) {
            $toDistrictId = 1456;
            $toWardCode = '21511';
            $fromDistrictId = 1457;
            $fromWardCode = '21715';
        } else {
            $toDistrictId = '';
            $toWardCode = '';
            $fromDistrictId = '';
            $fromWardCode = '';
        }
        $length = ceil($rateRequest->getPackageLength());
        $width = ceil($rateRequest->getPackageWidth());
        $height = ceil($rateRequest->getPackageHeight());

        $packageWeight = (int)($rateRequest->getPackageWeight() * $rate);
        if ($packageWeight <= 0) {
            $packageWeight = self::DEFAULT_WEIGHT; // default weight 500g if Magento weight is 0
        }

        $data = [
            self::TOKEN => $this->config->getValue('api_token'),
            self::FROM_DISTRICT_ID => $fromDistrictId,
            self::FROM_WARD_CODE => $fromWardCode,
            self::SERVICE_TYPE_ID => null,
            self::TO_DISTRICT_ID => $toDistrictId,
            self::TO_WARD_CODE => $toWardCode,
            self::WEIGHT => $packageWeight,
            self::LENGTH => $length ?: self::DEFAULT_LENGTH, // default min length 10cm
            self::WIDTH => $width ?: self::DEFAULT_WIDTH,   // default min width 10cm
            self::HEIGHT => $height ?: self::DEFAULT_HEIGHT, // default min height 10cm
            self::COUPON => null,
            self::SHOP_ID => (int)$this->config->getValue('shop_id')
        ];

        if ($serviceId = SubjectReader::readServiceId($buildSubject)) {
            $data[self::SERVICE_ID] = $serviceId;
        }

        $data['items'] = $this->getAllItems($rateRequest->getAllItems());

        // payment COD
        if (isset($buildSubject['total_order'])) {
            $data[self::COD_VALUE] = $buildSubject['total_order'];
        }
 
        return $data;
    }
    
    /**
     * Get all items of order
     * @param \Magento\Quote\Model\Quote\Item[] $items
     * @return array
     */
    public function getAllItems($items)
    {
        $result = [];
        if (count($items) > 0) {
            $weightRate = $this->baseConfig->getWeightUnit() == self::DEFAULT_WEIGHT_UNIT ? Config::KGS_G : Config::LBS_G;
            /**
             * Required input Item when choosing a traditional delivery service
             * 5: Traditional Delivery
             * 2: E-commerce Delivery
             */
            foreach($items as $item){
                $tmp = [];
                $tmp['name'] = $item->getName();
                $tmp['code'] = $item->getSku();
                $tmp['quantity'] = (int)$item->getQty();
                $itemWeight = ceil($item->getWeight() * $weightRate * $tmp['quantity']);
                $tmp['weight'] = $itemWeight > 0 ? $itemWeight : self::DEFAULT_WEIGHT;
                $tmp['width'] = !is_null($item->getWidth()) && $item->getWidth() > 0 ? (ceil($item->getWidth()*$tmp['quantity'])) : self::DEFAULT_WIDTH;
                $tmp['height'] = !is_null($item->getHeight()) && $item->getHeight() > 0 ? (ceil($item->getHeight()*$tmp['quantity'])) : self::DEFAULT_HEIGHT;
                $tmp['length'] = !is_null($item->getLength()) && $item->getLength() > 0 ? (ceil($item->getLength()*$tmp['quantity'])) : self::DEFAULT_LENGTH;

                array_push($result, $tmp);
            }
        }

        return $result;
    }
}
