<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
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

namespace Mageplaza\AbandonedCart\Block\Adminhtml\Customer\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use Mageplaza\AbandonedCart\Helper\Data;

/**
 * Class AbandonedCartBlacklist
 * @package Mageplaza\AbandonedCart\Block\Adminhtml\Customer\Edit\Tab
 */
class AbandonedCartBlacklist extends Generic implements TabInterface
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * AbandonedCartBlacklist constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Data $helperData
     * @param CustomerFactory $customerFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Data $helperData,
        CustomerFactory $customerFactory,
        array $data = []
    ) {
        $this->helperData      = $helperData;
        $this->customerFactory = $customerFactory;

        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @return string|null
     */
    public function getCustomerId()
    {
        return $this->_coreRegistry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
    }

    /**
     * @inheritdoc
     */
    protected function _prepareForm()
    {
        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('mpabandonedcart_');

        $fieldset       = $form->addFieldset('base_fieldset', ['legend' => __('Abandoned Cart Email Blacklist')]);
        $customer       = $this->customerFactory->create()->load($this->getCustomerId());
        $mpAceBlacklist = $customer->getData('mp_ace_blacklist');

        $fieldset->addField('mp_ace_blacklist', 'select', [
            'label'          => __('Abandoned Cart Email Blacklist'),
            'name'           => 'customer[mp_ace_blacklist]',
            'id'             => 'mp_ace_blacklist',
            'value'          => $mpAceBlacklist,
            'values'         => [__('Inactive'), __('Active')],
            'data-form-part' => $this->getData('target_form')
        ]);

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * @return Phrase|string
     */
    public function getTabLabel()
    {
        return __('Abandoned Cart Email Blacklist');
    }

    /**
     * @return Phrase|string
     */
    public function getTabTitle()
    {
        return __('Abandoned Cart Email Blacklist');
    }

    /**
     * @return bool
     */
    public function canShowTab()
    {
        if ($this->getCustomerId() && $this->helperData->isEnabled()) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isHidden()
    {
        if ($this->getCustomerId() || $this->helperData->isEnabled()) {
            return false;
        }

        return true;
    }

    /**
     * Tab class getter
     *
     * @return string
     */
    public function getTabClass()
    {
        return '';
    }

    /**
     * Return URL link to Tab content
     *
     * @return string
     */
    public function getTabUrl()
    {
        return '';
    }

    /**
     * Tab should be loaded trough Ajax call
     *
     * @return bool
     */
    public function isAjaxLoaded()
    {
        return false;
    }
}
