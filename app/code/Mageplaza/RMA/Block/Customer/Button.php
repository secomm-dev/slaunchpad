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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Block\Customer;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\RMA\Helper\Data as HelperData;

/**
 * Class Request
 * @package Mageplaza\RMA\Block\Customer
 */
class Button extends Template
{
    /**
     * @var HelperData
     */
    public $helperData;

    /**
     * Button constructor.
     *
     * @param Context $context
     * @param HelperData $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        HelperData $helperData,
        array $data = []
    ) {
        $this->helperData = $helperData;

        parent::__construct($context, $data);
    }

    /**
     * Get the template based on the theme or other conditions
     *
     * @return string
     */
    protected function _prepareTemplate()
    {
        if ($this->helperData->checkHyvaTheme()) {
            return 'Mageplaza_RMA::hyva/customer/request/button.phtml';
        }

        return 'Mageplaza_RMA::customer/request/button.phtml';
    }

    /**
     * Override parent _toHtml to apply dynamic template
     *
     * @return string
     */
    protected function _toHtml()
    {
        $this->setTemplate($this->_prepareTemplate());
        return parent::_toHtml();
    }
}
