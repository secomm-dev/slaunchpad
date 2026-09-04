<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Cron;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\VietQr\Model\Config;

/**
 * Auto-cancels overdue unpaid VietQR orders (TASK-6X2FQH / AC-024..AC-026).
 *
 * Only `secomm_vietqr` orders in the configured New Order Status (default
 * `vietqr_pending`) older than the configured timeout are canceled. Orders in
 * `vietqr_awaiting_payment_confirm` are never touched here — the customer
 * already confirmed the transfer, the merchant reconciles those manually.
 */
class CancelPendingOrders
{
    private const PAYMENT_METHOD = 'secomm_vietqr';

    private const BATCH_SIZE = 100;

    // ponytail: hard ceiling of 500 orders/store/run; at a 5-min cron that is
    // 100 abandoned orders/min before falling behind. Raise if a backlog forms.
    private const MAX_ORDERS_PER_STORE_PER_RUN = 500;

    public function __construct(
        private readonly Config $config,
        private readonly CollectionFactory $orderCollectionFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly \Psr\Log\LoggerInterface $logger
    ) {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        foreach ($this->storeManager->getStores(false) as $store) {
            $storeId = (int)$store->getId();
            if (!$this->config->isAutoCancelEnabled($storeId)) {
                continue;
            }

            try {
                $this->cancelOverdueForStore($storeId);
            } catch (\Throwable $e) {
                $this->logger->error(
                    'VietQR auto-cancel run failed for store ' . $storeId,
                    ['error' => $e->getMessage()]
                );
            }
        }
    }

    /**
     * @param int $storeId
     * @return void
     */
    private function cancelOverdueForStore(int $storeId): void
    {
        $timeout = $this->config->getAutoCancelTimeout($storeId);
        if ($timeout <= 0) {
            $this->logger->warning('VietQR auto-cancel: timeout must be positive, skipping store ' . $storeId);
            return;
        }

        $status = $this->config->getNewOrderStatus($storeId);
        $reason = $this->config->getAutoCancelReason($storeId);
        if ($status === '' || $reason === '') {
            return;
        }

        // created_at is stored in UTC — the cutoff must be computed in UTC
        // (using store-local time would shift it by the UTC offset).
        $cutoff = new \DateTime('now', new \DateTimeZone('UTC'));
        $cutoff->modify('-' . $timeout . ' minutes');

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('status', $status)
            ->addFieldToFilter('created_at', ['lt' => $cutoff->format('Y-m-d H:i:s')]);
        $collection->getSelect()->join(
            ['vietqr_payment' => $collection->getResource()->getTable('sales_order_payment')],
            'main_table.entity_id = vietqr_payment.parent_id',
            []
        )->where('vietqr_payment.method = ?', self::PAYMENT_METHOD);

        $ids = array_slice($collection->getAllIds(), 0, self::MAX_ORDERS_PER_STORE_PER_RUN);

        $canceled = 0;
        foreach (array_chunk(array_map('intval', $ids), self::BATCH_SIZE) as $chunk) {
            foreach ($chunk as $orderId) {
                if ($this->cancelOrder($orderId, $status, $reason)) {
                    $canceled++;
                }
            }
        }

        if ($canceled > 0) {
            $this->logger->info(
                sprintf('VietQR auto-cancel: canceled %d order(s) for store %d (timeout %d min).', $canceled, $storeId, $timeout)
            );
        }
    }

    /**
     * Cancel a single order, re-verifying it on a fresh load immediately
     * before canceling — guards against canceling an order a customer just
     * confirmed via the Submit endpoint while the cron was running (AC-026).
     *
     * @param int $orderId
     * @param string $expectedStatus
     * @param string $reason
     * @return bool
     */
    private function cancelOrder(int $orderId, string $expectedStatus, string $reason): bool
    {
        try {
            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();
            if (!$payment || $payment->getMethod() !== self::PAYMENT_METHOD) {
                return false;
            }
            if ($order->getStatus() !== $expectedStatus) {
                return false;
            }

            /** @var Order $order */
            $order->registerCancellation($reason);
            $this->orderRepository->save($order);
            $this->logger->info(
                'VietQR auto-canceled order ' . $order->getIncrementId() . ' (payment timeout exceeded).'
            );
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                'VietQR auto-cancel failed for order id ' . $orderId,
                ['error' => $e->getMessage()]
            );
            return false;
        }
    }
}
