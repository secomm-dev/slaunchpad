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

namespace Mageplaza\ExtraFee\Block\Adminhtml\Rule\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Rule\Block\Conditions as MagentoCondition;
use Mageplaza\ExtraFee\Model\Rule;

/**
 * Class Conditions
 * @package Mageplaza\ExtraFee\Block\Adminhtml\Rule\Edit\Tab
 */
class Conditions extends Generic implements TabInterface
{
    /**
     * @var MagentoCondition
     */
    protected $_conditions;

    /**
     * @var Fieldset
     */
    protected $_rendererFieldset;

    /**
     * Conditions constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param MagentoCondition $conditions
     * @param Fieldset $rendererFieldset
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        MagentoCondition $conditions,
        Fieldset $rendererFieldset,
        array $data = []
    ) {
        $this->_conditions       = $conditions;
        $this->_rendererFieldset = $rendererFieldset;

        parent::__construct($context, $registry, $formFactory, $data);
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
     * Prepare label for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return __('Conditions');
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

    /**
     * Get form Html
     *
     * @return string
     */
    public function getFormHtml()
    {
        $formHtml  = parent::getFormHtml();
        $childHtml = $this->getChildHtml();

        return $formHtml . $childHtml;
    }

    /**
     * @return Generic
     * @throws LocalizedException
     */
    protected function _prepareForm()
    {
        /** @var Rule $rule */
        $rule = $this->_coreRegistry->registry('mageplaza_extrafee_rule');

        /** @var Form $form */
        $form = $this->_formFactory->create();

        $form->setHtmlIdPrefix('rule_');
        $form->setFieldNameSuffix('rule');

        $renderer = $this->_rendererFieldset->setTemplate(
            'Magento_CatalogRule::promo/fieldset.phtml'
        )->setNewChildUrl(
            $this->getUrl('mpextrafee/condition/newConditionHtml/form/rule_conditions_fieldset')
        )->setNameInLayout('mp_extra_fee_rule_conditions_fieldset');

        $fieldset = $form->addFieldset(
            'conditions_fieldset',
            ['legend' => __('Conditions (don\'t add conditions if rule is applied to all products)'),]
        )->setRenderer($renderer);

        $fieldset->addField(
            'conditions',
            'text',
            ['name' => 'conditions', 'label' => __('Conditions'), 'title' => __('Conditions')]
        )->setRule($rule)->setRenderer($this->_conditions);

        $form->addValues($rule->getData());
        $rule->getConditions()->setJsFormObject('rule_conditions_fieldset');
        $this->setConditionFormName($rule->getConditions(), 'rule_conditions_fieldset');
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
