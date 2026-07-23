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
 * @package     Mageplaza_OscPro
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\OscPro\Model\System\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class BillingAddressChangeOptions
 * @package Mageplaza\OscPro\Model\System\Config\Source
 */
class ShippingMethodChangeOptions implements ArrayInterface
{
    const NULL = 0;
    const PAYMENT_METHOD = 1;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::NULL, 'label' => __('-- Please select --')],
            ['value' => self::PAYMENT_METHOD, 'label' => __('Refresh Payment Method')],
        ];
    }
}
