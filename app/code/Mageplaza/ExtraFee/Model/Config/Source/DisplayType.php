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
 * Class DisplayType
 * @package Mageplaza\ExtraFee\Model\Config\Source
 */
class DisplayType extends AbstractSource
{
    const RADIO     = 1;
    const CHECK_BOX = 2;
    const SELECT    = 3;

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            self::RADIO     => __('Radio'),
            self::CHECK_BOX => __('Check Box'),
            self::SELECT    => __('Select'),
        ];
    }
}
