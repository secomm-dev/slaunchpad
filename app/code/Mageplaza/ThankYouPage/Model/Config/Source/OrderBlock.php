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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class OrderBlock
 * @package Mageplaza\ThankYouPage\Model\Config\Source
 */
class OrderBlock implements ArrayInterface
{
    const ORDER_DETAIL   = 'order_detail';
    const SOCIAL_SHARING = 'social_sharing';
    const ACCOUNT        = 'account';
    const SUBSCRIBE      = 'subscribe';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => '',
                'label' => __('-- None --')
            ],
            [
                'value' => self::ORDER_DETAIL,
                'label' => __('Order Details')
            ],
            [
                'value' => self::SOCIAL_SHARING,
                'label' => __('Social Sharing')
            ],
            [
                'value' => self::ACCOUNT,
                'label' => __('Register Account Form')
            ],
            [
                'value' => self::SUBSCRIBE,
                'label' => __('Subscribe Email Form')
            ]
        ];

        return $options;
    }
}
