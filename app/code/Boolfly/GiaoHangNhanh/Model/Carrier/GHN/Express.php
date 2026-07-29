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
 * Class Express
 *
 * @package Boolfly\GiaoHangNhanh\Model\Carrier\GHN
 */
class Express extends GHN
{
    const SERVICE_NAME = 'Chuyển phát thương mại điện tử';
    const MAX_HEIGHT = 200; //centimeter
    const MAX_WIDTH = 200; //centimeter
    const MAX_LENGTH = 200; //centimeter
    const MAX_WEIGHT = 50; //kilograms
    const MAX_CONVERTED_MASS = 200; //kilograms

    /**
     * @var string
     */
    protected $_code = 'giaohangnhanh_express';

    /**
     * This method is used to check if the shipping method can be displayed or not
     *
     * @param $request
     * @return bool
     */
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
            return true;
        } else {
            return false;
        }
    }
}
