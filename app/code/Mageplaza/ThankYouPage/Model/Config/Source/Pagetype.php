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

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class Pagetype
 * @package Mageplaza\ThankYouPage\Model\Config\Source
 */
class Pagetype implements OptionSourceInterface
{
    const ORDER      = 'order';
    const NEWSLETTER = 'newsletter';
    /**
     * Default product page type
     */
    const DEFAULT_TYPE_PAGE = 'order';

    /**
     * @return array
     */
    public function getPageType()
    {
        return [
            self::ORDER      => __('Order Success Page'),
            self::NEWSLETTER => __('Newsletter Success Page')
        ];
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => self::ORDER,
                'label' => __('Order Success Page')
            ],
            [
                'value' => self::NEWSLETTER,
                'label' => __('Newsletter Success Page')
            ]
        ];

        return $options;
    }
}
