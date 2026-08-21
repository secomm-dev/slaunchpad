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
 * Class ApplyType
 * @package Mageplaza\ExtraFee\Model\Config\Source
 */
class DisplayArea extends AbstractSource
{
    const PAYMENT_METHOD  = 1;
    const SHIPPING_METHOD = 2;
    const CART_SUMMARY    = 3;
    const TOTAL           = 4;

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            self::PAYMENT_METHOD  => __('Payment Method'),
            self::SHIPPING_METHOD => __('Shipping Method'),
            self::CART_SUMMARY    => __('Cart Summary'),
        ];
    }
}
