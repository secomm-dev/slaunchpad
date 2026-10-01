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

namespace Mageplaza\RMA\Model\Config\Source\RMAStatus;

use Mageplaza\RMA\Model\Config\Source\AbstractOption;

/**
 * Class Action
 * @package Mageplaza\RMA\Model\Config\Source\RMAStatus
 */
class Action extends AbstractOption
{
    const CREDIT_MEMO = 1;
    const REORDER = 2;
    const SHIPPING_LABEL = 3;

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            0 => __('-- Please Select --'),
            self::CREDIT_MEMO => __('Create New Credit Memo'),
            self::REORDER => __('Reorder (Replace return Product by create new order)'),
            self::SHIPPING_LABEL => __('Add Shipping Label')
        ];
    }
}
