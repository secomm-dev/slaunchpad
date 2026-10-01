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

namespace Mageplaza\RMA\Block\Adminhtml\Rule\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Rule;

/**
 * Class RMA
 * @package Mageplaza\RMA\Block\Adminhtml\Rule\Edit\Tab
 */
class RMA extends Generic implements TabInterface
{
    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * General constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param HelperData $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        HelperData $helperData,
        array $data = []
    ) {
        $this->_helperData = $helperData;

        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @inheritdoc
     * @return Generic
     * @throws LocalizedException
     */
    protected function _prepareForm()
    {
        /** @var Rule $rule */
        $rule = $this->_coreRegistry->registry('mageplaza_rma_rule');

        /** @var Form $form */
        $form = $this->_formFactory->create();

        $form->setHtmlIdPrefix('rule_');
        $form->setFieldNameSuffix('rule');

        $RMAFieldset = $form->addFieldset('RMA_fieldset', [
            'class' => 'fieldset-wide',
            'legend' => __('RMA Information')
        ]);

        if (count($reasons = $this->_helperData->getReasonOptionArray())) {
            $RMAFieldset->addField('reason', 'multiselect', [
                'name' => 'reason',
                'label' => __('Reason'),
                'title' => __('Reason'),
                'values' => $reasons
            ]);
        } else {
            $RMAFieldset->addField('reason', 'note', [
                'text' => '<div class="mp-reason-empty">' . __('There are no RMA reasons.') . '</div>',
                'label' => __('Reason'),
                'title' => __('Reason')
            ]);
        }

        if (count($solutions = $this->_helperData->getSolutionOptionArray())) {
            $RMAFieldset->addField('solution', 'multiselect', [
                'name' => 'solution',
                'label' => __('Solution'),
                'title' => __('Solution'),
                'values' => $solutions
            ]);
        } else {
            $RMAFieldset->addField('solution', 'note', [
                'text' => '<div class="mp-solution-empty">' . __('There are no RMA solutions.') . '</div>',
                'label' => __('Solution'),
                'title' => __('Solution')
            ]);
        }

        if (count($fields = $this->_helperData->getAdditionalFieldOptionArray())) {
            $RMAFieldset->addField('additional_field', 'multiselect', [
                'name' => 'additional_field',
                'label' => __('Additional Information'),
                'title' => __('Additional Information'),
                'values' => $fields
            ]);
        } else {
            $RMAFieldset->addField('additional_field', 'note', [
                'text' => '<div class="mp-solution-empty">' . __('There are no RMA additional information.') . '</div>',
                'label' => __('Additional Information'),
                'title' => __('Additional Information')
            ]);
        }

        $form->addValues($rule->getData());
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
        return __('RMA Information');
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
