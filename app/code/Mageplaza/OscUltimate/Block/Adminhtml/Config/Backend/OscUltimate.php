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
 * @package     Mageplaza_OscUltimate
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\OscUltimate\Block\Adminhtml\Config\Backend;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field as FormField;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;
use Mageplaza\OscUltimate\Helper\Data as OscHelper;

/**
 * Class OscUltimate
 * @package Mageplaza\EditOrder\Block\Adminhtml\Config
 */
class OscUltimate extends FormField
{
    /**
     * @var AbstractElement
     */
    protected $element;

    /**
     * @var OscHelper
     */
    protected $helper;

    /**
     * @param Context $context
     * @param OscHelper $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        OscHelper $helper,
        array $data = []
    ) {
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    /**
     * @return array|mixed
     */
    public function getConfigValue()
    {
        $value = $this->getRequest()->getParams();

        $pageLayout = null;
        if (isset($value['store']) && $value['store']) {
            $pageLayout = $this->helper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT, $value['store']);
        }
        if (isset($value['website']) && $value['website']) {
            $pageLayout = $this->helper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT, $value['website'],
                ScopeInterface::SCOPE_WEBSITE);
        }
        if (!$pageLayout) {
            $pageLayout = $this->helper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT);
        }
        if (!$pageLayout) {
            $pageLayout = $this->helper->getSystemValue();
        }
        switch ($pageLayout) {
            case '1column':
                $label = __('1 Column');
                break;
            case '2columns':
                $label = __('2 Columns');
                break;
            case '2columns-floating':
                $label = __('2 Columns With Floating Column');
                break;
            case '3columns':
                $label = __('3 Columns');
                break;
            case '3columns-colspan':
                $label = __('3 Columns With Colspan');
                break;
        }

        return $label;
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->setTemplate('Mageplaza_OscUltimate::system/config/osc-ultimate.phtml');
        parent::_construct();
    }

    /**
     * Return element html
     *
     * @param AbstractElement $element
     *
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->element = $element;

        return $this->_toHtml();
    }
}
