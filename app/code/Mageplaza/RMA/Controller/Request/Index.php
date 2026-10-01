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

namespace Mageplaza\RMA\Controller\Request;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\RMA\Helper\Data;
use Mageplaza\RMA\Model\Config\Source\RMARequest\FindOrder;
use Mageplaza\RMA\Model\Config\Source\RMARequest\Orders;

/**
 * Class Index
 * @package Mageplaza\RMA\Controller\Request
 */
class Index extends Action
{
    /**
     * Cookie key for guest view
     */
    const COOKIE_NAME = 'guest-view';
    /**
     * Cookie path
     */
    const COOKIE_PATH = '/';

    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var CookieManagerInterface
     */
    protected $_cookieManager;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     * @var CookieMetadataFactory
     */
    protected $_cookieMetadataFactory;

    /**
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var Orders
     */
    protected $_rmaOrders;

    /**
     * @var Data
     */
    protected $_helperData;

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param CookieManagerInterface $cookieManager
     * @param StoreManagerInterface $storeManager
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param Registry $coreRegistry
     * @param Orders $rmaOrders
     * @param Data $helperData
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        CookieManagerInterface $cookieManager,
        StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CookieMetadataFactory $cookieMetadataFactory,
        Registry $coreRegistry,
        Orders $rmaOrders,
        Data $helperData
    ) {
        parent::__construct($context);

        $this->_resultPageFactory = $resultPageFactory;
        $this->_cookieManager = $cookieManager;
        $this->_storeManager = $storeManager;
        $this->_orderRepository = $orderRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_cookieMetadataFactory = $cookieMetadataFactory;
        $this->_coreRegistry = $coreRegistry;
        $this->_rmaOrders = $rmaOrders;
        $this->_helperData = $helperData;
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        if (!$this->_helperData->isEnabled()) {
            return $this->_redirect('noroute');
        }
        /** @var RequestInterface $request */
        $request = $this->getRequest();
        /** @var Page $resultPage */
        $resultPage = $this->_resultPageFactory->create();
        if (($customerId = $request->getParam('customer_id'))
            && $customerId === $this->_helperData->getCustomerId()
        ) {
            $resultPage->getConfig()->getTitle()->set(__('New RMA Request'));
            $this->_coreRegistry->register('is_customer_request', true);

            return $resultPage;
        }
        $result = $this->loadValidOrder($request);
        if ($result instanceof ResultInterface) {
            return $result;
        }
        /** @var Order $order */
        $order = $this->_coreRegistry->registry('current_order');
        $resultPage->getConfig()->getTitle()->set(__('New RMA Request for Order # %1', $order->getRealOrderId()));

        return $resultPage;
    }

    /**
     * Try to load valid order by $_POST or $_COOKIE
     *
     * @param RequestInterface $request
     *
     * @return Redirect|bool
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     * @throws NoSuchEntityException
     */
    public function loadValidOrder($request)
    {
        $post = $request->getPostValue();
        $fromCookie = $this->_cookieManager->getCookie(self::COOKIE_NAME);
        if (empty($post) && !$fromCookie) {
            return $this->resultRedirectFactory->create()->setPath('mprma/request/form');
        }
        try {
            $order = (!empty($post)
                && isset($post['mprma_order_id'], $post['mprma_type'])
                && !$this->_hasPostDataEmptyFields($post))
                ? $this->_loadFromPost($post) : $this->_loadFromCookie($fromCookie);
            $this->_coreRegistry->register('current_order', $order);

            return true;
        } catch (InputException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->resultRedirectFactory->create()->setPath('mprma/request/form');
        }
    }

    /**
     * Check post data for empty fields
     *
     * @param array $postData
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    private function _hasPostDataEmptyFields($postData)
    {
        return empty($postData['mprma_order_id']) || empty($postData['mprma_billing_lastname']) ||
            empty($postData['mprma_type']) || empty($this->_storeManager->getStore()->getId()) ||
            !in_array($postData['mprma_type'], [FindOrder::FIND_BY_EMAIL, FindOrder::FIND_BY_ZIP_CODE], true) ||
            (FindOrder::FIND_BY_EMAIL === $postData['mprma_type'] && empty($postData['mprma_email'])) ||
            (FindOrder::FIND_BY_ZIP_CODE === $postData['mprma_type'] && empty($postData['mprma_zip']));
    }

    /**
     * Load order data from post
     *
     * @param array $postData
     *
     * @return Order
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    private function _loadFromPost($postData)
    {
        /** @var Order $order */
        $order = $this->_getOrderRecord($postData['mprma_order_id']);
        if (!$this->_compareStoredBillingDataWithInput($order, $postData)) {
            throw new InputException(__('You entered incorrect data. Please try again.'));
        }
        $toCookie = base64_encode($order->getProtectCode() . ':'
            . $postData['mprma_order_id'] . ':' . $postData['mprma_type']);
        $this->_setGuestViewCookie($toCookie);
        $order->setFindType($postData['mprma_type']);

        return $order;
    }

    /**
     * Get order by increment_id and store_id
     *
     * @param string $incrementId
     *
     * @return OrderInterface
     * @throws InputException
     * @throws NoSuchEntityException
     */
    private function _getOrderRecord($incrementId)
    {
        $records = $this->_orderRepository->getList(
            $this->_searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId)
                ->addFilter('store_id', $this->_storeManager->getStore()->getId())
                ->create()
        );
        $items = $records->getItems();
        if (empty($items)) {
            throw new InputException(__('You entered incorrect data. Please try again.'));
        }
        $item = array_shift($items);

        $availableOrderIds = [];
        foreach ($this->_rmaOrders->orderListID() as $orderOption) {
            $availableOrderIds[] = $orderOption;
        }
        if (!in_array($item->getId(), $availableOrderIds, true)) {
            throw new InputException(__('You cannot create return request for this order.'));
        }

        return $item;
    }

    /**
     * Check that billing data from the order and from the input are equal
     *
     * @param Order $order
     * @param array $postData
     *
     * @return bool
     */
    private function _compareStoredBillingDataWithInput($order, $postData)
    {
        $type = $postData['mprma_type'];
        $email = $postData['mprma_email'];
        $lastName = $postData['mprma_billing_lastname'];
        $zip = $postData['mprma_zip'];
        $billingAddress = $order->getBillingAddress();
        if (!$billingAddress) {
            return false;
        }

        $isExistByMail = $type === FindOrder::FIND_BY_EMAIL
            && strtolower($email) === strtolower($billingAddress->getEmail());
        $isExistByPostCode = $type === FindOrder::FIND_BY_ZIP_CODE
            && strtolower($zip) === strtolower($billingAddress->getPostcode());

        return strtolower($lastName) === strtolower($billingAddress->getLastname())
            && ($isExistByMail || $isExistByPostCode);
    }

    /**
     * Set guest-view cookie
     *
     * @param string $cookieValue
     *
     * @return void
     * @throws InputException
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     */
    private function _setGuestViewCookie($cookieValue)
    {
        $metadata = $this->_cookieMetadataFactory->createPublicCookieMetadata()
            ->setPath(self::COOKIE_PATH)
            ->setHttpOnly(true);
        $this->_cookieManager->setPublicCookie(self::COOKIE_NAME, $cookieValue, $metadata);
    }

    /**
     * Load order from cookie
     *
     * @param string $fromCookie
     *
     * @return Order|OrderInterface
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    private function _loadFromCookie($fromCookie)
    {
        $cookieData = explode(':', base64_decode($fromCookie));
        $protectCode = isset($cookieData[0]) ? $cookieData[0] : null;
        $incrementId = isset($cookieData[1]) ? $cookieData[1] : null;
        $findType = isset($cookieData[2]) ? $cookieData[2] : null;
        if (!empty($protectCode) && !empty($incrementId) && !empty($findType)) {
            $order = $this->_getOrderRecord($incrementId);
            if (hash_equals((string)$order->getProtectCode(), $protectCode)) {
                $this->_setGuestViewCookie($fromCookie);
                $order->setFindType($findType);

                return $order;
            }
        }
        throw new InputException(__('You entered incorrect data. Please try again.'));
    }
}
