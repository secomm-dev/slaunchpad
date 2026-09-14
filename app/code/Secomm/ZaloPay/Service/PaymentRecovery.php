<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Store\Model\ScopeInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Exception\ContractMismatchException;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollectionFactory;
use Zend_Db_Expr;

/**
 * Bounded proactive recovery for LOST callbacks (corrective round 3,
 * Blocker 6) — implements the OFFICIAL ZaloPay guidance
 * (https://docs.zalopay.vn/docs/developer-tools/knowledge-base/callback/,
 * "Retry": "After 15 minutes from the time of the order establishment, if
 * you still do not receive a callback from ZaloPay, the merchant needs to
 * call QueryOrder API proactively to get the final result.").
 *
 * Smallest bounded worker, driven by a cron job:
 *  - SELECTION (deterministic, no locks): non-terminal attempts (ACTIVE/
 *    PAID) with no bound order, NOT quarantined, past the documented
 *    callback window, ordered by entity_id, LIMIT batch size;
 *  - CLAIM: one atomic conditional UPDATE per attempt increments
 *    recovery_attempts BEFORE any HTTP — a lost race (IPN/Return mutated
 *    the row concurrently) skips the attempt; no DB lock is ever held
 *    across the ZaloPay HTTP call;
 *  - VERIFICATION: the authoritative v2/query runs OUTSIDE any transaction;
 *  - OUTCOMES through the SAME business services as IPN/Return — never a
 *    duplicate order-placement implementation:
 *      return_code 1 + EXACT amount -> PaymentAttemptLifecycle::
 *      recordVerifiedPaid -> OrderFinalizer::finalizeOrRecover (exactly one
 *      order; quarantined rows are refused by the finalizer itself);
 *      return_code 1 + missing/wrong amount -> recordAmountMismatch
 *      (quarantine, NO order);
 *      return_code 3 (processing) -> no mutation, retried on a later run;
 *      return_code 2 (FAIL) -> recordVerifiedFailure (concurrency-safe);
 *      anything else -> logged, left for a later run;
 *  - BOUNDS: batch size, max recovery attempts per attempt row and the
 *    callback window are configuration. Exhaustion is EXPLICIT (corrective
 *    round 4, Blocker 4): the claim that consumes the last permitted query
 *    also persists the machine-readable `recovery_exhausted` marker and
 *    logs it — exhausted rows are never selected or claimed again. The
 *    marker is OPERATIONAL ONLY: exhaustion is NOT money-real evidence, it
 *    never quarantines the attempt and a valid authenticated IPN can still
 *    resolve the payment normally afterwards.
 */
class PaymentRecovery
{
    /** Documented ZaloPay lost-callback window (minutes). */
    public const DEFAULT_WINDOW_MINUTES = 15;

    /** Deterministic page size for one cron run. */
    public const DEFAULT_BATCH_SIZE = 25;

    /** Hard cap of proactive queries per attempt row. */
    public const DEFAULT_MAX_ATTEMPTS = 5;

    public const XML_PATH_WINDOW = 'payment/zalopay/recovery_window';
    public const XML_PATH_BATCH_SIZE = 'payment/zalopay/recovery_batch_size';
    public const XML_PATH_MAX_ATTEMPTS = 'payment/zalopay/recovery_max_attempts';

    private const TABLE_PAYMENT_ATTEMPT = 'secomm_zalopay_payment_attempt';

    /**
     * PaymentRecovery constructor.
     *
     * @param PaymentAttemptCollectionFactory $collectionFactory
     * @param ResourceConnection $resourceConnection
     * @param CommandPoolInterface $commandPool
     * @param PaymentAttemptLifecycle $lifecycle
     * @param OrderFinalizer $orderFinalizer
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     */
    public function __construct(
        private readonly PaymentAttemptCollectionFactory $collectionFactory,
        private readonly ResourceConnection              $resourceConnection,
        private readonly CommandPoolInterface            $commandPool,
        private readonly PaymentAttemptLifecycle         $lifecycle,
        private readonly OrderFinalizer                  $orderFinalizer,
        private readonly ScopeConfigInterface            $scopeConfig,
        private readonly Logger                          $logger
    ) {
    }

    /**
     * Run one bounded recovery pass.
     *
     * @return array Claim/summary counters (cron logging).
     */
    public function execute(): array
    {
        $summary = ['claimed' => 0, 'finalized' => 0, 'failed' => 0, 'mismatch' => 0, 'processing' => 0, 'errors' => 0];
        foreach ($this->selectCandidates() as $attempt) {
            if (!$this->claim((int)$attempt->getEntityId())) {
                // Lost the claim race (IPN/Return/cron mutated the row
                // between selection and claim) — the fresh state owns it.
                continue;
            }
            $summary['claimed']++;

            try {
                $this->recoverAttempt($attempt, $summary);
            } catch (\Throwable $exception) {
                $summary['errors']++;
                $this->logger->error(
                    'ZaloPay recovery: attempt processing failed; left for a later run.',
                    [
                        'app_trans_id' => (string)$attempt->getAppTransId(),
                        'reason' => $exception->getMessage(),
                    ]
                );
            }
        }

        return $summary;
    }

    /**
     * Deterministic candidate selection: bounded page, stable order, no
     * row locks (the atomic claim decides who acts).
     *
     * @return PaymentAttemptInterface[]
     */
    private function selectCandidates(): array
    {
        $cutoff = date('Y-m-d H:i:s', (int)(time() - $this->getWindowMinutes() * 60));
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(
            PaymentAttemptInterface::PAYMENT_STATUS,
            ['in' => [PaymentAttemptInterface::STATUS_ACTIVE, PaymentAttemptInterface::STATUS_PAID]]
        );
        $collection->addFieldToFilter(PaymentAttemptInterface::ORDER_ID, ['null' => true]);
        $collection->addFieldToFilter(PaymentAttemptInterface::REQ_RECONCILIATION, ['neq' => 1]);
        $collection->addFieldToFilter(PaymentAttemptInterface::RECOVERY_ATTEMPTS, ['lt' => $this->getMaxAttempts()]);
        // Round 4, Blocker 4: explicitly exhausted rows are never selected
        // again (belt-and-braces next to the attempts-cap filter above).
        $collection->addFieldToFilter(PaymentAttemptInterface::RECOVERY_EXHAUSTED, ['neq' => 1]);
        $collection->addFieldToFilter(PaymentAttemptInterface::CREATED_AT, ['lteq' => $cutoff]);
        $collection->setOrder(PaymentAttemptInterface::ENTITY_ID, 'ASC');
        $collection->setPageSize($this->getBatchSize());

        return array_values($collection->getItems());
    }

    /**
     * Atomic claim BEFORE the HTTP call: a conditional UPDATE increments
     * recovery_attempts only while the row is still non-terminal, unbound,
     * un-quarantined, not exhausted and under the attempt cap. Returns
     * false when another writer won. No transaction is held across the
     * provider HTTP.
     *
     * Round 4, Blocker 4: the SAME statement persists the explicit
     * `recovery_exhausted` marker when this claim consumes the last
     * permitted query. MySQL evaluates SET assignments left to right and
     * the second assignment reads the ALREADY-INCREMENTED
     * recovery_attempts (documented single-table UPDATE behaviour), so the
     * marker flips to 1 exactly on the final allowed claim. Exhaustion is
     * an OPERATIONAL marker only — never money-real evidence, never a
     * quarantine: a valid later IPN still resolves the payment.
     *
     * @param int $entityId
     * @return bool
     */
    private function claim(int $entityId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE_PAYMENT_ATTEMPT);
        // All interpolated values are internal ints/identifiers — no user
        // input reaches this statement.
        $where = sprintf(
            '%1$s = %2$d AND %3$s IN (\'%4$s\', \'%5$s\') AND %6$s < %7$d'
            . ' AND %8$s IS NULL AND %9$s = 0 AND %10$s = 0',
            PaymentAttemptInterface::ENTITY_ID,
            $entityId,
            PaymentAttemptInterface::PAYMENT_STATUS,
            PaymentAttemptInterface::STATUS_ACTIVE,
            PaymentAttemptInterface::STATUS_PAID,
            PaymentAttemptInterface::RECOVERY_ATTEMPTS,
            $this->getMaxAttempts(),
            PaymentAttemptInterface::ORDER_ID,
            PaymentAttemptInterface::REQ_RECONCILIATION,
            PaymentAttemptInterface::RECOVERY_EXHAUSTED
        );
        $affected = $connection->update(
            $table,
            [
                PaymentAttemptInterface::RECOVERY_ATTEMPTS => new Zend_Db_Expr(
                    PaymentAttemptInterface::RECOVERY_ATTEMPTS . ' + 1'
                ),
                PaymentAttemptInterface::RECOVERY_EXHAUSTED => new Zend_Db_Expr(
                    sprintf(
                        'IF(%1$s >= %2$d, 1, %3$s)',
                        PaymentAttemptInterface::RECOVERY_ATTEMPTS,
                        $this->getMaxAttempts(),
                        PaymentAttemptInterface::RECOVERY_EXHAUSTED
                    )
                ),
            ],
            $where
        );
        if (!$affected) {
            return false;
        }
        $this->logExhaustionOnceRowIsExhausted($entityId);

        return true;
    }

    /**
     * Explicit, observable exhaustion evidence (round 4, Blocker 4): when
     * the claimed row has consumed its full proactive query budget, log it
     * critically — the `recovery_exhausted` column is the machine-readable
     * marker, this is the log half of the evidence. The row is never
     * selected or claimed again; a valid authenticated IPN can still
     * resolve it.
     *
     * @param int $entityId
     * @return void
     */
    private function logExhaustionOnceRowIsExhausted(int $entityId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $attempts = (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName(self::TABLE_PAYMENT_ATTEMPT),
                    PaymentAttemptInterface::RECOVERY_ATTEMPTS)
                ->where(PaymentAttemptInterface::ENTITY_ID . ' = ?', $entityId)
        );
        if ($attempts < $this->getMaxAttempts()) {
            return;
        }
        $this->logger->critical(
            'ZaloPay recovery: query budget exhausted for payment attempt — explicit recovery_exhausted '
            . 'marker persisted; NO further proactive queries (a valid callback still resolves the payment).',
            ['entity_id' => $entityId, 'recovery_attempts' => $attempts]
        );
    }

    /**
     * Authoritative query + canonical outcome for one claimed attempt.
     *
     * @param PaymentAttemptInterface $attempt The SELECTION copy (id/reference only — the
     *        lifecycle/finalizer re-lock the FRESH row for every mutation).
     * @param array $summary
     * @return void
     * @throws LocalizedException
     */
    private function recoverAttempt(PaymentAttemptInterface $attempt, array &$summary): void
    {
        $appTransId = (string)$attempt->getAppTransId();
        $query = $this->queryTransaction($appTransId);
        $returnCode = (int)($query[AbstractResponseValidator::RETURN_CODE] ?? 0);

        if ($returnCode === 3) {
            // Still processing — non-terminal, a later run queries again.
            $summary['processing']++;

            return;
        }

        if ($returnCode !== AbstractResponseValidator::RETURN_CODE_ACCEPT) {
            // Authoritative FAIL: concurrency-safe failure where the fresh
            // state permits (PAID/FINALIZED are never regressed).
            $fresh = $this->lifecycle->recordVerifiedFailure(
                $appTransId,
                sprintf('Recovery v2/query return_code %d.', $returnCode),
                'failed'
            );
            if ($fresh->getPaymentStatus() === PaymentAttemptInterface::STATUS_FAILED) {
                $summary['failed']++;
            }

            return;
        }

        // Authoritative PAID: the amount is REQUIRED — missing/zero or wrong
        // amount quarantines (money-real, no order); EXACT amount drives the
        // canonical PAID -> finalize path (same services as IPN/Return).
        $paidAmount = (int)($query[AbstractResponseValidator::TOTAL_AMOUNT] ?? 0);
        $zpTransId = (string)($query[AbstractResponseValidator::ZP_TRANS_ID] ?? '');

        if ($paidAmount !== (int)$attempt->getAmount()) {
            $this->logger->critical(
                'ZaloPay recovery amount mismatch: quarantined for reconciliation, no order placed.',
                [
                    'app_trans_id' => $appTransId,
                    'paid_amount' => $paidAmount,
                    'snapshot_amount' => (int)$attempt->getAmount(),
                ]
            );
            $this->lifecycle->recordAmountMismatch(
                $appTransId,
                $paidAmount,
                'Recovery',
                $zpTransId !== '' ? $zpTransId : null
            );
            $summary['mismatch']++;

            return;
        }

        $fresh = $this->lifecycle->recordVerifiedPaid($appTransId, $zpTransId !== '' ? $zpTransId : null);
        if ($fresh->getRequiresReconciliation()
            || ($fresh->getPaymentStatus() !== PaymentAttemptInterface::STATUS_PAID
                && $fresh->getPaymentStatus() !== PaymentAttemptInterface::STATUS_FINALIZED)
        ) {
            // Quarantined (e.g. conflicting zp_trans_id) or money-real
            // evidence on a terminal attempt — never an order.
            $summary['mismatch']++;

            return;
        }

        try {
            $this->orderFinalizer->finalizeOrRecover($fresh, $zpTransId);
            $summary['finalized']++;
        } catch (ContractMismatchException $exception) {
            // Deterministic refusal — already quarantined with evidence by
            // the lifecycle; never retried into an order.
            $summary['mismatch']++;
        }
    }

    /**
     * Run the authoritative v2/query (OUTSIDE any DB transaction).
     *
     * @param string $appTransId
     * @return array
     * @throws LocalizedException
     */
    private function queryTransaction(string $appTransId): array
    {
        try {
            $result = $this->commandPool->get('query_transaction')->execute(
                [AbstractResponseValidator::TRANSACTION_ID => $appTransId]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'ZaloPay v2/query failed during recovery: ' . $e->getMessage(),
                ['app_trans_id' => $appTransId]
            );
            throw new LocalizedException(__('ZaloPay payment could not be verified right now.'));
        }

        return $result->get();
    }

    /**
     * @return int
     */
    private function getWindowMinutes(): int
    {
        return max(1, (int)($this->scopeConfig->getValue(self::XML_PATH_WINDOW) ?: self::DEFAULT_WINDOW_MINUTES));
    }

    /**
     * @return int
     */
    private function getBatchSize(): int
    {
        return max(1, (int)($this->scopeConfig->getValue(self::XML_PATH_BATCH_SIZE) ?: self::DEFAULT_BATCH_SIZE));
    }

    /**
     * @return int
     */
    private function getMaxAttempts(): int
    {
        return max(1, (int)($this->scopeConfig->getValue(self::XML_PATH_MAX_ATTEMPTS) ?: self::DEFAULT_MAX_ATTEMPTS));
    }
}
