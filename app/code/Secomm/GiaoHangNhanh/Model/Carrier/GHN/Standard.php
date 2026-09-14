<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Carrier\GHN;

use Secomm\GiaoHangNhanh\Model\Carrier\GHN;

/**
 * Class Standard
 *
 * @package Secomm\GiaoHangNhanh\Model\Carrier\GHN
 */
class Standard extends GHN
{
    const SERVICE_NAME = 'Chuyển phát truyền thống';
    const SERVICE_NAME_SHORT = 'Hàng nhẹ';
    const MAX_HEIGHT = 200; //centimeter
    const MAX_WIDTH = 200; //centimeter
    const MAX_LENGTH = 150; //centimeter — GHN create-order API limit (fee API allows 200)
    const MAX_WEIGHT = 20; //kilograms
    const MAX_CONVERTED_MASS = 1600; //kilograms

    /**
     * @var string
     */
    protected $_code = 'giaohangnhanh_standard';

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
            && $ruleWeightKgGhn <= $maxConvertedMassOrder
            && $this->validateDataProduct($request);
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
        $config = [
            'max_width_item' => $this->getMaxWidthItem() ?: self::MAX_WIDTH,
            'max_weight_item' => $this->getMaxWeightItem() ?: self::MAX_WEIGHT,
            'max_height_item' => $this->getMaxHeightItem() ?: self::MAX_HEIGHT,
            'max_length_item' => $this->getMaxLengthItem() ?: self::MAX_LENGTH,
            'max_converted_mass_item' => $this->getMaxConvertedMassItem() ?: self::MAX_CONVERTED_MASS,
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

        return $weightKgMagento <= $config['max_weight_item']
            && $height <= $config['max_height_item']
            && $length <= $config['max_length_item']
            && $width <= $config['max_width_item']
            && $ruleWeightKgGhn <= $config['max_converted_mass_item'];
    }
}
