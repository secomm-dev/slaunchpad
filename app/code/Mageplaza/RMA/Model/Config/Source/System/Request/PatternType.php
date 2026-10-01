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
 * Class PatternType
 * @package Mageplaza\RMA\Model\Config\Source\System\Request
 */
class PatternType extends AbstractOption
{
    const ONLY_ID = 1;
    const CUSTOM = 2;

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            self::ONLY_ID => __('Only ID'),
            self::CUSTOM => __('Custom')
        ];
    }
}
