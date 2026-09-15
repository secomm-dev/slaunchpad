<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */

namespace Secomm\GiaoHangNhanh\Model\Carrier\GHN;

use Secomm\GiaoHangNhanh\Model\Carrier\GHN;

/**
 * Class Express
 *
 * @package Secomm\GiaoHangNhanh\Model\Carrier\GHN
 */
class Express extends GHN
{
    const SERVICE_NAME = 'Chuyển phát thương mại điện tử';
    const SERVICE_NAME_SHORT = 'Hàng nặng';
    const MAX_HEIGHT = 200; //centimeter
    const MAX_WIDTH = 200; //centimeter
    const MAX_LENGTH = 150; //centimeter — GHN create-order API limit (fee API allows 200)
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
        // (float) cast: getPackage*() may be null on some collect-rates paths —
        // PHP 8 ceil(null) throws TypeError.
        $length = ceil((float)$request->getPackageLength());
        $width = ceil((float)$request->getPackageWidth());
        $height = ceil((float)$request->getPackageHeight());

        $maxWeight = $this->getMaxWeight() ?: self::MAX_WEIGHT;
        $maxWidth = $this->getMaxWidth() ?: self::MAX_WIDTH;
        $maxHeight = $this->getMaxHeight() ?: self::MAX_HEIGHT;
        $maxLength = $this->getMaxLength() ?: self::MAX_LENGTH;
        $weightKgMagento = $this->ghnHelperData->convertToKilograms($request->getPackageWeight());
        $ruleWeightKgGhn = ($length * $width * $height) / 5000;
        $maxConvertedMassOrder = $this->getMaxConvertedMassOrder() ?: self::MAX_CONVERTED_MASS;

        return $length <= $maxLength
            && $width <= $maxWidth
            && $height <= $maxHeight
            && $weightKgMagento <= $maxWeight
            && $ruleWeightKgGhn <= $maxConvertedMassOrder;
    }
}
