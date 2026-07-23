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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Plugin\Block\Widget\Grid\Column\Filter;

/**
 * Class ConversionFunnel
 * @package Mageplaza\AbandonedCart\Plugin
 */
class Datetime
{
    /**
     * @param \Magento\Backend\Block\Widget\Grid\Column\Filter\Datetime $subject
     * @param $result string
     * @return string
     */
    public function afterGetHtml(\Magento\Backend\Block\Widget\Grid\Column\Filter\Datetime $subject, string $result)
    {
        return str_replace('Date selector', '', $result);
    }
}
