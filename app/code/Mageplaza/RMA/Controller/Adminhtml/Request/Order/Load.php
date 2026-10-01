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

namespace Mageplaza\RMA\Controller\Adminhtml\Request\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Element\Messages;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Items;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Config\Source\RMARequest\Orders as RMAOrders;
use Mageplaza\RMA\Model\Request;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;

/**
 * Class Load
 * @package Mageplaza\RMA\Controller\Adminhtml\Request\Order
 */
class Load extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::request';

    /**
     * @var PageFactory
     */
    protected $_pageFactory;

    /**
     * @var Json
     */
    protected $_resultJson;

    /**
     * @var OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var OrderResource
     */
    protected $_orderResource;

    /**
     * @var RequestFactory
     */
    protected $_requestFactory;

    /**
     * @var RequestResource
     */
    protected $_requestResource;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * @var RMAOrders
     */
    protected $_RMAOrders;

    /**
     * Load constructor.
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param Json $resultJson
     * @param OrderFactory $orderFactory
     * @param OrderResource $orderResource
     * @param RequestFactory $requestFactory
     * @param RequestResource $requestResource
     * @param HelperData $helperData
     * @param RMAOrders $RMAOrders
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        Json $resultJson,
        OrderFactory $orderFactory,
        OrderResource $orderResource,
        RequestFactory $requestFactory,
        RequestResource $requestResource,
        HelperData $helperData,
        RMAOrders $RMAOrders
    ) {
        $this->_pageFactory     = $pageFactory;
        $this->_resultJson      = $resultJson;
        $this->_orderFactory    = $orderFactory;
        $this->_orderResource   = $orderResource;
        $this->_requestFactory  = $requestFactory;
        $this->_requestResource = $requestResource;
        $this->_helperData      = $helperData;
        $this->_RMAOrders       = $RMAOrders;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $page             = $this->_pageFactory->create();
        $orderIncrementId = $this->getRequest()->getParam('order_increment_id', 0);
        /** @var Messages $messageBlock */
        $messageBlock     = $page->getLayout()->createBlock(Messages::class);
        if (!$orderIncrementId) {
            $messageBlock->addError(__('You have entered invalid order increment ID.'));

            return $this->_resultJson->setData([
                'status'        => false,
                'error_message' => $messageBlock->toHtml()
            ]);
        }
        $requestId = $this->getRequest()->getParam('request_id', 0);
        /** @var Order $order */
        $order = $this->_orderFactory->create()->loadByIncrementId($orderIncrementId);
        if (!$order->getId()) {
            $messageBlock->addError(__('You have entered invalid order increment ID.'));

            return $this->_resultJson->setData([
                'status'        => false,
                'error_message' => $messageBlock->toHtml()
            ]);
        }
        $availableIncrementIds = $this->_RMAOrders->orderListIncrementId();

        if (!$requestId && !in_array($order->getIncrementId(), $availableIncrementIds, true)) {
            $messageBlock->addError(__('You cannot create return request for this Order.'));

            return $this->_resultJson->setData([
                'status'        => false,
                'error_message' => $messageBlock->toHtml()
            ]);
        }
        /** @var Items $itemBlock */
        $itemBlock = $page->getLayout()->createBlock(Items::class);
        $html      = $itemBlock
            ->setCurrentRequest($this->_getCurrentRequest($requestId))
            ->setOrderedProducts($order->getItemsCollection())
            ->setOrder($order)
            ->setTemplate('Mageplaza_RMA::request/form/items.phtml')
            ->toHtml();

        $customerEmail = $order->getBillingAddress() ? $order->getBillingAddress()->getEmail() : '';
        $emailText     = '<a id="mp-customer-email"
            class="mp-customer-email"
            href="mailto:' . $customerEmail . '">' . $customerEmail . '</a>';
        if ($order->getCustomerIsGuest()) {
            $cuzNameText = '<span id="mp-customer-name" class="mp-customer-name">' . __('Guest') . '</span>';
        } else {
            $cuzNameText = '<a id="mp-customer-name"
            class="mp-customer-name"
            href="' . $this->getUrl('customer/index/edit', ['id' => $order->getCustomerId()])
                . '" onclick="this.target=\'blank\'">' . $order->getCustomerName() . '</a>';
        }
        $emailText .= '<input type="hidden" id="mp-customer-email-input"
                class="mp-customer-email-input" name="request[customer_email]" value="' . $customerEmail . '">';

        $storeId = $order->getStoreId();
        if (!is_array($storeId)) {
            $storeId = [$storeId];
        }
        $storeViewText  = $this->_helperData->getStoresStructureHtml($storeId);
        $storeViewText .= '<input type="hidden" id="mp-store-id-input"
        class="mp-store-id-input" name="request[store_id]" value="' . $order->getStoreId() . '">';
        $result = [
            'status'           => true,
            'order_id'         => $order->getId(),
            'email_text'       => $emailText,
            'cuz_name_text'    => $cuzNameText,
            'store_text'       => $storeViewText,
            'request_products' => $html
        ];

        return $this->_resultJson->setData($result);
    }

    /**
     * @param string $requestId
     *
     * @return bool|Request
     */
    protected function _getCurrentRequest($requestId)
    {
        if ($requestId) {
            $request = $this->_requestFactory->create();
            $this->_requestResource->load($request, $requestId);

            return $request;
        }

        return false;
    }
}
