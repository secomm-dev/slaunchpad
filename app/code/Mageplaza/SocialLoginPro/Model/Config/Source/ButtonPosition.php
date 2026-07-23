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
 * Class ButtonPosition
 *
 * @package Mageplaza\SocialLoginPro\Model\System\Config\Source
 */
class ButtonPosition implements ArrayInterface
{
    const POSITION_LEFT   = 'left';
    const POSITION_RIGHT  = 'right';
    const POSITION_TOP    = 'top';
    const POSITION_BOTTOM = 'bottom';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::POSITION_LEFT, 'label' => __('Left')],
            ['value' => self::POSITION_RIGHT, 'label' => __('Right')],
            ['value' => self::POSITION_TOP, 'label' => __('Top')],
            ['value' => self::POSITION_BOTTOM, 'label' => __('Bottom')]
        ];
    }
}
