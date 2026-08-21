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

use Magento\Backend\Block\Store\Switcher\Form\Renderer\Fieldset\Element;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Config\Model\Config\Source\Enabledisable;
use Magento\Config\Model\Config\Source\Yesno;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroup;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Store\Model\System\Store;
use Mageplaza\ExtraFee\Model\Rule;
use Mageplaza\ExtraFee\Model\Config\Source\Days;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class General
 * @package Mageplaza\ExtraFee\Block\Adminhtml\Rule\Edit\Tab
 */
class General extends Generic implements TabInterface
{
    /**
     * @var Enabledisable
     */
    protected $enabledisable;

    /**
     * @var Store
     */
    protected $systemStore;

    /**
     * @var CustomerGroup
     */
    protected $customerGroup;

    /**
     * @var Yesno
     */
    protected $yesno;

    /**
     * @var Days
     */
    protected $days;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * General constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Enabledisable $enableDisable
     * @param Store $systemStore
     * @param Yesno $yesno
     * @param CustomerGroup $customerGroup
     * @param Days $days
     * @param Data $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Enabledisable $enableDisable,
        Store $systemStore,
        Yesno $yesno,
        CustomerGroup $customerGroup,
        Days $days,
        Data $helperData,
        array $data = []
    ) {
        $this->enabledisable = $enableDisable;
        $this->systemStore   = $systemStore;
        $this->customerGroup = $customerGroup;
        $this->yesno         = $yesno;
        $this->days          = $days;
        $this->helperData    = $helperData;

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
        return __('General');
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

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => __('General'),
            'class'  => 'fieldset-wide'
        ]);

        $fieldset->addField('name', 'text', [
            'name'     => 'name',
            'label'    => __('Name'),
            'title'    => __('Name'),
            'required' => true
        ]);

        $fieldset->addField('status', 'select', [
            'name'   => 'status',
            'label'  => __('Status'),
            'title'  => __('Status'),
            'values' => $this->enabledisable->toOptionArray()
        ]);

        $fieldset->addField('description', 'textarea', [
            'label' => __('Description'),
            'title' => __('Description'),
            'name'  => 'description',
            'cols'  => 20,
            'rows'  => 5,
            'value' => '',
            'wrap'  => 'soft',
            'note'  => __('Only apply the extra fee description for the manual type.')
        ]);

        if ($this->_storeManager->isSingleStoreMode()) {
            $fieldset->addField('store_ids', 'hidden', [
                'name'  => 'store_ids[]',
                'value' => $this->_storeManager->getStore()->getId()
            ]);
        } else {
            /** @var RendererInterface $rendererBlock */
            $rendererBlock = $this->getLayout()->createBlock(Element::class);
            $fieldset->addField('store_ids', 'multiselect', [
                'name'     => 'store_ids',
                'label'    => __('Store Views'),
                'title'    => __('Store Views'),
                'required' => true,
                'values'   => $this->systemStore->getStoreValuesForForm(false, true),
                'value'    => 0
            ])->setRenderer($rendererBlock);
        }
        $fieldset->addField('customer_groups', 'multiselect', [
            'name'     => 'customer_groups',
            'label'    => __('Customer Groups'),
            'title'    => __('Customer Groups'),
            'values'   => $this->customerGroup->toOptionArray(),
            'required' => true
        ])->setSize(5);

        $allowNote = $fieldset->addField('allow_note_message', 'select', [
            'name'   => 'allow_note_message',
            'label'  => __('Allow Customer Notes/Messages of Extra Fee'),
            'title'  => __('Allow Customer Notes/Messages of Extra Fee'),
            'values' => $this->yesno->toOptionArray(),
            'value'  => 1,
            'note'   => __('If yes, will display the message section with each extra fee in the frontend so that the customer can leave a note or message. Only apply to the Manual Type.')
        ]);

        $messageTitle = $fieldset->addField('message_title', 'text', [
            'name'  => 'message_title',
            'label' => __('Message Title'),
            'title' => __('Message Title'),
            'value' => __('Leave a message for the extra fee.')
        ]);

        $fieldset->addField('from_date', 'date', [
            'name'        => 'from_date',
            'label'       => __('From Date'),
            'title'       => __('From Date'),
            'date_format' => 'yyyy-MM-dd'
        ]);

        $fieldset->addField('to_date', 'date', [
            'name'        => 'to_date',
            'label'       => __('To Date'),
            'title'       => __('To Date'),
            'date_format' => 'yyyy-MM-dd'
        ]);

        $adminChecked = $this->checkConfig($rule->getId(), $rule->getAvailableDays());
        if ($adminChecked) {
            $rule->setAvailableDays($this->helperData->getConfigGeneral('available_days'));
        }
        $fieldset->addField('available_days', 'multiselect', [
            'name'     => 'available_days',
            'label'    => __('Available Days'),
            'title'    => __('Available Days'),
            'values'   => $this->days->toOptionArray(),
            'required' => true,
            'after_element_html' => $this->getUseConfigHtml('available_days', $adminChecked),
        ])->setSize(5);

        $fieldset->addField('priority', 'text', [
            'name'  => 'priority',
            'label' => __('Priority'),
            'title' => __('Priority'),
            'class' => 'validate-digits'
        ]);

        $form->addValues($rule->getData());
        $this->setForm($form);

        $blockDependence = $this->getLayout()->createBlock(\Magento\Backend\Block\Widget\Form\Element\Dependence::class);
        $blockDependence->addFieldMap($allowNote->getHtmlId(), $allowNote->getName())
            ->addFieldMap($messageTitle->getHtmlId(), $messageTitle->getName())
            ->addFieldDependence($messageTitle->getName(), $allowNote->getName(), 1);

        $this->setChild('form_after', $blockDependence);

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
        $checked = !$id || $value === 'mp-use-config' || $value === null ? 'checked' : '';

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
            <label style="position: absolute; margin-left: 10px;"
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
                #rule_available_days {
                    margin-bottom: 10px;
                }
                #rule_available_days-error {
                    position: absolute;
                    bottom: -23px;
                    width: 60%;
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
                    "#rule_tabs_rule_content": {
                        "Mageplaza_ExtraFee/js/form/config-checked": {}
                    }
                }
            </script>
            HTML;
    }
}
