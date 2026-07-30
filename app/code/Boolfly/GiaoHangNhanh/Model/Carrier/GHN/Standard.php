<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Boolfly. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    info@boolfly.com
 * *  @project   Giao hang nhanh
 */
namespace Boolfly\GiaoHangNhanh\Model\Carrier\GHN;

use Boolfly\GiaoHangNhanh\Model\Carrier\GHN;

/**
 * Class Standard
 *
 * @package Boolfly\GiaoHangNhanh\Model\Carrier\GHN
 */
class Standard extends GHN
{
    const SERVICE_NAME = 'Chuyển phát truyền thống';
    const SERVICE_TYPE_ID = 5; // GHN API v2 service_type_id for Standard / Hàng nặng
    const MAX_HEIGHT = 20000; //centimeter
    const MAX_WIDTH = 20000; //centimeter
    const MAX_LENGTH = 20000; //centimeter
    const MAX_WEIGHT = 5000; //kilograms
    const MAX_CONVERTED_MASS = 1600; //kilograms

    /**
     * @var string
     */
    protected $_code = 'giaohangnhanh_standard';

    public function canDisplay($request): bool
    {
        $length = ceil($request->getPackageLength());
        $width = ceil($request->getPackageWidth());
        $height = ceil($request->getPackageHeight());
        //If not set value, that means no limit

        $maxWeight = $this->getMaxWeight() ?: self::MAX_WEIGHT;
        $maxWidth = $this->getMaxWidth() ?: self::MAX_WIDTH;
        $maxHeight = $this->getMaxHeight() ?: self::MAX_HEIGHT;
        $maxLength = $this->getMaxLength() ?: self::MAX_LENGTH;
        $weightKgMagento = $this->ghnHelperData->convertToKilograms($request->getPackageWeight());
        $ruleWeightKgGhn = ($length * $width * $height) / 5000;
        $maxConvertedMassOrder = $this->getMaxConvertedMassOrder() ?: self::MAX_CONVERTED_MASS;
        if ($length <= $maxLength && $width <= $maxWidth && $height <= $maxHeight && $weightKgMagento <= $maxWeight && $ruleWeightKgGhn <= $maxConvertedMassOrder) {
            if (!$this->validateDataProduct($request)) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Validate a data of product
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return bool
     */
    private function validateDataProduct($request)
    {
        if (!count($allItems = $request->getAllItems())) {
            return false;
        }

        /** @var \Magento\Quote\Model\Quote\Item[] $allItems */
        $defaultValue = $this->getDefaultValueItem();
        $config = [
            'max_width_item' => $this->getMaxWidthItem() ?: $defaultValue['max_width'],
            'max_weight_item' => $this->getMaxWeightItem() ?: $defaultValue['max_weight'],
            'max_height_item' => $this->getMaxHeightItem() ?: $defaultValue['max_height'],
            'max_length_item' => $this->getMaxLengthItem() ?: $defaultValue['max_length'],
            'max_converted_mass_item' => $this->getMaxConvertedMassItem() ?: $defaultValue['max_converted_mass'],
        ];
        foreach($allItems as $item){
            if (is_null($item->getWidth()) || empty($item->getWidth())
                || is_null($item->getHeight()) || empty($item->getHeight())
                || is_null($item->getLength()) || empty($item->getLength())
                || is_null($item->getWeight()) || empty($item->getWeight())
            ) {
                return false;
            }
            if (!$this->validItemOrder($item, $config)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate an item of the order
     * @param \Magento\Quote\Model\Quote\Item $item
     * @param array $config
     * @return bool
     */
    private function validItemOrder($item, $config): bool
    {
        $length = (int)$item->getLength();
        $width = (int)$item->getWidth();
        $height = (int)$item->getHeight();
        $ruleWeightKgGhn = ($length * $width * $height) / 5000;
        $weightKgMagento = $this->ghnHelperData->convertToKilograms($item->getWeight());

        if ($weightKgMagento > $config['max_weight_item']
            || $item->getHeight() > $config['max_height_item']
            || $item->getLength() > $config['max_length_item']
            || $item->getWidth() > $config['max_width_item']
            || $ruleWeightKgGhn > $config['max_converted_mass_item']
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get a default value of item
     * @return array
     */
    private function getDefaultValueItem()
    {
        $defaultValue = [
            'max_height' => 200, //centimeter
            'max_length' => 200, //centimeter
            'max_width' => 200, //centimeter
            'max_weight' => 50, //kilograms
            'max_converted_mass' => 200 //kilograms
        ];

        return $defaultValue;
    }
}
