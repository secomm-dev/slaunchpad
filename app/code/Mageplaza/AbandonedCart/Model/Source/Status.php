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
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class Status
 * @package Mageplaza\Smtp\Model\Source
 */
class Status implements OptionSourceInterface
{
    const SENT = 0;
    const RECOVER   = 1;
    const ERROR   = 2;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::SENT, 'label' => __('SENT')],
            ['value' => self::RECOVER, 'label' => __('RECOVER')],
            ['value' => self::ERROR, 'label' => __('ERROR')],
        ];
    }
}
