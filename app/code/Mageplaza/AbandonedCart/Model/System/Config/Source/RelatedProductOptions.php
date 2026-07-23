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
 * @package     Mageplaza_Osc
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Model\System\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class RelatedProductOptions
 * @package Mageplaza\AbandonedCart\Model\System\Config\Source
 */
class RelatedProductOptions implements ArrayInterface
{
    const NO                  = 0;
    const RELATED_PRODUCTS    = 1;
    const UP_SELL_PRODUCTS    = 2;
    const CROSS_SELL_PRODUCTS = 3;

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        $timeArray = [
            [
                'label' => __('No'),
                'value' => self::NO,
            ],
            [
                'label' => __('Related Products'),
                'value' => self::RELATED_PRODUCTS,
            ],
            [
                'label' => __('Up-Sell Products'),
                'value' => self::UP_SELL_PRODUCTS,
            ],
            [
                'label' => __('Cross-Sell Products'),
                'value' => self::CROSS_SELL_PRODUCTS,
            ],
        ];

        return $timeArray;
    }
}
