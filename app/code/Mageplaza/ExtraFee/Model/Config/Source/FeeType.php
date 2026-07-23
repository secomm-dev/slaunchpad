<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Model\Config\Source;

use Mageplaza\ExtraFee\Model\Config\AbstractSource;

/**
 * Class FeeType
 * @package Mageplaza\ExtraFee\Model\Config\Source
 */
class FeeType extends AbstractSource
{
    const FIX_AMOUNT_FOR_EACH_ITEM  = 1;
    const FIX_AMOUNT_FOR_WHOLE_CART = 2;
    const PERCENTAGE_OF_CART_TOTAL  = 3;

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            self::FIX_AMOUNT_FOR_EACH_ITEM  => __('Fixed amount for each item'),
            self::FIX_AMOUNT_FOR_WHOLE_CART => __('Fixed amount for the whole cart'),
            self::PERCENTAGE_OF_CART_TOTAL  => __('Percentage of cart total'),
        ];
    }
}
