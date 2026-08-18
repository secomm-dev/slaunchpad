<?php declare(strict_types=1);

namespace Secomm\GiaoHangNhanh\Model\Queue;

use Secomm\GiaoHangNhanh\Model\Service\OrderSyncService;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class OrderSyncConsumer
{
    /**
     * @param OrderSyncService $orderSyncService
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderSyncService $orderSyncService,
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
            if (is_string($data)) {
                $data = json_decode($data, true);
            }
            $orderId = is_array($data) ? (int) ($data['order_id'] ?? 0) : (int)$message;

            if (!$orderId) {
                $this->logger->error('[GHN OrderSync] Invalid order_id in message');
                return;
            }

            $this->orderSyncService->sync($orderId);
        } catch (NoSuchEntityException $e) {
            $this->logger->error('[GHN OrderSync] Order/Quote not found: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[GHN OrderSync] Error: ' . $e->getMessage());
            throw $e; // Re-throw để Magento MQ retry
        }
    }
}
