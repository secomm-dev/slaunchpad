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

namespace Mageplaza\RMA\Model\Config\Source\System\Request;

use Mageplaza\RMA\Model\Config\Source\AbstractOption;

/**
 * Class Location
 * @package Mageplaza\RMA\Model\Config\Source\System\Request
 */
class Location extends AbstractOption
{
    const TOP_LINK = 1;
    const FOOTER_LINK = 2;

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            0 => __('-- Please select --'),
            self::TOP_LINK => __('Top Link'),
            self::FOOTER_LINK => __('Footer Link')
        ];
    }
}
