<?php declare(strict_types=1);

namespace Secomm\GiaoHangNhanh\Model\Queue;

use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command\CommandPoolInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class OrderCancelConsumer
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CommandPoolInterface $commandPool,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param string $message
     */
    public function process(string $message)
    {
        try {
            $data = json_decode($message, true);
            $orderId = (int) ($data['order_id'] ?? 0);

            if (!$orderId) {
                $this->logger->error('[GHN CancelOrder] Invalid order_id in message');
                return;
            }

            $order = $this->orderRepository->get($orderId);

            if ($order->getData('ghn_status') && $order->getData('tracking_code')) {
                $this->commandPool->get('cancel_order')->execute(['order' => $order]);
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->error('[GHN CancelOrder] Order not found: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[GHN CancelOrder] Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
