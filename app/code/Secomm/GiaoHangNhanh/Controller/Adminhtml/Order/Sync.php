<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Controller\Adminhtml\Order;

use Secomm\GiaoHangNhanh\Model\Config;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Admin controller to manually queue a GHN sync for an order.
 * Does NOT call GHN API directly — delegates to the queue consumer (same flow as auto-sync).
 */
class Sync extends Action
{
    /**
     * ACL resource required to access this controller
     */
    const ADMIN_RESOURCE = 'Magento_Sales::actions';

    /**
     * Queue topic for GHN order sync
     */
    const TOPIC = 'ghn.sync.order';

    /**
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param PublisherInterface $publisher
     * @param Config $config
     */
    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PublisherInterface $publisher,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    /**
     * Queue a GHN sync message for the given order
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Order ID is missing.'));
            return $resultRedirect->setPath('sales/order/index');
        }

        try {
            $order = $this->orderRepository->get($orderId);

            if (false === strpos((string) $order->getShippingMethod(), Config::GHN_CODE)) {
                $this->messageManager->addErrorMessage(__('This order does not use GHN shipping.'));
                return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
            }

            if ($order->getTrackingCode()) {
                $this->messageManager->addWarningMessage(__('This order is already synced to GHN.'));
                return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
            }

            $this->publisher->publish(self::TOPIC, json_encode(['order_id' => $orderId]));
            $this->messageManager->addSuccessMessage(__('GHN sync has been queued for this order.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Failed to queue GHN sync: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
