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
 * Class FeeTypeItem
 * @package Mageplaza\ExtraFee\Model\Config\Source
 */
class FeeTypeItem extends AbstractSource
{
    const FIX_AMOUNT_FOR_ITEM    = 4;
    const PERCENTAGE_ITEM_AMOUNT = 5;

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            self::FIX_AMOUNT_FOR_ITEM       => __('Fix amount for item'),
            self::PERCENTAGE_ITEM_AMOUNT    => __('Percentage item amount')
        ];
    }
}
