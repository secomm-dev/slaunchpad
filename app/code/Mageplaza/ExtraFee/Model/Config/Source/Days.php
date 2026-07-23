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
 * Class Days
 * @package Mageplaza\ExtraFee\Model\Config\Source
 */
class Days extends AbstractSource
{
    const MONDAY    = 'monday';
    const TUESDAY   = 'tuesday';
    const WEDNESDAY = 'wednesday';
    const THURSDAY  = 'thursday';
    const FRIDAY    = 'friday';
    const SATURDAY  = 'saturday';
    const SUNDAY    = 'sunday';

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            self::MONDAY    => __('Monday'),
            self::TUESDAY   => __('Tuesday'),
            self::WEDNESDAY => __('Wednesday'),
            self::THURSDAY  => __('Thursday'),
            self::FRIDAY    => __('Friday'),
            self::SATURDAY  => __('Saturday'),
            self::SUNDAY    => __('Sunday')
        ];
    }
}
