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

namespace Mageplaza\SocialLoginPro\Plugin\Model\System\Config\Source;

use Mageplaza\SocialLogin\Model\System\Config\Source\Popup as ConfigPopup;

/**
 * Class Popup
 *
 * @package Mageplaza\SocialLoginPro\Plugin\Model\System\Config\Source
 */
class Popup
{
    const QUICK_LOGIN = 'quick_login';
    const POPUP_SLIDE = 'popup_slide';

    /**
     * @param ConfigPopup $subject
     * @param $result
     *
     * @return array
     */
    public function afterToOptionArray(ConfigPopup $subject, $result)
    {
        $configData = [
            ['value' => self::QUICK_LOGIN, 'label' => __('Quick Login')],
            ['value' => self::POPUP_SLIDE, 'label' => __('Popup Slide')]
        ];

        return array_merge($result, $configData);
    }
}
