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
 * @category  Mageplaza
 * @package   Mageplaza_SocialLoginPro
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\SocialLoginPro\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class Redirect
 *
 * @package Mageplaza\RequiredLogin\Model\Config\Source
 */
class Redirect implements ArrayInterface
{
    const CUSTOMER_DASHBOARD = 1;
    const HOME_PAGE          = 2;
    const CMS_PAGE           = 3;
    const CUSTOM_URL         = 4;
    const PREVIOUS_PAGE      = 5;

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::CUSTOMER_DASHBOARD, 'label' => __('Customer Dashboard')],
            ['value' => self::HOME_PAGE, 'label' => __('Home page')],
            ['value' => self::CMS_PAGE, 'label' => __('CMS Pages')],
            ['value' => self::CUSTOM_URL, 'label' => __('Custom URL')],
            ['value' => self::PREVIOUS_PAGE, 'label' => __('Previous Page')]
        ];
    }
}
