<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Secomm\EInvoiceCore\Model\Config;
use Secomm\EInvoiceCore\Model\Service\IssueInvoiceService;

/**
 * Issues an adjustment invoice when a credit memo is created for an invoiced order.
 */
class OrderCreditmemoSaveAfter implements ObserverInterface
{
    /**
     * @param IssueInvoiceService $issueInvoiceService
     * @param Config $config
     */
    public function __construct(
        private readonly IssueInvoiceService $issueInvoiceService,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $creditmemo = $observer->getEvent()->getData('creditmemo');
        if (!$creditmemo instanceof Creditmemo || !$creditmemo->getEntityId()) {
            return;
        }

        $storeId = (int) $creditmemo->getStoreId();

        if (!$this->config->isEnabled($storeId)) {
            return;
        }

        if (!$this->config->isAutoCreditmemoAdjustment($storeId)) {
            return;
        }

        $this->issueInvoiceService->issueAdjustmentSafe((int) $creditmemo->getEntityId());
    }
}
