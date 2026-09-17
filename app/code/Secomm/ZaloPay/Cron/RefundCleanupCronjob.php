<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Logger\Logger as LoggerInterface;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;

/**
 * Refund evidence cleanup (TASK-CG6BM7 corrective round 7, F36).
 *
 * RETENTION POLICY: refund rows are FINANCIAL EVIDENCE - the cron deletes
 * ONLY rows that are fully resolved AND older than the retention window:
 *
 *     is_processed = 1
 *     AND refund_state = 'confirmed_success'
 *     AND updated_at < (now UTC - REFUND_EVIDENCE_RETENTION_DAYS days)
 *
 * updated_at is the Magento-managed UTC timestamp (on_update = true), so
 * every transition refreshes the retention clock. NOTHING else is ever
 * deleted: confirmed_fail, unknown, processing, provider_request_started,
 * provider_success_local_pending and initiating rows are retained
 * REGARDLESS of age or is_processed - unresolved or non-success evidence
 * must stay available for manual reconciliation / audit.
 */
class RefundCleanupCronjob
{
    /**
     * Payment method active config path.
     */
    private const XML_PATH_ACTIVE = 'payment/zalopay/active';

    /**
     * How long a fully-resolved (confirmed_success) refund row is kept as
     * financial evidence before the cleanup may delete it (days, UTC).
     */
    public const REFUND_EVIDENCE_RETENTION_DAYS = 90;

    /**
     * RefundCleanupCronjob constructor.
     *
     * @param RefundCollectionFactory $refundCollectionFactory
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly RefundCollectionFactory $refundCollectionFactory,
        private readonly LoggerInterface $logger,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Execute Refund Cleanup Cron Job
     *
     * Deletes ONLY confirmed_success rows older than
     * REFUND_EVIDENCE_RETENTION_DAYS (see the class retention policy).
     */
    public function execute(): void
    {
        if (!$this->isActive()) {
            return;
        }

        try {
            $processedRefunds = $this->getProcessedRefunds();
            $deleted = 0;
            foreach ($processedRefunds as $processedRefund) {
                $processedRefund->delete();
                $deleted++;
            }

            $this->logger->info(
                $deleted > 0
                    ? sprintf('ZaloPay refund evidence cleanup: %d confirmed_success row(s) older than %d day(s) deleted.', $deleted, self::REFUND_EVIDENCE_RETENTION_DAYS)
                    : 'ZaloPay refund evidence cleanup: nothing eligible to delete.'
            );
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    /**
     * Get the deletable refund collection: resolved confirmed_success rows
     * past the retention window ONLY (see the class retention policy -
     * every other state is retained regardless of age or is_processed).
     *
     * @return RefundCollection
     */
    public function getProcessedRefunds(): RefundCollection
    {
        $refundCollection = $this->refundCollectionFactory->create();
        $refundCollection->addFieldToFilter(RefundInterface::IS_PROCESSED, ['eq' => RefundInterface::PROCESSED]);
        $refundCollection->addFieldToFilter(
            RefundInterface::REFUND_STATE,
            ['eq' => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS]
        );
        $refundCollection->addFieldToFilter('updated_at', ['lt' => $this->getRetentionCutoff()]);

        return $refundCollection;
    }

    /**
     * The retention cutoff as a UTC 'Y-m-d H:i:s' timestamp: now minus
     * REFUND_EVIDENCE_RETENTION_DAYS. Compare against updated_at (a
     * Magento-managed UTC timestamp with on_update = true).
     *
     * @return string
     */
    private function getRetentionCutoff(): string
    {
        $cutoff = new \DateTime('now', new \DateTimeZone('UTC'));
        $cutoff->modify('-' . self::REFUND_EVIDENCE_RETENTION_DAYS . ' days');

        return $cutoff->format('Y-m-d H:i:s');
    }

    /**
     * Check whether the ZaloPay payment method is enabled.
     *
     * @return bool
     */
    private function isActive(): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE);
    }
}
