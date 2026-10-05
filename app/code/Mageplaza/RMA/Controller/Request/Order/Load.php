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
 * @category  Mageplaza
 * @package   Mageplaza_RMA
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Controller\Request\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Mageplaza\RMA\Block\Request\Index\Items;
use Mageplaza\RMA\Helper\Data as HelperData;

/**
 * Class Load
 * @package Mageplaza\RMA\Controller\Request\Order
 */
class Load extends Action
{
    /**
     * @var PageFactory
     */
    protected $_pageFactory;

    /**
     * @var OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var OrderResource
     */
    protected $_orderResource;

    /**
     * @var Json
     */
    protected $_resultJson;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Load constructor.
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param Json $resultJson
     * @param OrderFactory $orderFactory
     * @param OrderResource $orderResource
     * @param HelperData $helperData
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        Json $resultJson,
        OrderFactory $orderFactory,
        OrderResource $orderResource,
        HelperData $helperData
    ) {
        $this->_pageFactory = $pageFactory;
        $this->_resultJson = $resultJson;
        $this->_orderFactory = $orderFactory;
        $this->_orderResource = $orderResource;
        $this->_helperData = $helperData;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $page = $this->_pageFactory->create();
        $orderId = $this->getRequest()->getParam('order_id', 0);

        $order = $this->_orderFactory->create();
        $this->_orderResource->load($order, $orderId);
        $isValid = true;

        if (!$this->_helperData->getConfigGeneral('enabled_guest')
            && $order->getCustomerId() !== $this->_helperData->getCustomerId()) {
            $isValid = false;
        }

        if (!$isValid) {
            return $this->_resultJson->setData([
                'status' => false,
                'error_message' => __('Cannot find this order.')
            ]);
        }

        /** @var Items $itemBlock */
        $itemBlock = $page->getLayout()->createBlock(Items::class);
        if ($this->_helperData->checkHyvaTheme()) {
            $html = $itemBlock->setOrderedProducts($order->getItemsCollection())
                ->setOrder($order)
                ->setTemplate('Mageplaza_RMA::hyva/request/index/items.phtml')
                ->toHtml();
        } else {
            $html = $itemBlock->setOrderedProducts($order->getItemsCollection())
                ->setOrder($order)
                ->setTemplate('Mageplaza_RMA::request/index/items.phtml')
                ->toHtml();
        }
        $billingAddress = $order->getBillingAddress();

        $result = [
            'status' => true,
            'request_products' => $html,
            'customer_email' => $billingAddress ? $billingAddress->getEmail() : '',
            'store_id' => $order->getStoreId(),
            'order_increment_id' => $order->getIncrementId(),
            'billing_lastname' => $billingAddress ? $billingAddress->getLastname() : ''
        ];

        return $this->_resultJson->setData($result);
    }
}
