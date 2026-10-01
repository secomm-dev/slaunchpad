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

namespace Mageplaza\RMA\Block\Adminhtml\Status\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Config\Model\Config\Source\Yesno;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Mageplaza\RMA\Model\Config\Source\RMAStatus\Action;
use Mageplaza\RMA\Model\Status;

/**
 * Class Status
 * @package Mageplaza\RMA\Block\Adminhtml\Status\Edit\Tab
 */
class General extends Generic implements TabInterface
{
    /**
     * @var Yesno
     */
    protected $_yesNo;

    /**
     * @var Action
     */
    protected $_statusAction;

    /**
     * General constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Yesno $yesNo
     * @param Action $statusAction
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Yesno $yesNo,
        Action $statusAction,
        array $data = []
    ) {
        $this->_yesNo = $yesNo;
        $this->_statusAction = $statusAction;

        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @inheritdoc
     * @return Generic
     * @throws LocalizedException
     */
    protected function _prepareForm()
    {
        /** @var Status $status */
        $status = $this->_coreRegistry->registry('mageplaza_rma_status');

        /** @var Form $form */
        $form = $this->_formFactory->create();

        $form->setHtmlIdPrefix('status_');
        $form->setFieldNameSuffix('status');

        $fieldset = $form->addFieldset('base_fieldset', ['class' => 'fieldset-wide']);

        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => __('Status Name'),
            'title' => __('Status Name'),
            'required' => true
        ]);

        $fieldset->addField('is_active', 'select', [
            'name' => 'is_active',
            'label' => __('Active'),
            'title' => __('Active'),
            'values' => $this->_yesNo->toOptionArray()
        ]);
        if (!$status->hasData('is_active')) {
            $status->setIsActive(1);
        }

        $fieldset->addField('description', 'textarea', [
            'name' => 'description',
            'label' => __('Description'),
            'title' => __('Description')
        ]);

        $fieldset->addField('allow_action', 'multiselect', [
            'name' => 'allow_action',
            'label' => __('Select Allowed Action'),
            'title' => __('Select Allowed Action'),
            'values' => $this->_statusAction->toOptionArray()
        ]);

        $form->addValues($status->getData());
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
        return __('General');
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
