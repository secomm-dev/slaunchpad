<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Secomm\EInvoiceCore\Api\OrderStoreIdProviderInterface;

/**
 * Resolves Magento store ID from a sales order entity.
 */
class OrderStoreIdProvider implements OrderStoreIdProviderInterface
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getStoreIdByOrderId(int $orderId): int
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(__('Order %1 not found.', $orderId), $exception);
        }

        return (int) $order->getStoreId();
    }
}
