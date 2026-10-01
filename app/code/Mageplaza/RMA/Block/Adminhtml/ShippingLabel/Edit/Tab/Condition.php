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

namespace Mageplaza\RMA\Block\Adminhtml\ShippingLabel\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Rule\Block\Conditions as MagentoCondition;
use Mageplaza\RMA\Model\ShippingLabel;

/**
 * Class Condition
 * @package Mageplaza\RMA\Block\Adminhtml\ShippingLabel\Edit\Tab
 */
class Condition extends Generic implements TabInterface
{
    /**
     * @var Fieldset
     */
    protected $_rendererFieldset;

    /**
     * @var MagentoCondition
     */
    protected $_conditions;

    /**
     * General constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Fieldset $rendererFieldset
     * @param MagentoCondition $magentoCondition
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Fieldset $rendererFieldset,
        MagentoCondition $magentoCondition,
        array $data = []
    ) {
        $this->_rendererFieldset = $rendererFieldset;
        $this->_conditions = $magentoCondition;

        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @inheritdoc
     * @return Generic
     * @throws LocalizedException
     */
    protected function _prepareForm()
    {
        /** @var ShippingLabel $shippingLabel */
        $shippingLabel = $this->_coreRegistry->registry('mageplaza_rma_shipping_label');

        /** @var Form $form */
        $form = $this->_formFactory->create();

        $form->setHtmlIdPrefix('shipping_label_');
        $form->setFieldNameSuffix('shipping_label');

        $renderer = $this->_rendererFieldset->setTemplate(
            'Magento_CatalogRule::promo/fieldset.phtml'
        )->setNewChildUrl($this->getUrl('mprma/condition/newConditionHtml/form/rule_conditions_fieldset'))
            ->setNameInLayout('mp_extra_fee_rule_conditions_fieldset');

        $ruleFieldset = $form->addFieldset(
            'conditions_fieldset',
            ['legend' => __('Return Address Conditions (Leave empty to apply for all request)')]
        )->setRenderer($renderer);

        $ruleFieldset->addField('conditions', 'text', [
            'name' => 'conditions',
            'label' => __('Conditions'),
            'title' => __('Conditions')
        ])->setRule($shippingLabel)->setRenderer($this->_conditions);

        $form->addValues($shippingLabel->getData());
        $shippingLabel->getConditions()->setJsFormObject('rule_conditions_fieldset');
        $this->setConditionFormName($shippingLabel->getConditions(), 'rule_conditions_fieldset');
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Prepare label for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return __('Condition');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->getTabLabel();
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return false;
    }
}
