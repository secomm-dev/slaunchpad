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
 * Class ReplyName
 * @package Mageplaza\RMA\Model\Config\Source\System\Request
 */
class ReplyName extends AbstractOption
{
    const DEFAULT_NAME = 1;
    const ARGENT_NAME = 2;

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            self::DEFAULT_NAME => __('Default Name'),
            self::ARGENT_NAME => __('Admin Argent Name')
        ];
    }
}
