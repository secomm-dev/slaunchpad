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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Model\Config\Source\RMAShippingLabel;

use Mageplaza\RMA\Model\Config\Source\AbstractOption;

/**
 * Class Information
 * @package Mageplaza\RMA\Model\Config\Source\RMAShippingLabel
 */
class Information extends AbstractOption
{
    const LOGO = 'logo';
    const ORDER_SHIPPING_ADDRESS = 'order_shipping_address';
    const ORDER_INCREMENT_ID = 'order_increment_id';
    const RMA_INCREMENT_ID = 'rma_increment_id';
    const RMA_INFORMATION = 'rma_information';
    const RETURN_SHIPPING_ADDRESS = 'return_shipping_address';
    const PRINT_DATE = 'print_date';
    const REQUEST_DATE = 'request_date';
    const BARCODE = 'barcode';

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            0 => __('-- Please Select --'),
            self::LOGO => __('Logo'),
            self::ORDER_SHIPPING_ADDRESS => __('Order Shipping Address'),
            self::ORDER_INCREMENT_ID => __('Order Increment ID'),
            self::RMA_INCREMENT_ID => __('RMA Increment ID'),
            self::RMA_INFORMATION => __('RMA Information'),
            self::RETURN_SHIPPING_ADDRESS => __('Return Shipping Address'),
            self::PRINT_DATE => __('Print Date'),
            self::REQUEST_DATE => __('Request Date'),
            self::BARCODE => __('Barcode'),
        ];
    }
}
