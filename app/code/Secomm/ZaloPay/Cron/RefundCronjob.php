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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Exception\RefundTransportException;
use Secomm\ZaloPay\Gateway\Command\RefundQueryCommand;
use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Logger\Logger as LoggerInterface;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;
use Secomm\ZaloPay\Helper\RefundProcessor;
use Secomm\ZaloPay\Plugin\Model\Order\CreditmemoPlugin;
use Secomm\ZaloPay\Service\PendingRefundManager;

/**
 * RefundCronjob - bounded, terminal-explicit poller for async ZaloPay
 * refunds (TASK-CG6BM7 corrective round).
 *
 * Every NOT_PROCESSED refund row within the bounded query budget is queried
 * via v2/query_refund and resolved to exactly one classification:
 *
 *  - return_code 1  -> SUCCESS: finalized exactly once through the NATIVE
 *    core accounting (PendingRefundManager::finalizeSuccess - order totals,
 *    payment transactions, creditmemo REFUNDED; full refunds close the order
 *    through the core StateResolver);
 *  - return_code 2  -> TERMINAL FAIL: budget saturated, safe provider-map
 *    message kept as evidence, never queried again, Magento accounting
 *    untouched;
 *  - transport error -> RETRYABLE: consumes exactly one budget unit with
 *    safe evidence (BLOCKER 2 fix - every genuine attempt progresses state);
 *  - return_code 3 / protocol anomaly -> still pending: consumes one budget
 *    unit, retried until the cap, then explicitly exhausted;
 *  - malformed payload / missing creditmemo / creditmemo state drift
 *    -> TERMINAL reconcile: budget saturated with safe evidence (never a
 *    silent loop).
 *
 * Items are isolated: one broken row never blocks the batch. The finalize
 * step itself is guarded by SELECT ... FOR UPDATE + is_processed re-check
 * (exactly once) inside PendingRefundManager.
 */
class RefundCronjob
{
    /**
     * Payment method active config path.
     */
    private const XML_PATH_ACTIVE = 'payment/zalopay/active';

    /**
     * Bounded v2/query_refund budget: 96 runs on the 15-minute schedule
     * (~24h) - defined by PendingRefundManager, shared with the plugin
     * in-flight guard so terminal rows drop out of BOTH the selection and
     * the guard.
     */
    public const MAX_QUERY_ATTEMPTS = PendingRefundManager::MAX_QUERY_ATTEMPTS;

    /**
     * @param RefundCollectionFactory $refundCollectionFactory
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param LoggerInterface $logger
     * @param RefundQueryCommand $refundQueryCommand
     * @param DateTime $dateTime
     * @param Authorization $authorization
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $serializer
     * @param PendingRefundManager $pendingRefundManager
     */
    public function __construct(
        private readonly RefundCollectionFactory       $refundCollectionFactory,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly LoggerInterface               $logger,
        private readonly RefundQueryCommand            $refundQueryCommand,
        private readonly DateTime                      $dateTime,
        private readonly Authorization                 $authorization,
        private readonly ScopeConfigInterface          $scopeConfig,
        private readonly Json                          $serializer,
        private readonly PendingRefundManager          $pendingRefundManager
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
                // Per-item isolation (last resort): one broken item never
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
     * Resolve ONE refund row against the provider to exactly one
     * classification (terminal or bounded-retryable).
     *
     * @param \Secomm\ZaloPay\Model\RefundModel $refund
     * @return void
     */
    private function processRefund($refund): void
    {
        // 1. The credit memo must exist (missing = terminal reconcile).
        try {
            $creditMemo = $this->creditmemoRepository->get((int)$refund->getCreditMemoId());
        } catch (NoSuchEntityException $exception) {
            $this->pendingRefundManager->terminate(
                $refund,
                PendingRefundManager::EVIDENCE_RECONCILE . 'credit memo missing'
            );
            $this->logger->critical(
                sprintf(
                    'ZaloPay refund row #%d: credit memo #%d is missing - row terminated for manual reconciliation.',
                    (int)$refund->getId(),
                    (int)$refund->getCreditMemoId()
                )
            );

            return;
        }

        // 2. Only credit memos parked in the PROCESSING state are ours to
        //    finalize: any other state means the refund was resolved outside
        //    this lifecycle (e.g. manually) - never double-finalize.
        if ((int)$creditMemo->getState() !== CreditmemoPlugin::STATE_PROCESSING) {
            $this->pendingRefundManager->terminate(
                $refund,
                PendingRefundManager::EVIDENCE_RECONCILE
                . sprintf('credit memo state is %d (expected %d)', (int)$creditMemo->getState(), CreditmemoPlugin::STATE_PROCESSING)
            );
            $this->logger->critical(
                sprintf(
                    'ZaloPay refund row #%d: credit memo #%d state drifted (%d) - row terminated for manual reconciliation.',
                    (int)$refund->getId(),
                    (int)$refund->getCreditMemoId(),
                    (int)$creditMemo->getState()
                )
            );

            return;
        }

        // 3. The stored reconciliation payload must be sane (malformed =
        //    terminal reconcile: the row can never be resolved without it).
        try {
            $commandSubject = $this->buildQuerySubject($refund);
        } catch (LocalizedException $exception) {
            $this->pendingRefundManager->terminate(
                $refund,
                PendingRefundManager::EVIDENCE_RECONCILE . 'malformed stored query payload'
            );
            $this->logger->critical(
                sprintf(
                    'ZaloPay refund row #%d: %s - row terminated for manual reconciliation.',
                    (int)$refund->getId(),
                    $exception->getMessage()
                )
            );

            return;
        }

        // 4. Query the provider: transport failures are retryable and consume
        //    exactly one budget unit (observable state progression).
        try {
            $response = $this->refundQueryCommand->getRefundQuery($commandSubject);
        } catch (RefundTransportException $exception) {
            $attempts = $this->pendingRefundManager->consumeQueryBudget(
                $refund,
                PendingRefundManager::EVIDENCE_TRANSPORT . $exception->getMessage()
            );
            $this->logBudgetIfExhausted($refund, $attempts);

            return;
        }

        $statusCode = $this->readReturnCode($response);

        // 5. SUCCESS: finalize exactly once through the native core accounting.
        if ($statusCode === AbstractResponseValidator::RETURN_CODE_ACCEPT) {
            try {
                $this->pendingRefundManager->finalizeSuccess($refund);
                $this->logger->info(
                    sprintf('ZaloPay refund finalized for credit memo ID %d.', (int)$refund->getCreditMemoId())
                );
            } catch (\Throwable $finalizeException) {
                // Local finalize failure (rolled back: no accounting applied):
                // consume budget with safe evidence so the attempt is
                // observable and bounded; retried on the next run.
                $attempts = $this->pendingRefundManager->consumeQueryBudget(
                    $refund,
                    PendingRefundManager::EVIDENCE_RECONCILE
                    . 'finalize failed: ' . $finalizeException->getMessage()
                );
                $this->logger->critical(
                    sprintf(
                        'ZaloPay refund row #%d: finalize failed (%s) - provider money is refunded, local accounting pending.',
                        (int)$refund->getId(),
                        $finalizeException->getMessage()
                    )
                );
                $this->logBudgetIfExhausted($refund, $attempts);
            }

            return;
        }

        // 6. Explicit provider FAIL: terminal (budget saturated, evidence kept,
        //    Magento accounting untouched).
        if ($statusCode === AbstractResponseValidator::REFUND_FAIL) {
            $failMessage = $this->safeFailMessage($response);
            $this->pendingRefundManager->terminate(
                $refund,
                PendingRefundManager::EVIDENCE_REFUND_FAILED . $failMessage
            );
            $this->logger->critical(
                sprintf(
                    'ZaloPay refund FAILED terminally for credit memo ID %d: %s',
                    (int)$refund->getCreditMemoId(),
                    $failMessage
                )
            );

            return;
        }

        // 7. Still pending: PROCESSING (3) or a protocol anomaly (missing /
        //    invalid return_code) - consume one budget unit; at the cap the
        //    row is explicitly exhausted.
        $evidence = $statusCode === AbstractResponseValidator::REFUND_PROCESSING
            ? null
            : PendingRefundManager::EVIDENCE_ANOMALY . 'missing or invalid provider return_code';
        $attempts = $this->pendingRefundManager->consumeQueryBudget($refund, $evidence);
        $this->logBudgetIfExhausted($refund, $attempts);
    }

    /**
     * Rebuild the v2/query_refund subject from the stored payload: refresh
     * the timestamp, drop the stale MAC, re-sign (official MAC keys:
     * app_id|m_refund_id|timestamp - the stored payload holds exactly these).
     *
     * @param \Secomm\ZaloPay\Model\RefundModel $refund
     * @return array
     * @throws LocalizedException Malformed stored payload.
     */
    private function buildQuerySubject($refund): array
    {
        try {
            $commandSubject = $this->serializer->unserialize((string)$refund->getAdditionalInformation());
        } catch (\InvalidArgumentException $exception) {
            throw new LocalizedException(
                __('ZaloPay refund row #%1 has a malformed stored query payload.', (int)$refund->getId())
            );
        }

        if (!is_array($commandSubject) || empty($commandSubject[RefundInterface::M_REFUND_ID])) {
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
     * Exhaustion is explicit: a critical log when the bounded budget is
     * consumed (the row then drops out of the selection filter by design).
     *
     * @param \Secomm\ZaloPay\Model\RefundModel $refund
     * @param int $attempts
     * @return void
     */
    private function logBudgetIfExhausted($refund, int $attempts): void
    {
        if ($attempts >= self::MAX_QUERY_ATTEMPTS) {
            $this->logger->critical(
                sprintf(
                    'ZaloPay refund query budget exhausted for credit memo ID %d after %d attempts - manual reconciliation required.',
                    (int)$refund->getCreditMemoId(),
                    $attempts
                )
            );
        }
    }

    /**
     * Safe read of the provider return_code (missing/non-numeric = anomaly).
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
        // bounded query budget is not exhausted (terminal rows saturate
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
