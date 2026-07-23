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

namespace Mageplaza\ExtraFee\Plugin\Block\Adminhtml;

use Closure;
use Magento\Sales\Block\Adminhtml\Order\Create\Data;

/**
 * Class OrderCreateData
 * @package Mageplaza\ExtraFee\Plugin\Block\Adminhtml
 */
class OrderCreateData
{
    /**
     * @param Data $subject
     * @param Closure $proceed
     * @param string $alias
     *
     * @return string
     */
    public function aroundGetChildHtml(Data $subject, Closure $proceed, $alias = '')
    {
        $result = $proceed($alias);

        if ($alias === 'gift_options') {
            $result .= $subject->getChildHtml('order_create_extra_fee_form');
        }

        return $result;
    }
}
