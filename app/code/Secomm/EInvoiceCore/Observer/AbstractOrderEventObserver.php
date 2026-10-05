<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceCore\Model\Config;
use Secomm\EInvoiceCore\Model\Service\IssueInvoiceService;
use Throwable;

/**
 * Base observer that schedules invoice issuance on supported order events.
 */
abstract class AbstractOrderEventObserver implements ObserverInterface
{
    /**
     * @param IssueInvoiceService $issueInvoiceService
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected readonly IssueInvoiceService $issueInvoiceService,
        protected readonly Config $config,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $order = $this->extractOrder($observer);
        if ($order === null || !$order->getEntityId()) {
            return;
        }

        $storeId = (int) $order->getStoreId();
        if (!$this->issueInvoiceService->shouldReactToTrigger($this->getTrigger(), $storeId)) {
            return;
        }

        if (!$this->config->isAutoIssue($storeId)) {
            return;
        }

        if (!$this->shouldSchedule($order)) {
            return;
        }

        try {
            $this->issueInvoiceService->schedule((int) $order->getEntityId());
        } catch (Throwable $throwable) {
            $this->logger->error('EInvoice schedule failed on event', [
                'trigger' => $this->getTrigger(),
                'order_id' => $order->getEntityId(),
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Extra gate per trigger (e.g. first shipment only).
     *
     * @param Order $order
     * @return bool
     */
    protected function shouldSchedule(Order $order): bool
    {
        return true;
    }

    /**
     * Config trigger code handled by this observer.
     *
     * @return string
     */
    abstract protected function getTrigger(): string;

    /**
     * Extract sales order from the event observer payload.
     *
     * @param Observer $observer
     * @return Order|null
     */
    abstract protected function extractOrder(Observer $observer): ?Order;
}
