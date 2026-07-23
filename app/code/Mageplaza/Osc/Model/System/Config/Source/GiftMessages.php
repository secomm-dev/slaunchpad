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
 * Class Giftwrap
 *
 */
class GiftMessages implements ArrayInterface
{
    const ON_ORDER = 'order';
    const ON_ITEM = 'item';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return  [
            ['value' => self::ON_ORDER, 'label' => __('On Order')],
            ['value' => self::ON_ITEM, 'label' => __('On Item')]
        ];
    }
}
