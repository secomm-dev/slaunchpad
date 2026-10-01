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
 * Class FindOrder
 * @package Mageplaza\RMA\Model\Config\Source\RMARequest
 */
class FindOrder extends AbstractOption
{
    const FIND_BY_EMAIL = 'email';
    const FIND_BY_ZIP_CODE = 'zip';

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            self::FIND_BY_EMAIL => __('Email'),
            self::FIND_BY_ZIP_CODE => __('ZIP Code')
        ];
    }
}
