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

namespace Mageplaza\RMA\Block\Adminhtml\Request\Edit;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Element\Dependence;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Config\Model\Config\Structure\Element\Dependency\FieldFactory;
use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Renderer\Files;
use Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Renderer\Reply;
use Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Renderer\Textarea;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Config\Source\RMARequest\Orders as RMAOrders;
use Mageplaza\RMA\Model\Config\Source\RMARequest\Status as RMAStatus;
use Mageplaza\RMA\Model\Config\Source\RMAStatus\Action;
use Mageplaza\RMA\Model\Request;

/**
 * Class Form
 * @package Mageplaza\RMA\Block\Adminhtml\Request\Edit
 */
class Form extends Generic
{
    /**
     * @var RMAStatus
     */
    protected $_RMAStatus;

    /**
     * @var RMAOrders
     */
    protected $_RMAOrders;

    /**
     * @var HelperData
     */
    protected $_helperData;

    protected $fieldFactory;

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param RMAStatus $RMAStatus
     * @param RMAOrders $RMAOrders
     * @param HelperData $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        RMAStatus $RMAStatus,
        RMAOrders $RMAOrders,
        HelperData $helperData,
        FieldFactory $fieldFactory,
        array $data = []
    ) {
        $this->_RMAStatus = $RMAStatus;
        $this->_RMAOrders = $RMAOrders;
        $this->_helperData = $helperData;
        $this->fieldFactory = $fieldFactory;

        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @inheritdoc
     * @return Generic
     * @throws LocalizedException
     */
    protected function _prepareForm()
    {
        /** @var Request $request */
        $request = $this->_coreRegistry->registry('mageplaza_rma_request');
        $defaultStatus = $this->_helperData->getRequestConfig('default_status');
        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'edit_form',
                'action' => $this->getData('action'),
                'method' => 'post',
                'enctype' => 'multipart/form-data'
            ],
        ]);
        $form->setHtmlIdPrefix('request_');

        $fieldset = $form->addFieldset('base_fieldset', ['class' => 'fieldset-wide']);

        $fieldset->addField('request_id', 'hidden', ['name' => 'request[request_id]']);

        $rmaStatus = $this->_RMAStatus->toOptionArray();

        $statusField = $fieldset->addField('status_id', 'select', [
            'name' => 'request[status_id]',
            'label' => __('Status RMA'),
            'title' => __('Status RMA'),
            'values' => $rmaStatus
        ]);

        if (!$request->hasData('status_id')) {
            $request->setStatusId($defaultStatus);
        }

        if ($request->getId()
            && in_array(Action::SHIPPING_LABEL, $this->_helperData->getRequestStatusActions($request), true)) {
            $shippingLabelField = $fieldset->addField('shipping_label_id', 'select', [
                'name' => 'request[shipping_label_id]',
                'label' => __('Return Shipping Label'),
                'title' => __('Return Shipping Label'),
                'values' => $this->_helperData->getValidatedShippingLabels($request->getOrder())
            ]);

            $value = '';

            foreach ($rmaStatus as $item) {
                if (strpos($item['allow'], (string)Action::SHIPPING_LABEL) !== false) {
                    $value .= $item['value'] . ',';
                }
            }

            $refField = $this->fieldFactory->create(
                [
                    'fieldData' => [
                        'value' => $value,
                        'separator' => ','
                    ],
                    'fieldPrefix' => ''
                ]
            );

            $dependencies = $this->getLayout()->createBlock(Dependence::class)
                ->addFieldMap($statusField->getHtmlId(), $statusField->getName())
                ->addFieldMap($shippingLabelField->getHtmlId(), $shippingLabelField->getName())
                ->addFieldDependence($shippingLabelField->getName(), $statusField->getName(), $refField);

            // define field dependencies
            $this->setChild('form_after', $dependencies);

            if ($request->getShippingLabelId()) {
                $request->setShippingLabelId($request->getShippingLabelId());
            }
        }

        $fieldset->addField('comment', 'textarea', [
            'name' => 'request[comment]',
            'label' => __('Comment'),
            'disabled' => (bool)$request->getId(),
            'title' => __('Comment')
        ]);

        $fieldset->addField(
            'files',
            Files::class,
            [
                'name' => 'request[files]',
                'label' => 'Attach File(s)'
            ]
        );

        $orderFieldset = $form->addFieldset('order_fieldset', [
            'class' => 'fieldset-wide',
            'legend' => __('Order Information')
        ]);

        $this->renderSelectOrderInput($orderFieldset, $request);

        $orderFieldset->addField('order_id', 'hidden', ['name' => 'request[order_id]']);
        $orderFieldset->addField('customer_name', 'note', [
            'text' => '<div id="request_customer_name_value"></div>',
            'label' => __('Customer Name'),
            'title' => __('Customer Name')
        ]);

        $orderFieldset->addField('customer_email', 'note', [
            'text' => '<div id="request_customer_email_value"></div>',
            'label' => __('Email'),
            'title' => __('Email')
        ]);

        $orderFieldset->addField('store_view', 'note', [
            'text' => '<div id="request_store_view_value"></div>',
            'label' => __('Store View'),
            'title' => __('Store View')
        ]);

        $RMAFieldset = $form->addFieldset('RMA_fieldset', [
            'class' => 'fieldset-wide',
            'legend' => __('RMA Information')
        ]);

        $RMAFieldset->addField('product_information', 'note', [
            'name' => 'request[product_information]',
            'text' => '<div class="mprma_products_grid"></div>'
        ]);

        if ($request->getId()) {
            $conversationFieldset = $form->addFieldset('conversation_fieldset', [
                'class' => 'fieldset-wide',
                'legend' => __('Conversation')
            ]);

            $conversationFieldset->addField('reply', Textarea::class, [
                'name' => 'reply[content]',
                'label' => __('Reply'),
                'title' => __('Reply')
            ]);

            $conversationFieldset->addField('reply_box', Reply::class, ['label' => '']);
        }

        $form->addValues($request->getData());
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * @return string
     */
    public function getFormHtml()
    {
        $html = parent::getFormHtml();
        $html .= '<div id="mp-rma-order-form" class="mp-rma-action-form mp-rma-grid-form">
                    <div id="page:main-container" class="page-columns">
                    <div class="mprma_orders_grid"></div>
                    <div class="mp-rma-message select-message"></div></div>
                </div>';
        $html .= '<div id="mp-rma-template-container" class="mp-rma-template-container" style="display: none;">
                <div class="mp-rma-image-loader">
                    <div class="loader">
                        <img src="' . $this->getViewFileUrl('images/loader-1.gif') . '" alt="' . __('Loading') . '">
                    </div>
                </div>
                <div class="mp-rma-message template-message"></div>
                <div id="page:main-container" class="page-columns"></div>
            </div>';

        return $html;
    }

    /**
     * @param Fieldset $orderFieldset
     * @param Request $request
     */
    public function renderSelectOrderInput(&$orderFieldset, $request)
    {
        if ($request->getId()) {
            $orderFieldset->addField('order_increment_id', 'text', [
                'name' => 'request[order_increment_id]',
                'label' => __('Order Increment ID'),
                'title' => __('Order Increment ID'),
                'note' => '<div class="mp-rma-message submit-message"></div>',
                'disabled' => true
            ])->setAfterElementHtml('<a id="mp-customer-name"
            class="mp-customer-name"
            href="' . $this->getUrl('sales/order/view', ['order_id' => $request->getOrderId()])
                . '" onclick="this.target=\'blank\'">' . __('View Order') . '</a>');
        } else {
            $loadOrderHtml =
                '<button type="button" class="mp_submit_order_information"
                    onclick="mpRMAFormBefore.initOrderInformation();">
                    <span>' . __('Load') . '</span>
                </button>';
            $selectOrderHtml =
                '<button type="button" class="mp_load_orders_grid"
                        onclick="mpRMAFormBefore.loadOrdersGridPopup();">
                    <span>' . __('Select') . '</span>
                </button>';

            $orderFieldset->addField('order_increment_id', 'text', [
                'name' => 'request[order_increment_id]',
                'label' => __('Order Increment ID'),
                'title' => __('Order Increment ID'),
                'note' => '<div class="mp-rma-message submit-message"></div>',
                'after_element_html' => $loadOrderHtml . $selectOrderHtml
            ]);
        }
    }
}
