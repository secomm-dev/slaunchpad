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

use Magento\Backend\Block\Store\Switcher\Form\Renderer\Fieldset\Element;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Config\Model\Config\Source\Enabledisable;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Store\Model\System\Store as SystemStore;
use Mageplaza\Core\Block\Adminhtml\Renderer\Image as ImageRenderer;
use Mageplaza\RMA\Helper\Image;
use Mageplaza\RMA\Model\Config\Source\RMAShippingLabel\BarcodeType;
use Mageplaza\RMA\Model\Config\Source\RMAShippingLabel\Information;
use Mageplaza\RMA\Model\ShippingLabel;

/**
 * Class General
 * @package Mageplaza\RMA\Block\Adminhtml\ShippingLabel\Edit\Tab
 */
class General extends Generic implements TabInterface
{
    /**
     * @var SystemStore
     */
    protected $_systemStore;

    /**
     * @var Enabledisable
     */
    protected $_enableDisable;

    /**
     * @var Image
     */
    protected $_helperImage;

    /**
     * @var BarcodeType
     */
    protected $_barcodeType;

    /**
     * @var Information
     */
    protected $_information;

    /**
     * General constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param SystemStore $systemStore
     * @param Enabledisable $enableDisable
     * @param Image $helperImage
     * @param BarcodeType $barcodeType
     * @param Information $information
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        SystemStore $systemStore,
        Enabledisable $enableDisable,
        Image $helperImage,
        BarcodeType $barcodeType,
        Information $information,
        array $data = []
    ) {
        $this->_systemStore = $systemStore;
        $this->_enableDisable = $enableDisable;
        $this->_helperImage = $helperImage;
        $this->_barcodeType = $barcodeType;
        $this->_information = $information;

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

        $fieldset = $form->addFieldset('base_fieldset', [
            'class' => 'fieldset-wide',
            'legend' => __('General Information')
        ]);

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
            'values' => $this->_enableDisable->toOptionArray()
        ]);
        if (!$shippingLabel->hasData('status')) {
            $shippingLabel->setStatus(1);
        }

        $fieldset->addField('description', 'textarea', [
            'name' => 'description',
            'label' => __('Description'),
            'title' => __('Description')
        ]);

        $fieldset->addField('return_address', 'textarea', [
            'name' => 'return_address',
            'label' => __('Return Shipping Address'),
            'title' => __('Return Shipping Address'),
            'required' => true
        ]);

        if (!$this->_storeManager->isSingleStoreMode()) {
            /** @var RendererInterface $rendererBlock */
            $rendererBlock = $this->getLayout()->createBlock(Element::class);
            $fieldset->addField('store_id', 'multiselect', [
                'name' => 'store_id',
                'label' => __('Store View(s)'),
                'title' => __('Store View(s)'),
                'values' => $this->_systemStore->getStoreValuesForForm(false, true)
            ])->setRenderer($rendererBlock);

            if (!$shippingLabel->hasData('store_id')) {
                $shippingLabel->setStoreId(0);
            }
        } else {
            $fieldset->addField('store_id', 'hidden', [
                'name' => 'store_id',
                'value' => $this->_storeManager->getStore()->getId()
            ]);
        }

        $fieldset->addField('image', ImageRenderer::class, [
            'name' => 'image',
            'label' => __('Image'),
            'title' => __('Image'),
            'path' => $this->_helperImage->getBaseMediaPath(Image::TEMPLATE_MEDIA_TYPE_SHIPPING_LABEL)
        ]);

        $fieldset->addField('barcode', 'select', [
            'name' => 'barcode',
            'label' => __('Barcode Value'),
            'title' => __('Barcode Value'),
            'values' => $this->_barcodeType->toOptionArray()
        ]);
        if (!$shippingLabel->hasData('barcode')) {
            $shippingLabel->setBarcode(BarcodeType::ORDER_INCREMENT_ID);
        }

        $fieldset->addField('information', 'multiselect', [
            'name' => 'information',
            'label' => __('Information'),
            'title' => __('Information'),
            'values' => $this->_information->toOptionArray()
        ]);
        if (!$shippingLabel->hasData('information')) {
            $shippingLabel->setInformation(implode(',', array_keys($this->_information->toArray())));
        }

        $fieldset->addField('priority', 'text', [
            'name' => 'priority',
            'label' => __('Priority'),
            'title' => __('Priority'),
            'class' => 'validate-digits-range',
            'value' => '0'
        ]);

        $fieldset->addField('show_default_template', 'note', [
            'text' =>
                '<button type="button" id="mp-shipping-label-template"
                    onclick="mpRMAFormBefore.showShippingLabelTemplate()">
                    <span>' . __('Show Default Template') . '</span>
                </button>
                <div class="mp-review-shipping-container">
                    <img src="' . $this->getViewFileUrl('Mageplaza_RMA::media/shipping-label/default-template.png') . '"
                        alt="' . __('Default Template') . '">
                </div>'
        ]);

        $form->addValues($shippingLabel->getData());
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
