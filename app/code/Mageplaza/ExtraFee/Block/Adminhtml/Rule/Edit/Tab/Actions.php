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
use Magento\Backend\Block\Widget\Form\Element\Dependence;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Config\Model\Config\Source\Yesno;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Tax\Model\TaxClass\Source\Product as TaxProduct;
use Mageplaza\ExtraFee\Block\Adminhtml\Rule\Edit\Tab\Renderer\Options;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\Config\Source\ApplyFor;
use Mageplaza\ExtraFee\Model\Config\Source\ApplyType;
use Mageplaza\ExtraFee\Model\Config\Source\CalculateOptions;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayType;
use Mageplaza\ExtraFee\Model\Config\Source\FeeType;
use Mageplaza\ExtraFee\Model\Config\Source\FeeTypeItem;
use Mageplaza\ExtraFee\Model\Rule;

/**
 * Class Actions
 * @package Mageplaza\ExtraFee\Block\Adminhtml\Rule\Edit\Tab
 */
class Actions extends Generic implements TabInterface
{
    /**
     * @var Yesno
     */
    protected $yesno;

    /**
     * @var TaxProduct
     */
    protected $taxProduct;

    /**
     * @var Options
     */
    protected $options;

    /**
     * @var ApplyType
     */
    protected $applyType;

    /**
     * @var DisplayType
     */
    protected $displayType;

    /**
     * @var DisplayArea
     */
    protected $displayArea;

    /**
     * @var FeeType
     */
    protected $feeType;

    /**
     * @var FeeTypeItem
     */
    protected $feeTypeItem;

    /**
     * @var ApplyFor
     */
    protected $applyFor;

    /**
     * @var CalculateOptions
     */
    protected $calculateOptions;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * Actions constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Yesno $yesno
     * @param TaxProduct $taxProduct
     * @param Options $options
     * @param ApplyType $applyType
     * @param DisplayType $displayType
     * @param DisplayArea $displayArea
     * @param FeeType $feeType
     * @param FeeTypeItem $feeTypeItem
     * @param ApplyFor $applyFor
     * @param CalculateOptions $calculateOptions
     * @param Data $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Yesno $yesno,
        TaxProduct $taxProduct,
        Options $options,
        ApplyType $applyType,
        DisplayType $displayType,
        DisplayArea $displayArea,
        FeeType $feeType,
        FeeTypeItem $feeTypeItem,
        ApplyFor $applyFor,
        CalculateOptions $calculateOptions,
        Data $helperData,
        array $data = []
    ) {
        $this->yesno            = $yesno;
        $this->taxProduct       = $taxProduct;
        $this->options          = $options;
        $this->applyType        = $applyType;
        $this->displayType      = $displayType;
        $this->displayArea      = $displayArea;
        $this->feeType          = $feeType;
        $this->feeTypeItem      = $feeTypeItem;
        $this->applyFor         = $applyFor;
        $this->calculateOptions = $calculateOptions;
        $this->helperData       = $helperData;

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
        return __('Actions');
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
     * @inheritdoc
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

        $actionsFieldset = $form->addFieldset('actions_fieldset', [
            'legend' => __('Actions'),
            'class'  => 'fieldset-wide'
        ]);
        $applyType       = $actionsFieldset->addField('apply_type', 'select', [
            'name'   => 'apply_type',
            'label'  => __('Apply Type'),
            'title'  => __('Apply Type'),
            'values' => $this->applyType->toOptionArray()
        ]);
        $applyFor        = $actionsFieldset->addField('apply_for', 'select', [
            'name'   => 'apply_for',
            'label'  => __('Apply For'),
            'title'  => __('Apply For'),
            'values' => $this->applyFor->toOptionArray()
        ]);

        $newActionUrl = $this->getUrl(
            'mpextrafee/condition/NewActionHtml/form/rule_actions',
            ['form_namespace' => 'mpextrafee_form']
        );

        $productCondition = $actionsFieldset->addField(
            'actions',
            \Mageplaza\ExtraFee\Block\Adminhtml\Rule\Edit\Tab\Renderer\ProductConditions::class,
            [
                'name'           => 'actions',
                'label'          => '',
                'title'          => '',
                'data-form-part' => 'rule_actions'
            ]
        )->setNewChildUrl($newActionUrl);
        $feeType          = $actionsFieldset->addField('fee_type', 'select', [
            'name'   => 'fee_type',
            'label'  => __('Fee Type'),
            'title'  => __('Fee Type'),
            'values' => $this->feeType->toOptionArray()
        ]);
        $feeTypeItem      = $actionsFieldset->addField('fee_type_item', 'select', [
            'name'   => 'fee_type_item',
            'label'  => __('Fee Type'),
            'title'  => __('Fee Type'),
            'values' => $this->feeTypeItem->toOptionArray()
        ]);
        $amount           = $actionsFieldset->addField('amount', 'text', [
            'name'     => 'amount',
            'label'    => __('Fee Amount'),
            'title'    => __('Fee Amount'),
            'class'    => 'validate-not-negative-number',
            'required' => true
        ]);
        $displayArea      = $actionsFieldset->addField('area', 'select', [
            'name'   => 'area',
            'label'  => __('Display Area'),
            'title'  => __('Display Area'),
            'values' => $this->displayArea->toOptionArray()
        ]);
        $displayType      = $actionsFieldset->addField('display_type', 'select', [
            'name'   => 'display_type',
            'label'  => __('Display Type'),
            'title'  => __('Display Type'),
            'values' => $this->displayType->toOptionArray()
        ]);
        $isRequired       = $actionsFieldset->addField('is_required', 'select', [
            'name'   => 'is_required',
            'label'  => __('Is Required'),
            'title'  => __('Is Required'),
            'values' => $this->yesno->toOptionArray()
        ]);
        $actionsFieldset->addField('fee_tax', 'select', [
            'name'   => 'fee_tax',
            'label'  => __('Fee Tax'),
            'title'  => __('Fee Tax'),
            'values' => $this->taxProduct->toOptionArray()
        ]);
        $actionsFieldset->addField('sort_order', 'text', [
            'name'  => 'sort_order',
            'label' => __('Cart Sort Order'),
            'title' => __('Cart Sort Order'),
        ]);
        $actionsFieldset->addField('refundable', 'select', [
            'name'   => 'refundable',
            'label'  => __('Refundable'),
            'title'  => __('Refundable'),
            'values' => $this->yesno->toOptionArray()
        ]);
        $actionsFieldset->addField('stop_further_processing', 'select', [
            'name'   => 'stop_further_processing',
            'label'  => __('Stop further processing'),
            'title'  => __('Stop further processing'),
            'values' => $this->yesno->toOptionArray()
        ]);

        $adminChecked = $this->checkConfig($rule->getId(), $rule->getFeeInclude());
        if ($adminChecked) {
            $rule->setFeeInclude($this->helperData->getConfigGeneral('calculate_options'));
        }
        $actionsFieldset->addField('fee_include', 'multiselect', [
            'name'               => 'fee_include',
            'label'              => __('Calculate Total Includes'),
            'title'              => __('Calculate Total Includes'),
            'note'               => __('Apply for percentage fee type only'),
            'values'             => $this->calculateOptions->toOptionArray(),
            'after_element_html' => $this->getUseConfigHtml('fee_include', $adminChecked),
            'style'              => 'height: 150px'
        ]);

        $actionsFieldset->addField('options', 'text', [
            'name' => 'options',
        ])->setRenderer($this->options);

        $this->setChild('form_after', $this->getLayout()->createBlock(Dependence::class)
            ->addFieldMap($applyType->getHtmlId(), $applyType->getName())
            ->addFieldMap($feeType->getHtmlId(), $feeType->getName())
            ->addFieldMap($feeTypeItem->getHtmlId(), $feeTypeItem->getName())
            ->addFieldMap($amount->getHtmlId(), $amount->getName())
            ->addFieldMap($displayArea->getHtmlId(), $displayArea->getName())
            ->addFieldMap($displayType->getHtmlId(), $displayType->getName())
            ->addFieldMap($isRequired->getHtmlId(), $isRequired->getName())
            ->addFieldMap($applyFor->getHtmlId(), $applyFor->getName())
            ->addFieldMap($productCondition->getHtmlId(), $productCondition->getName())
            ->addFieldDependence($productCondition->getName(), $applyFor->getName(), ApplyFor::ITEM)
            ->addFieldDependence($feeType->getName(), $applyType->getName(), ApplyType::AUTOMATIC)
            ->addFieldDependence($feeType->getName(), $applyFor->getName(), ApplyFor::CART)
            ->addFieldDependence($feeTypeItem->getName(), $applyType->getName(), ApplyType::AUTOMATIC)
            ->addFieldDependence($feeTypeItem->getName(), $applyFor->getName(), ApplyFor::ITEM)
            ->addFieldDependence($amount->getName(), $applyType->getName(), ApplyType::AUTOMATIC)
            ->addFieldDependence($displayArea->getName(), $applyType->getName(), ApplyType::MANUAL)
            ->addFieldDependence($displayType->getName(), $applyType->getName(), ApplyType::MANUAL)
            ->addFieldDependence($isRequired->getName(), $applyType->getName(), ApplyType::MANUAL));

        $form->addValues($rule->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * @param $id
     * @param $emails
     *
     * @return string
     */
    protected function checkConfig($id, $value)
    {
        $checked = !$id || $value === 'mp-use-config' ? 'checked' : '';

        return $checked;
    }

    /**
     * @param $elemId
     * @param $checked
     * @param string $value
     *
     * @return string
     */
    protected function getUseConfigHtml($elemId, $checked, $value = 'mp-use-config')
    {
        return <<<HTML
            <label style="position: absolute;margin-left: 5px;"
                   class="mp-use-config-label add-after" for="{$elemId}-use-config">
                <input style="margin-top: 0 !important;"
                       type="checkbox"
                       class="mp-use-config"
                       id="{$elemId}-use-config"
                       name="rule[{$elemId}][]"
                       value="{$value}" {$checked}>
                Use config setting
            </label>
            {$this->configScript()}
            <style>
                .admin__fieldset > .admin__field > .admin__field-control input[type="checkbox"] {
                margin-top: 0;
                }
            </style>
            HTML;
    }

    /**
     * @return string
     */
    protected function configScript()
    {
        return <<<HTML
            <script type="text/x-magento-init">
                {
                    "#rule_tabs_actions_content": {
                        "Mageplaza_ExtraFee/js/form/config-checked": {}
                    }
                }
            </script>
            HTML;
    }
}
