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
 * @copyright   Copyright (c) Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Block\Adminhtml\System;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Rule\Block\Conditions;
use Mageplaza\RMA\Model\Order\RuleFactory;

/**
 * Class Reason
 * @package Mageplaza\RMA\Block\Adminhtml\System
 */
class Condition extends AbstractFieldArray
{
    /**
     * @var string
     */
    protected $_template = 'Mageplaza_RMA::system/config/conditions.phtml';

    /**
     * @var RuleFactory
     */
    protected $_ruleFactory;

    /**
     * @var FormFactory
     */
    protected $_formFactory;

    /**
     * @var Fieldset
     */
    protected $_rendererFieldset;

    /**
     * @var Conditions
     */
    protected $_conditions;

    /**
     * Condition constructor.
     *
     * @param Context $context
     * @param RuleFactory $ruleFactory
     * @param FormFactory $formFactory
     * @param Fieldset $rendererFieldset
     * @param Conditions $conditions
     * @param array $data
     */
    public function __construct(
        Context $context,
        RuleFactory $ruleFactory,
        FormFactory $formFactory,
        Fieldset $rendererFieldset,
        Conditions $conditions,
        array $data = []
    ) {
        $this->_ruleFactory = $ruleFactory;
        $this->_formFactory = $formFactory;
        $this->_rendererFieldset = $rendererFieldset;
        $this->_conditions = $conditions;

        parent::__construct($context, $data);
    }

    /**
     * {@inheritdoc}
     */
    protected function _prepareToRender()
    {
        $this->addColumn('name', ['label' => __('Name')]);
    }

    /**
     * @return mixed
     * @throws LocalizedException
     */
    public function getConditionHtml()
    {
        $rule = $this->_ruleFactory->create();
        /** @var Form $form */
        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('rule_');
        $form->setFieldNameSuffix('rule');
        $newChildUrl = $this->getUrl(
            'sales_rule/promo_quote/newConditionHtml/form/rule_conditions_fieldset',
            ['form_namespace' => 'catalog_rule_form']
        );

        $values = $this->getElement()->getValue();
        $rule->setData('conditions_serialized', $values);
        $renderer = $this->_rendererFieldset->setTemplate('Mageplaza_RMA::order/conditions.phtml')
            ->setType('page')->setNewChildUrl($newChildUrl);
        $fieldset = $form->addFieldset('conditions_fieldset', [])->setRenderer($renderer);
        $fieldset->addField('conditions', 'text', [])->setRule($rule)->setRenderer($this->_conditions);

        $rule->getConditions()->setJsFormObject('rule_conditions_fieldset');
        $this->setConditionFormName($rule->getConditions(), 'rule_conditions_fieldset');

        return $fieldset->toHtml();
    }
}
