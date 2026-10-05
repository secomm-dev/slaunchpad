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

namespace Mageplaza\RMA\Model\Config\Source\RMARequest;

use Mageplaza\RMA\Model\Config\Source\AbstractOption;

/**
 * Class ReturnType
 * @package Mageplaza\RMA\Model\Config\Source\RMARequest
 */
class ReturnType extends AbstractOption
{
    const ALL_ITEMS = 1;
    const EACH_ITEM = 2;

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            self::ALL_ITEMS => __('All Items'),
            self::EACH_ITEM => __('Each Item')
        ];
    }
}
