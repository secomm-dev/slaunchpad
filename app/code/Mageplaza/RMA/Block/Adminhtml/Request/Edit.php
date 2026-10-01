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

namespace Mageplaza\RMA\Block\Adminhtml\Request;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Registry;
use Magento\Sales\Helper\Reorder;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Config\Source\RMARequest\Orders as RMAOrders;
use Mageplaza\RMA\Model\Config\Source\RMAStatus\Action;
use Mageplaza\RMA\Model\Request;

/**
 * Class Edit
 * @package Mageplaza\RMA\Block\Adminhtml\Request
 */
class Edit extends Container
{
    /**
     * @var Registry
     */
    public $coreRegistry;

    /**
     * @var Reorder
     */
    protected $_reorderHelper;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * @var RMAOrders
     */
    protected $_RMAOrders;

    /**
     * Edit constructor.
     *
     * @param Registry $coreRegistry
     * @param Context $context
     * @param Reorder $reorderHelper
     * @param HelperData $helperData
     * @param RMAOrders $RMAOrders
     * @param array $data
     */
    public function __construct(
        Registry   $coreRegistry,
        Context    $context,
        Reorder    $reorderHelper,
        HelperData $helperData,
        RMAOrders  $RMAOrders,
        array      $data = []
    )
    {
        $this->coreRegistry   = $coreRegistry;
        $this->_reorderHelper = $reorderHelper;
        $this->_helperData    = $helperData;
        $this->_RMAOrders     = $RMAOrders;

        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * Initialize Request edit block
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_blockGroup = 'Mageplaza_RMA';
        $this->_controller = 'adminhtml_request';

        parent::_construct();
        $this->buttonList->remove('save');
        /** @var Request $request */
        $request = $this->coreRegistry->registry('mageplaza_rma_request');
        if ($request->getId() || count($this->_RMAOrders->orderListID())) {
            $this->addButton('save', [
                'label'   => __('Save'),
                'class'   => 'save primary',
                'onclick' => 'mpRMAFormBefore.validateRmaForm(false)'
            ], 1);

            $this->buttonList->add('save-and-continue', [
                'label'   => __('Save and Continue Edit'),
                'class'   => 'save',
                'onclick' => 'mpRMAFormBefore.validateRmaForm(true)'
            ], -100);
        }
        if ($request->getId()) {
            if ($this->_isAllowedAction('Magento_Sales::creditmemo')
                && $request->getOrder()->canCreditmemo()
                && in_array(Action::CREDIT_MEMO, $this->_helperData->getRequestStatusActions($request), true)
            ) {
                $this->buttonList->add('order_creditmemo', [
                    'label'   => __('Credit Memo'),
                    'onclick' => 'setLocation(\'' . $this->getUrl('sales/order_creditmemo/start', [
                            'form_key' => $this->getFormKey(),
                            'order_id' => $request->getOrder()->getId()
                        ]) . '\')'
                ], -1);
            }

            if ($this->_isAllowedAction('Magento_Sales::reorder')
                && in_array(Action::REORDER, $this->_helperData->getRequestStatusActions($request), true)
                && $request->getOrder()->canReorderIgnoreSalable()
                && $this->_reorderHelper->isAllowed($request->getOrder()->getStore())
            ) {
                $this->buttonList->add('order_reorder', [
                    'label'   => __('Reorder'),
                    'onclick' => 'setLocation(\'' . $this->getUrl('sales/order_create/reorder', [
                            'form_key' => $this->getFormKey(),
                            'order_id' => $request->getOrder()->getId()
                        ]) . '\')'
                ], -1);
            }
        }

        if (!$this->_isAllowedAction('Mageplaza_RMA::request_delete')) {
            $this->buttonList->remove('delete');
        }
    }

    /**
     * Retrieve text for header element depending on loaded Post
     *
     * @return string
     */
    public function getHeaderText()
    {
        /** @var Request $request */
        $request = $this->coreRegistry->registry('mageplaza_rma_request');
        if ($request->getId()) {
            return __('Edit Request');
        }

        return __('New Request');
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        /** @var Request $request */
        $request = $this->coreRegistry->registry('mageplaza_rma_request');
        if ($requestId = $request->getId()) {
            return $this->getUrl('*/*/save', ['id' => $requestId]);
        }

        return parent::getFormActionUrl();
    }

    /**
     * Check permission for passed action
     *
     * @param string $resourceId
     *
     * @return bool
     */
    protected function _isAllowedAction($resourceId)
    {
        return $this->_authorization->isAllowed($resourceId);
    }
}
