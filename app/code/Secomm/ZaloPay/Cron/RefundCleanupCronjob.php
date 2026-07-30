<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\ZaloPay\Cron;

use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Secomm\ZaloPay\Logger\Logger as LoggerInterface;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;

/**
 * Class RefundCleanupCronjob
 *
 * Cron job class responsible for deleting processed refund items.
 *
 */
class RefundCleanupCronjob
{
    /**
     * @var RefundCollectionFactory
     */
    private RefundCollectionFactory $refundCollectionFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * RefundCleanupCronjob constructor.
     *
     * @param RefundCollectionFactory $refundCollectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        RefundCollectionFactory $refundCollectionFactory,
        LoggerInterface $logger
    ) {
        $this->refundCollectionFactory = $refundCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * Execute Refund Cleanup Cron Job
     *
     * This method is triggered by the cron scheduler to delete processed refund items.
     */
    public function execute()
    {
        try {
            // Get a collection of processed refunds
            $processedRefunds = $this->getProcessedRefunds();

            // Delete each processed refund
            foreach ($processedRefunds as $processedRefund) {
                $processedRefund->delete();
            }

            // Log the cleanup
            $this->logger->info('Processed refund items deleted.');
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    /**
     * Get processed refund collection
     *
     * @return RefundCollection
     */
    public function getProcessedRefunds()
    {
        // Get a collection of processed refunds
        $refundCollection = $this->refundCollectionFactory->create();
        $refundCollection->addFieldToFilter('is_processed', ['eq' => RefundInterface::PROCESSED]);
        return $refundCollection;
    }
}
