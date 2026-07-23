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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class LazyLoad
 * @package Mageplaza\Lookbook\Model\Config\Source
 */
class LazyLoad implements OptionSourceInterface
{
    const NO = '0';
    const ONDEMAND = 'ondemand';
    const PROGRESSIVE = 'progressive';

    /**
     * @return array|array[]
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::NO, 'label' => __('No')],
            ['value' => self::ONDEMAND, 'label' => __('On Demand')],
            ['value' => self::PROGRESSIVE, 'label' => __('Progressive')],
        ];
    }
}
