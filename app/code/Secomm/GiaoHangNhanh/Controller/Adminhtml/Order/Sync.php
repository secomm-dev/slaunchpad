<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Controller\Adminhtml\Order;

use Secomm\GiaoHangNhanh\Model\Config;
use Secomm\GiaoHangNhanh\Model\Service\OrderSyncService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Admin controller to manually sync a GHN order.
 *
 * - Direct mode: calls GHN API immediately and shows real success/error to admin.
 * - Async mode: publishes to queue (same flow as auto-sync).
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
     * @param OrderSyncService $orderSyncService
     */
    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PublisherInterface $publisher,
        private readonly Config $config,
        private readonly OrderSyncService $orderSyncService
    ) {
        parent::__construct($context);
    }

    /**
     * Sync GHN order — directly or via queue depending on Sync Mode config.
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

            if ($this->config->isDirectSyncMode()) {
                // --- Direct mode: gọi GHN API ngay lập tức ---
                $this->orderSyncService->sync($order);

                // Sau khi sync thành công, reload order để lấy tracking_code mới được lưu
                $order = $this->orderRepository->get($orderId);
                $trackingCode = $order->getTrackingCode();

                if ($trackingCode) {
                    $this->messageManager->addSuccessMessage(
                        __('Đơn hàng đã được đồng bộ trực tiếp lên GHN. Mã vận đơn: %1', $trackingCode)
                    );
                } else {
                    $this->messageManager->addSuccessMessage(__('Đơn hàng đã được đồng bộ lên GHN thành công.'));
                }
            } else {
                // --- Async mode (mặc định): đẩy vào Message Queue ---
                $this->publisher->publish(self::TOPIC, json_encode(['order_id' => $orderId]));
                $this->messageManager->addSuccessMessage(__('GHN sync has been queued for this order.'));
            }
        } catch (\Exception $e) {
            if ($this->config->isDirectSyncMode()) {
                $this->messageManager->addErrorMessage(__('Đồng bộ GHN thất bại: %1', $e->getMessage()));
            } else {
                $this->messageManager->addErrorMessage(__('Failed to queue GHN sync: %1', $e->getMessage()));
            }
        }

        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
