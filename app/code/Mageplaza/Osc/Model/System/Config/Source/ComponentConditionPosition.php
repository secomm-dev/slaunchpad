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
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Osc\Model\System\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class ComponentPosition
 *
 */
class ComponentConditionPosition implements ArrayInterface
{
    const NOT_SHOW = 0;
    const SHOW_IN_PAYMENT = 1;
    const SHOW_IN_BUTTON_PLACE_ORDER= 2;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            self::NOT_SHOW => __('No'),
            self::SHOW_IN_PAYMENT => __('Below selected payment method'),
            self::SHOW_IN_BUTTON_PLACE_ORDER => __('Above "Place Order" button')
        ];
    }
}
