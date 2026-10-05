<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Secomm\EInvoiceCore\Model\Service\IssueInvoiceService;

/**
 * Cancels the issued electronic invoice when an order is cancelled.
 */
class OrderCancelAfter implements ObserverInterface
{
    /**
     * @param IssueInvoiceService $issueInvoiceService
     */
    public function __construct(
        private readonly IssueInvoiceService $issueInvoiceService
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order instanceof Order || !$order->getEntityId()) {
            return;
        }

        $this->issueInvoiceService->cancelForOrderSafe(
            (int) $order->getEntityId(),
            (string) __('Order %1 cancelled in Magento.', $order->getIncrementId())
        );
    }
}
