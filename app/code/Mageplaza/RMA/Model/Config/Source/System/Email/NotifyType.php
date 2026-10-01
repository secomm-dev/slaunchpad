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

namespace Mageplaza\RMA\Model\Config\Source\System\Email;

use Mageplaza\RMA\Model\Config\Source\AbstractOption;

/**
 * Class NotifyType
 * @package Mageplaza\RMA\Model\Config\Source\System\Email
 */
class NotifyType extends AbstractOption
{
    const NO = 0;
    const AGENT = 1;
    const ALL_ADDRESS = 2;

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            self::NO => __('No'),
            self::AGENT => __('Only request agent'),
            self::ALL_ADDRESS => __('All above address')
        ];
    }
}
