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
 * Class Additional
 * @package Mageplaza\ThankYouPage\Model\Config\Source
 */
class Additional implements ArrayInterface
{
    const SHOW_CART     = 1;
    const SHOW_WISHLIST = 2;
    const SHOW_COMPARE  = 3;
    const SHOW_REVIEW   = 4;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];

        foreach ($this->toArray() as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label
            ];
        }

        return $options;
    }

    /**
     * @return array
     */
    protected function toArray()
    {
        return [
            self::SHOW_CART     => __('Add to Cart button'),
            self::SHOW_WISHLIST => __('Add to WishList button'),
            self::SHOW_COMPARE  => __('Add to Compare button'),
            self::SHOW_REVIEW   => __('Review information')
        ];
    }
}
