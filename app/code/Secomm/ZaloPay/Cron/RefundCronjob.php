<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Gateway\Command\RefundQueryCommand;
use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Logger\Logger as LoggerInterface;
use Secomm\ZaloPay\Model\Data\RefundData;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;
use Secomm\ZaloPay\Helper\RefundProcessor;

/**
 * RefundCronjob Class
 *
 * Bounded, terminal-explicit poller for async ZaloPay refunds
 * (TASK-CG6BM7): every NOT_PROCESSED refund row whose query budget
 * (`query_attempts`) is not exhausted is queried via v2/query_refund and
 * resolved to exactly one terminal outcome:
 *
 *  - return_code 1  -> SUCCESS: creditmemo -> STATE_REFUNDED, row PROCESSED,
 *    last_error cleared;
 *  - return_code 2  -> terminal FAIL: budget saturated, last_error holds a
 *    safe provider-map message; the row is never queried again (evidence
 *    retained — RefundCleanupCronjob only deletes PROCESSED rows);
 *  - anything else  -> still pending (PROCESSING, transport error, protocol
 *    anomaly): budget consumed by one, retried on the next run until the
 *    cap, then explicitly exhausted (critical log).
 *
 * Items are isolated: one broken row (malformed payload, missing creditmemo,
 * transport failure) is logged and skipped — the batch continues.
 */
class RefundCronjob
{
    /**
     * Payment method active config path.
     */
    private const XML_PATH_ACTIVE = 'payment/zalopay/active';

    /**
     * Bounded v2/query_refund budget: 96 runs on the 15-minute schedule
     * (~24h). Terminal FAIL saturates the budget so the row is never
     * re-selected (never loops silently); pending rows are retried until
     * the cap and then explicitly exhausted.
     */
    public const MAX_QUERY_ATTEMPTS = 96;

    /**
     * RefundCronjob constructor.
     *
     * @param RefundCollectionFactory $refundCollectionFactory
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param LoggerInterface $logger
     * @param RefundQueryCommand $refundQueryCommand
     * @param DateTime $dateTime
     * @param Authorization $authorization
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $serializer
     */
    public function __construct(
        private readonly RefundCollectionFactory       $refundCollectionFactory,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly LoggerInterface               $logger,
        private readonly RefundQueryCommand            $refundQueryCommand,
        private readonly DateTime                      $dateTime,
        private readonly Authorization                 $authorization,
        private readonly ScopeConfigInterface          $scopeConfig,
        private readonly Json                          $serializer
    ) {
    }

    /**
     * Execute Refund Cron Job
     *
     * This method is triggered by the cron scheduler to process refunds with
     * 'is_processed' set to false and a non-exhausted query budget.
     */
    public function execute(): void
    {
        if (!$this->isActive()) {
            return;
        }

        $this->logger->info('Cron Start');
        foreach ($this->getUnprocessedRefunds() as $refund) {
            try {
                $this->processRefund($refund);
            } catch (\Throwable $exception) {
                // Per-item isolation (TASK-CG6BM7): one broken item never
                // blocks the batch.
                $this->logger->error(
                    sprintf(
                        'ZaloPay refund query for refund row #%d failed: %s',
                        (int)$refund->getId(),
                        $exception->getMessage()
                    )
                );
            }
        }
        $this->logger->info('Cron End');
    }

    /**
     * Resolve ONE refund row against the provider to a terminal outcome.
     *
     * @param \Secomm\ZaloPay\Model\RefundModel $refund
     * @return void
     * @throws LocalizedException Malformed stored payload.
     */
    private function processRefund($refund): void
    {
        $creditMemoId = (int)$refund->getCreditMemoId();
        $creditMemo = $this->creditmemoRepository->get($creditMemoId);
        $commandSubject = $this->buildQuerySubject($refund);

        $response = $this->refundQueryCommand->getRefundQuery($commandSubject);
        $statusCode = $this->readReturnCode($response);

        if ($statusCode === AbstractResponseValidator::RETURN_CODE_ACCEPT) {
            // Terminal SUCCESS: refund money landed — flip the creditmemo.
            $creditMemo->setState(Creditmemo::STATE_REFUNDED);
            $this->creditmemoRepository->save($creditMemo);
            $refund->setIsProcessed(true);
            $refund->setData(RefundInterface::LAST_ERROR, null);
            $refund->save();
            $this->logger->info('Refund processed for credit memo ID ' . $creditMemoId);

            return;
        }

        if ($statusCode === AbstractResponseValidator::REFUND_FAIL) {
            // Terminal FAIL (explicit, never a silent loop): saturate the
            // budget so the row is never re-selected; keep the safe mapped
            // message as evidence. Cleanup only deletes PROCESSED rows, so
            // this evidence survives monthly cleanup.
            $refund->setData(RefundInterface::QUERY_ATTEMPTS, self::MAX_QUERY_ATTEMPTS);
            $refund->setData(RefundInterface::LAST_ERROR, $this->safeFailMessage($response));
            $refund->save();
            $this->logger->critical(
                sprintf(
                    'ZaloPay refund FAILED terminally for credit memo ID %d: %s',
                    $creditMemoId,
                    $this->safeFailMessage($response)
                )
            );

            return;
        }

        // Still pending: PROCESSING (3), unknown or missing code. Consume
        // budget and wait for the next run; at the cap, exhaust explicitly.
        $attempts = (int)$refund->getData(RefundInterface::QUERY_ATTEMPTS) + 1;
        $refund->setData(RefundInterface::QUERY_ATTEMPTS, $attempts);
        $refund->save();
        if ($attempts >= self::MAX_QUERY_ATTEMPTS) {
            $this->logger->critical(
                sprintf(
                    'ZaloPay refund query budget exhausted for credit memo ID %d after %d attempts.',
                    $creditMemoId,
                    $attempts
                )
            );
        }
    }

    /**
     * Rebuild the v2/query_refund subject from the stored payload: refresh
     * the timestamp, drop the stale MAC, re-sign (official MAC keys:
     * app_id|m_refund_id|timestamp — the stored payload holds exactly these).
     *
     * @param \Secomm\ZaloPay\Model\RefundModel $refund
     * @return array
     * @throws LocalizedException Malformed stored payload.
     */
    private function buildQuerySubject($refund): array
    {
        $commandSubject = $this->serializer->unserialize((string)$refund->getAdditionalInformation());
        if (!is_array($commandSubject)) {
            throw new LocalizedException(
                __('ZaloPay refund row #%1 has a malformed stored query payload.', (int)$refund->getId())
            );
        }
        $commandSubject[AbstractDataBuilder::TIMESTAMP] = $this->dateTime->timestamp() * 1000;
        //Remove old Mac
        unset($commandSubject[AbstractDataBuilder::MAC]);
        $commandSubject[AbstractDataBuilder::MAC] = $this->authorization->getMac($commandSubject);

        return $commandSubject;
    }

    /**
     * Safe read of the provider return_code (TASK-CG6BM7): a missing or
     * non-numeric code is a protocol anomaly — pending, not a crash.
     *
     * @param array $response
     * @return int|null
     */
    private function readReturnCode(array $response): ?int
    {
        $code = $response[AbstractResponseValidator::RETURN_CODE] ?? null;

        return is_numeric($code) ? (int)$code : null;
    }

    /**
     * Safe, provider-map-based failure text for evidence (never raw
     * provider/transport detail).
     *
     * @param array $response
     * @return string
     */
    private function safeFailMessage(array $response): string
    {
        $subCode = $response[AbstractResponseValidator::SUB_RETURN_CODE] ?? null;

        return is_numeric($subCode)
            ? RefundProcessor::processRefundStatus((int)$subCode)
            : RefundProcessor::processRefundStatus(AbstractResponseValidator::REFUND_FAIL);
    }

    /**
     * Get unprocessed refund collection
     *
     * @return RefundCollection
     */
    public function getUnprocessedRefunds(): RefundCollection
    {
        // Get a collection of refunds where 'is_processed' is false AND the
        // bounded query budget is not exhausted (terminal-FAIL rows saturate
        // query_attempts, so they drop out of selection by design).
        $refundCollection = $this->refundCollectionFactory->create();
        $refundCollection->addFieldToFilter('is_processed', ['eq' => RefundInterface::NOT_PROCESSED]);
        $refundCollection->addFieldToFilter(RefundInterface::QUERY_ATTEMPTS, ['lt' => self::MAX_QUERY_ATTEMPTS]);

        return $refundCollection;
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
