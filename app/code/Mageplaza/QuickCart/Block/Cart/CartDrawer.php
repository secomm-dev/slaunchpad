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
 * @package     Mageplaza_QuickCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\QuickCart\Block\Cart;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\QuickCart\Helper\Data;

/**
 * Class CartDrawer
 * @package Mageplaza\QuickCart\Block\Cart
 */
class CartDrawer extends Template
{
    /**
     * @var Data
     */
    protected Data $helper;

    /**
     * @param Context $context
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->helper = $helper;
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        if ($this->helper->isEnabled()) {
            $this->_template = 'Mageplaza_QuickCart::hyva/cart/cart-drawer.phtml';
        } else {
            $this->_template = 'Magento_Theme::html/cart/cart-drawer.phtml';
        }

        return parent::getTemplate();
    }
}
