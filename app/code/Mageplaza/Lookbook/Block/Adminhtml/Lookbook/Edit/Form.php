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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Block\Adminhtml\Lookbook\Edit;

use Magento\Backend\Block\Store\Switcher\Form\Renderer\Fieldset\Element;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Config\Model\Config\Source\Enabledisable;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;
use Magento\Store\Model\System\Store;
use Mageplaza\Lookbook\Block\Adminhtml\Lookbook\Edit\Render\Editor;
use Mageplaza\Lookbook\Block\Adminhtml\Lookbook\Edit\Render\Image;
use Mageplaza\Lookbook\Helper\Data;
use Mageplaza\Lookbook\Helper\Media;
use Mageplaza\Lookbook\Model\Config\Source\MarkerType;

/**
 * Class Form
 * @package Mageplaza\Lookbook\Block\Adminhtml\Lookbook\Edit
 */
class Form extends Generic
{
    /**
     * @var Data
     */
    protected $helperData;
    /**
     * @var Store
     */
    protected $systemStore;
    /**
     * @var Enabledisable
     */
    protected $statusOptions;
    /**
     * @var MarkerType
     */
    protected $markerType;
    /**
     * @var Media
     */
    protected $imageHelper;

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Data $helperData
     * @param Store $systemStore
     * @param Enabledisable $statusOptions
     * @param MarkerType $markerType
     * @param Media $imageHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Data $helperData,
        Store $systemStore,
        Enabledisable $statusOptions,
        MarkerType $markerType,
        Media $imageHelper,
        array $data = []
    ) {
        $this->helperData = $helperData;
        $this->systemStore = $systemStore;
        $this->statusOptions = $statusOptions;
        $this->markerType = $markerType;
        $this->imageHelper = $imageHelper;

        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @return Form
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _prepareForm()
    {
        $model = $this->_coreRegistry->registry('mplookbook_lookbook');
        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create(
            [
                'data' => [
                    'id' => 'edit_form',
                    'action' => $this->getUrl('*/*/save', [
                        'lookbook_id' => $this->getRequest()->getParam('lookbook_id')
                    ]),
                    'method' => 'post',
                    'enctype' => 'multipart/form-data'
                ],
            ]
        );
        $form->setUseContainer(true);
        $form->setHtmlIdPrefix('mplookbook_');

        $fieldset = $form->addFieldset(
            'base_fieldset',
            [
                'legend' => __('Lookbook Information'),
                'class' => 'fieldset-wide'
            ]
        );
        if ($model->getId()) {
            $fieldset->addField('lookbook_id', 'hidden', ['name' => 'lookbook_id']);
        }
        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => __('Name'),
            'title' => __('Name'),
            'required' => true
        ]);

        $fieldset->addField('status', 'select', [
            'name' => 'status',
            'label' => __('Status'),
            'title' => __('Status'),
            'values' => $this->statusOptions->toOptionArray()
        ]);
        if (!$model->getId()) {
            $model->setData('status', 1);
        }

        if ($this->_storeManager->isSingleStoreMode()) {
            $fieldset->addField('store_ids', 'hidden', [
                'name' => 'store_ids',
                'value' => $this->_storeManager->getStore()->getId()
            ]);
            $model->setStoreIds(0);
        } else {
            /** @var RendererInterface $rendererBlock */
            $rendererBlock = $this->getLayout()->createBlock(Element::class);
            $fieldset->addField('store_ids', 'multiselect', [
                'name' => 'store_ids',
                'label' => __('Store Views'),
                'title' => __('Store Views'),
                'required' => true,
                'values' => $this->systemStore->getStoreValuesForForm(false, true)
            ])->setRenderer($rendererBlock);
            if (!$model->hasData('store_ids') || !$model->getId()) {
                $model->setStoreIds(0);
            }
        }

        $fieldset->addField('marker_type', 'select', [
            'name' => 'marker_type',
            'label' => __('Marker Type'),
            'title' => __('Marker Type'),
            'values' => $this->markerType->toOptionArray()
        ]);

        $fieldset->addField('width', 'text', [
            'name' => 'width',
            'label' => __('Image Width'),
            'title' => __('Image Width'),
            'class' => 'validate-greater-than-zero validate-digits',
            'note' => __('If empty, width is auto.')
        ]);

        $fieldset->addField('height', 'text', [
            'name' => 'height',
            'label' => __('Image Height'),
            'title' => __('Image Height'),
            'class' => 'validate-greater-than-zero validate-digits',
            'note' => __('If empty, height is auto.')
        ]);

        $image = $fieldset->addField('image', Image::class, [
            'label' => __('Image'),
            'title' => __('Image'),
            'name' => 'image',
            'required' => true,
            'path' => $this->imageHelper->getBaseMediaPath(),
            'note' => __('Allowed file types: jpg, gif, png.')
        ]);

        $editor = $this->getLayout()->createBlock(
            Editor::class
        )->setTemplate('render/lookbook.phtml');

        $editor->setData('lookbook', $model);
        $image->setAfterElementHtml(
            $editor->toHtml()
        );

        $form->addValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
