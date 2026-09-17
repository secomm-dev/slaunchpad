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
 *  - malformed payload / missing creditmemo / manually-canceled creditmemo
 *    -> TERMINAL reconcile: budget saturated with safe evidence (never a
 *    silent loop).
 *
 * ROUND 6 F26: the LOCAL_READY -> PROVIDER_REQUEST_STARTED boundary makes
 * provider I/O provable from the durable state: a FRESH INITIATING row
 * (created_at within LOCAL_READY_GRACE_SECONDS) may still be owned by a
 * live request inside the local bind phase - the cron leaves it COMPLETELY
 * untouched (no query, no terminate, no release). A STALE INITIATING row
 * (age >= grace) proves the owner crashed before provider-start - provider
 * I/O was impossible by construction, so it is released CONFIRMED_FAIL,
 * never queried, bound or not; a PROVIDER_REQUEST_STARTED
 * row is left untouched during the reconciliation grace (grace > HTTP
 * timeout) and queried by the SAME m_refund_id after it; PROCESSING and
 * UNKNOWN rows query the SAME m_refund_id regardless of the creditmemo
 * state; PROVIDER_SUCCESS_LOCAL_PENDING finalizes locally only.
 *
 * ROUND 7: the selection is state-explicit (F32) - only the five
 * unresolved states are picked, budget-exhausted rows drop out EXCEPT
 * provider_success_local_pending, which stays selectable FOREVER (its
 * local finalize must never be starved by the query budget; the provider
 * is never contacted for it). Budget exhaustion NEVER mutates
 * refund_state (F32) and terminal rows (confirmed_success /
 * confirmed_fail) are skipped defensively at the top of processRefund
 * (F33) - they are never re-queried and never re-finalized even if a
 * stale selection or manual query hands one to the loop. On a
 * provider-confirmed FAIL the row is terminated confirmed_fail and the
 * credit memo is NOT touched (F34): no custom PROCESSING parking exists
 * anymore, the credit memo is OPEN until a successful finalize refunds
 * it natively; the money provably never left, accounting untouched.
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
        // -1. TERMINAL rows (round 7 F33 defense-in-depth): a row that
        //     already landed confirmed_success / confirmed_fail must NEVER
        //     re-enter the provider query flow - not queried, not
        //     finalized, not terminated again, no budget consumed. The
        //     explicit selection filter already excludes them; this guard
        //     holds even when a stale selection snapshot or a manual call
        //     hands a terminal row to the loop.
        $refundState = (string)$refund->getData(RefundInterface::REFUND_STATE);
        if ($refundState === RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS
            || $refundState === RefundInterface::REFUND_STATE_CONFIRMED_FAIL) {
            $this->logger->info(
                sprintf(
                    'ZaloPay refund row #%d: terminal state %s - skipped, never re-queried or re-finalized.',
                    (int)$refund->getId(),
                    $refundState
                )
            );

            return;
        }

        // 0. PROVIDER-SUCCESS/LOCAL-PENDING (round 3 F13): the provider
        //    money is out (CONFIRMED SUCCESS) but the Magento accounting is
        //    incomplete - finalize LOCALLY ONLY. No provider interaction
        //    (neither /refund nor query_refund) may ever run for this row.
        if ((string)$refund->getData(RefundInterface::REFUND_STATE)
            === RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING) {
            try {
                $this->pendingRefundManager->finalizeSuccess($refund);
                $this->logger->info(
                    sprintf('ZaloPay locally-pending refund finalized for credit memo ID %d.', (int)$refund->getCreditMemoId())
                );
            } catch (LocalizedException $exception) {
                // Accounting still pending (rolled back): consume one budget
                // unit with safe evidence - bounded, retried next run.
                $attempts = $this->pendingRefundManager->consumeQueryBudget(
                    $refund,
                    PendingRefundManager::EVIDENCE_RECONCILE . $exception->getMessage()
                );
                $this->logger->critical(
                    sprintf(
                        'ZaloPay refund row #%d: local finalize still pending (%s) - provider money is refunded.',
                        (int)$refund->getId(),
                        $exception->getMessage()
                    )
                );
                $this->logBudgetIfExhausted($refund, $attempts);
            }

            return;
        }

        // 0b. LOCAL_READY GRACE (round 6 F26): LOCAL_READY (initiating) =
        //    the provider has DEFINITELY not been contacted. A FRESH
        //    claim may be live: request A is inside the local bind phase
        //    (acquireClaim -> save Credit Memo -> bindCreditMemo ->
        //    markProviderRequestStarted) - a purely local, DB-only path.
        //    Terminating on sight would KILL that request's claim and
        //    persist an unnecessary OPEN Credit Memo. So the cron is
        //    patient first: age < LOCAL_READY_GRACE_SECONDS (300s,
        //    anchored on the persisted created_at UTC of the claim row -
        //    never in-memory time) -> complete no-op (no query, no
        //    terminate, no release). A STALE row (age >= grace) proves
        //    the owner crashed before provider-start; there provider I/O
        //    was impossible by construction, so CONFIRMED_FAIL release
        //    is truthful and safe. A missing created_at cannot be
        //    age-checked: consume one budget unit (bounded, never
        //    queries, never releases a live claim on a data glitch).
        if ((string)$refund->getData(RefundInterface::REFUND_STATE)
            === RefundInterface::REFUND_STATE_INITIATING) {
            $readyAt = (string)$refund->getData(RefundInterface::CREATED_AT);
            if ($readyAt === '') {
                $this->pendingRefundManager->consumeQueryBudget(
                    $refund,
                    PendingRefundManager::EVIDENCE_RECONCILE . 'local-ready timestamp missing'
                );
                $this->logger->critical(
                    sprintf(
                        'ZaloPay refund row #%d: LOCAL_READY without created_at - budget consumed, no query, claim NOT released (manual check advised).',
                        (int)$refund->getId()
                    )
                );

                return;
            }

            $readyTs = (int)(new \DateTime($readyAt, new \DateTimeZone('UTC')))->format('U');
            if ($this->dateTime->timestamp() < $readyTs + PendingRefundManager::LOCAL_READY_GRACE_SECONDS) {
                $this->logger->info(
                    sprintf(
                        'ZaloPay refund row #%d: LOCAL_READY claim fresh (created %s) - inside local-ready grace, untouched.',
                        (int)$refund->getId(),
                        $readyAt
                    )
                );

                return;
            }

            $this->pendingRefundManager->terminate(
                $refund,
                PendingRefundManager::EVIDENCE_ABANDONED
                . 'stale LOCAL_READY claim - provider I/O impossible by construction',
                RefundInterface::REFUND_STATE_CONFIRMED_FAIL
            );
            $this->logger->critical(
                sprintf(
                    'ZaloPay refund row #%d (order %d): stale LOCAL_READY claim (created %s) - released before provider I/O.',
                    (int)$refund->getId(),
                    (int)$refund->getOrderId(),
                    $readyAt
                )
            );

            return;
        }

        // 0c. PROVIDER-START GRACE (round 5 F23): the row crossed the
        //    LOCAL_READY → PROVIDER_REQUEST_STARTED boundary, so provider
        //    HTTP may run (or have run). While the request may STILL be in
        //    flight (now < started_at + grace; grace 120s > the 10s HTTP
        //    timeout), the cron does NOTHING to the row: no query, no
        //    budget consumption, no release (request A owns its claim
        //    until its provider request completed/timed out). After the
        //    grace: recovery queries the SAME m_refund_id. A missing
        //    timestamp cannot be grace-checked: consume one budget unit
        //    with reconcile evidence (bounded, never queries, never
        //    releases).
        if ((string)$refund->getData(RefundInterface::REFUND_STATE)
            === RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED) {
            $startedAt = (string)$refund->getData(RefundInterface::PROVIDER_REQUEST_STARTED_AT);
            if ($startedAt === '') {
                $this->pendingRefundManager->consumeQueryBudget(
                    $refund,
                    PendingRefundManager::EVIDENCE_RECONCILE . 'provider request start timestamp missing'
                );
                $this->logger->critical(
                    sprintf(
                        'ZaloPay refund row #%d: provider-start without timestamp - budget consumed, no query (manual check advised).',
                        (int)$refund->getId()
                    )
                );

                return;
            }

            $startedTs = (int)(new \DateTime($startedAt, new \DateTimeZone('UTC')))->format('U');
            if ($this->dateTime->timestamp() < $startedTs + PendingRefundManager::RECONCILIATION_GRACE_SECONDS) {
                $this->logger->info(
                    sprintf(
                        'ZaloPay refund row #%d: provider request started %s - within reconciliation grace, not queried.',
                        (int)$refund->getId(),
                        $startedAt
                    )
                );

                return;
            }
        }

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

        // 2a. Magento already REFUNDED the creditmemo (e.g. crash between
        //     the core accounting and the local row update after a sync
        //     SUCCESS): the refund is DONE in Magento - land the row
        //     bookkeeping (confirmed_success, claim released). Accounting is
        //     never re-run and the row never blocks a future refund.
        if ((int)$creditMemo->getState() === Creditmemo::STATE_REFUNDED) {
            $this->pendingRefundManager->markConfirmedSuccess($refund);
            $this->logger->info(
                sprintf(
                    'ZaloPay refund row #%d: credit memo #%d already REFUNDED in Magento - row resolved as success.',
                    (int)$refund->getId(),
                    (int)$refund->getCreditMemoId()
                )
            );

            return;
        }

        // 2b. STATE-DRIVEN RECOVERY (round 4 F18): the durable refund state
        //    machine decides what may be queried - NOT the creditmemo
        //    state. OPEN (1) is the only legitimate parked state now
        //    (round 7 F34: no custom PROCESSING creditmemo state exists
        //    anymore - the credit memo stays OPEN until a confirmed
        //    SUCCESS refunds it natively): INITIATING / PROCESSING /
        //    UNKNOWN rows carrying an OPEN creditmemo MUST query the SAME
        //    m_refund_id below (crash recovery by identity). Only a
        //    CANCELED creditmemo (a manual cancel outside this lifecycle)
        //    is drift - terminate for manual reconciliation (never
        //    double-finalize).
        if ((int)$creditMemo->getState() === Creditmemo::STATE_CANCELED) {
            $this->pendingRefundManager->terminate(
                $refund,
                PendingRefundManager::EVIDENCE_RECONCILE
                . sprintf('credit memo state is %d (manual cancel - drift)', (int)$creditMemo->getState())
            );
            $this->logger->critical(
                sprintf(
                    'ZaloPay refund row #%d: credit memo #%d manually canceled (state %d) - row terminated for manual reconciliation.',
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
        //    Magento accounting untouched). The credit memo is deliberately
        //    NOT mutated (round 7 F34): no custom PROCESSING parking exists
        //    anymore, the credit memo was never parked by this lifecycle, so
        //    there is nothing to release - it stays OPEN and a corrected
        //    future refund can proceed; the row is terminated confirmed_fail
        //    via the CAS transition (a lost CAS - a newer owner moved on -
        //    is swallowed inside the manager).
        if ($statusCode === AbstractResponseValidator::REFUND_FAIL) {
            $failMessage = $this->safeFailMessage($response);
            $this->pendingRefundManager->terminate(
                $refund,
                PendingRefundManager::EVIDENCE_REFUND_FAILED . $failMessage,
                RefundInterface::REFUND_STATE_CONFIRMED_FAIL
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
     * Get the refund rows the cron must work on (round 7 F32): an explicit
     * STATE selection of the five unresolved states only -
     * initiating / provider_request_started / processing / unknown /
     * provider_success_local_pending - AND not processed AND (budget not
     * exhausted OR provider_success_local_pending). Terminal states
     * (confirmed_success / confirmed_fail) are excluded BY THE STATE
     * FILTER, not by budget saturation; a budget-exhausted row keeps its
     * semantic state (F32: PSLP is never demoted to unknown at the cap)
     * and drops out of selection unless it is PSLP - a PSLP row stays
     * selectable FOREVER so its local-only finalize is never starved.
     *
     * @return RefundCollection
     */
    public function getUnprocessedRefunds(): RefundCollection
    {
        $refundCollection = $this->refundCollectionFactory->create();
        $refundCollection->addFieldToFilter(RefundInterface::IS_PROCESSED, ['eq' => RefundInterface::NOT_PROCESSED]);
        $refundCollection->addFieldToFilter(
            RefundInterface::REFUND_STATE,
            ['in' => [
                RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                RefundInterface::REFUND_STATE_PROCESSING,
                RefundInterface::REFUND_STATE_UNKNOWN,
                RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
            ]]
        );
        // The two-array form ORs the two conditions (Magento
        // AbstractCollection semantics): budget left, OR PSLP (uncapped).
        $refundCollection->addFieldToFilter(
            [RefundInterface::QUERY_ATTEMPTS, RefundInterface::REFUND_STATE],
            [
                [['lt' => self::MAX_QUERY_ATTEMPTS]],
                [['eq' => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING]],
            ]
        );

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
