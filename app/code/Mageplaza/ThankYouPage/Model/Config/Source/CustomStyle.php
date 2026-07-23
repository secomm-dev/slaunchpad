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
 * Class CustomStyle
 * @package Mageplaza\ThankYouPage\Model\Config\Source
 */
class CustomStyle implements OptionSourceInterface
{
    const USE_DEFAULT = 0;
    const CUSTOM      = 1;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => self::USE_DEFAULT,
                'label' => __('Use Default Style')
            ],
            [
                'value' => self::CUSTOM,
                'label' => __('Edit Default Style')
            ]
        ];

        return $options;
    }
}
