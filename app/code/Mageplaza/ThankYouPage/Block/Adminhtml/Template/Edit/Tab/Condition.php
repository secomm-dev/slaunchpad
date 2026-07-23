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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Block\Adminhtml\Template\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Rule\Block\Conditions;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use Mageplaza\ThankYouPage\Model\Template;
use Mageplaza\ThankYouPage\Model\TemplateFactory;

/**
 * Class Condition
 * @package Mageplaza\ThankYouPage\Block\Adminhtml\Template\Edit\Tab
 */
class Condition extends Generic implements TabInterface
{
    /**
     * @var Fieldset
     */
    protected $rendererFieldset;

    /**
     * @var Conditions
     */
    protected $conditions;

    /**
     * @var TemplateFactory
     */
    protected $templateFactory;

    /**
     * Condition constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Fieldset $rendererFieldset
     * @param Conditions $conditions
     * @param TemplateFactory $templateFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Fieldset $rendererFieldset,
        Conditions $conditions,
        TemplateFactory $templateFactory,
        array $data = []
    ) {
        $this->rendererFieldset = $rendererFieldset;
        $this->conditions       = $conditions;
        $this->templateFactory  = $templateFactory;

        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @return Generic
     * @throws LocalizedException
     */
    protected function _prepareForm()
    {
        $model = $this->_coreRegistry->registry('mpthankyoupage_template');
        $form  = $this->addTabToForm($model);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * @param Template $model
     * @param string $fieldsetId
     * @param string $formName
     *
     * @return Form
     * @throws LocalizedException
     */
    protected function addTabToForm(
        $model,
        $fieldsetId = 'conditions_fieldset',
        $formName = 'mpthankyoupage_template_form'
    ) {
        $templateId = $this->getRequest()->getParam('template_id');
        if (!$model) {
            $model = $this->templateFactory->create();
            $model->load($templateId);
        }
        /** @var Form $form */
        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('mpthankyoupage_');

        $newChildUrl = $this->getUrl(
            'mpthankyoupage/condition/newConditionHtml/form/' . $model->getConditionsFieldSetId($formName),
            ['form_namespace' => $formName]
        );

        $renderer = $this->rendererFieldset->setTemplate('Magento_CatalogRule::promo/fieldset.phtml')
            ->setNewChildUrl($newChildUrl)
            ->setFieldSetId($model->getConditionsFieldSetId($formName))
            ->setNameInLayout('mp_thanks_you_page_rule_conditions_fieldset');

        $fieldset = $form->addFieldset($fieldsetId, [
            'legend' => __('Apply the rule only if the following conditions are met (leave blank for all products).')
        ])->setRenderer($renderer);

        $fieldset->addField('conditions', 'text', [
            'name'           => 'conditions',
            'label'          => __('Conditions'),
            'title'          => __('Conditions'),
            'required'       => true,
            'data-form-part' => $formName
        ])->setRule($model)
            ->setRenderer($this->conditions);
        $form->setValues($model->getData());
        $model->getConditions()->setJsFormObject($model->getConditionsFieldSetId($formName));
        $this->setConditionFormName($model->getConditions(), $formName);

        return $form;
    }

    /**
     * Prepare content for tab
     *
     * @return Phrase
     * @codeCoverageIgnore
     */
    public function getTabLabel()
    {
        return __('Conditions');
    }

    /**
     * Prepare title for tab
     *
     * @return Phrase
     * @codeCoverageIgnore
     */
    public function getTabTitle()
    {
        return $this->getTabLabel();
    }

    /**
     * Returns status flag about this tab can be show or not
     *
     * @return bool
     * @codeCoverageIgnore
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Returns status flag about this tab hidden or not
     *
     * @return bool
     * @codeCoverageIgnore
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Tab class getter
     *
     * @return string
     * @codeCoverageIgnore
     */
    public function getTabClass()
    {
        return null;
    }

    /**
     * Return URL link to Tab content
     *
     * @return string
     * @codeCoverageIgnore
     */
    public function getTabUrl()
    {
        return null;
    }

    /**
     * Tab should be loaded trough Ajax call
     *
     * @return bool
     * @codeCoverageIgnore
     */
    public function isAjaxLoaded()
    {
        return false;
    }
}
