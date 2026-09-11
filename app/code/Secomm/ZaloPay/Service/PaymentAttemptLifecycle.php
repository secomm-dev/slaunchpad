<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Logger\Logger;

/**
 * CANONICAL, concurrency-safe payment-state mutation service.
 *
 * Every payment-state decision for an authoritative provider result (v2/query
 * result, verified IPN) goes through this class — the browser Return
 * processor and the IPN processor both delegate here and MUST NOT mutate
 * PaymentAttempt rows themselves. No divergent callback logic.
 *
 * Shape of every operation (review-corrective TASK-EDS9T5, Blocker 2):
 *  1. the remote/provider verification happened OUTSIDE — callers verify
 *     (MAC, v2/query HTTP) BEFORE calling; no ZaloPay HTTP runs in here;
 *  2. begin a short DB transaction;
 *  3. SELECT the exact attempt FOR UPDATE via lockByAppTransId();
 *  4. re-evaluate the CURRENT persisted status — a stale in-memory copy
 *     never decides (racing Return/IPN, TTL sweep, a superseding attempt);
 *  5. apply the legal transition or the evidence-only mutation
 *     (\Secomm\ZaloPay\Model\PaymentAttempt TRANSITIONS map guards every
 *     mark*() — terminal states have no outgoing edges, so a late PAID
 *     claim can never regress FAILED/STALE/EXPIRED/FINALIZED);
 *  6. save (only when something changed) and commit;
 *  7. return the FRESH locked attempt — callers branch on its status, never
 *     on the copy they held before.
 *
 * Money-real late callback states (FAILED/STALE/EXPIRED attempt +
 * authoritative PAID evidence): the evidence AND the provider transaction
 * identity are preserved (provider_transaction_id + last_error), the status
 * is NOT broadened, and no order may follow — callers only finalize from
 * PAID/FINALIZED rows.
 *
 * Reconciliation quarantine (corrective round 3, Blocker 5): states where
 * the money is real but an automatic Sales Order is STRUCTURALLY impossible
 * (verified amount mismatch, unavailable provider amount, conflicting
 * provider transaction id, broken order contract) set the structured
 * `requires_reconciliation` flag + machine-readable `reconciliation_code` —
 * never a free-text convention that callers must parse out of last_error.
 * A quarantined attempt can never be auto-finalized afterwards: the
 * OrderFinalizer refuses quarantined rows, and no generic callback, Return
 * or recovery query may clear the flag (only an explicit manual/
 * reconciliation workflow may).
 *
 * Contract-mismatch evidence ownership (corrective round 3, Blocker 2):
 * recordContractMismatch() is the ONLY persistence path for the finalizer's
 * refusal reason — it re-acquires the row lock and mutates the FRESH row.
 * The finalizer NEVER saves its (possibly stale pre-rollback) attempt copy
 * after releasing the lock. Evidence may never regress FINALIZED, never
 * clear order_id or provider_transaction_id.
 *
 * Callers still hand the attempt to \Secomm\ZaloPay\Service\OrderFinalizer
 * AFTER this service committed: the finalizer opens its own separate short
 * transaction (lock -> place/bind/capture -> commit), so no DB transaction
 * is ever held across the placement.
 */
class PaymentAttemptLifecycle
{
    /**
     * PaymentAttemptLifecycle constructor.
     *
     * @param PaymentAttemptRepositoryInterface $repository
     * @param ResourceConnection $resourceConnection
     * @param Logger $logger
     */
    public function __construct(
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly ResourceConnection                $resourceConnection,
        private readonly Logger                            $logger
    ) {
    }

    /**
     * Record an authoritative "provider confirmed payment" result.
     *
     * ACTIVE -> PAID on the LOCKED row; idempotent no-op when already
     * PAID/FINALIZED (a duplicate callback must never re-mutate). On any
     * other (non-payable) state the money-real evidence and the provider
     * transaction identity are preserved WITHOUT a status change — manual
     * reconciliation material, never an order.
     *
     * @param string $appTransId
     * @param string|null $providerTransactionId ZaloPay zp_trans_id (null/'' when unknown).
     * @return PaymentAttemptInterface The fresh locked attempt.
     * @throws LocalizedException Unknown app_trans_id or persistence failure.
     */
    public function recordVerifiedPaid(
        string $appTransId,
        ?string $providerTransactionId = null
    ): PaymentAttemptInterface {
        return $this->mutateLocked($appTransId, function (PaymentAttemptInterface $attempt) use (
            $appTransId,
            $providerTransactionId
        ): bool {
            // The same app_trans_id with a DIFFERENT authoritative
            // zp_trans_id can never silently drive (or re-drive) the
            // lifecycle — quarantined for manual reconciliation first
            // (corrective round 3, Blocker 5).
            if ($this->hasProviderTransactionConflict($attempt, $providerTransactionId)) {
                return $this->quarantineProviderTransactionConflict($attempt, (string)$providerTransactionId);
            }

            switch ($attempt->getPaymentStatus()) {
                case PaymentAttemptInterface::STATUS_FINALIZED:
                    // Terminal success: a duplicate claim changes nothing.
                    return false;
                case PaymentAttemptInterface::STATUS_PAID:
                    // Idempotent; backfill the provider id if an earlier
                    // verification recorded PAID without it.
                    return $this->backfillProviderTransactionId($attempt, $providerTransactionId);
                case PaymentAttemptInterface::STATUS_ACTIVE:
                    $attempt->markPaid($providerTransactionId !== '' ? $providerTransactionId : null);

                    return true;
                default:
                    // INITIATED/FAILED/STALE/EXPIRED: money-real evidence on a
                    // non-payable state — never broaden the terminal state.
                    return $this->recordTerminalPaidEvidence($attempt, $appTransId, $providerTransactionId);
            }
        });
    }

    /**
     * Record an authoritative "provider did NOT confirm payment" result.
     *
     * Only transitions when the fresh state permits (INITIATED/ACTIVE ->
     * FAILED). A PAID attempt is NEVER regressed by a later failure claim —
     * the conflict is recorded as evidence for manual reconciliation.
     * FINALIZED is untouched (an order exists; a failure claim is stale).
     *
     * @param string $appTransId
     * @param string $reason Customer-safe-free technical reason (persisted in last_error on transition).
     * @param string|null $providerStatus
     * @return PaymentAttemptInterface The fresh locked attempt.
     * @throws LocalizedException Unknown app_trans_id or persistence failure.
     */
    public function recordVerifiedFailure(
        string $appTransId,
        string $reason,
        ?string $providerStatus = 'failed'
    ): PaymentAttemptInterface {
        return $this->mutateLocked($appTransId, function (PaymentAttemptInterface $attempt) use (
            $reason,
            $providerStatus
        ): bool {
            switch ($attempt->getPaymentStatus()) {
                case PaymentAttemptInterface::STATUS_INITIATED:
                case PaymentAttemptInterface::STATUS_ACTIVE:
                    $attempt->markFailed($reason, $providerStatus);

                    return true;
                case PaymentAttemptInterface::STATUS_PAID:
                    // Money already verified by a prior authoritative proof —
                    // a failure claim must not regress it. STRUCTURED,
                    // STICKY quarantine (corrective round 4, Blocker 3):
                    // provider_state_conflict, so no later callback, Return,
                    // recovery run or Start can silently finalize past the
                    // contradiction — only an explicit manual reconciliation
                    // may resolve it.
                    $changed = $this->markRequiresReconciliation(
                        $attempt,
                        PaymentAttemptInterface::RECON_PROVIDER_STATE_CONFLICT
                    );
                    $message = sprintf(
                        'Authoritative failure evidence while attempt is "%s"; kept money-real for '
                        . 'manual reconciliation (%s).',
                        PaymentAttemptInterface::STATUS_PAID,
                        $reason
                    );
                    if ($attempt->getLastError() !== $message) {
                        $attempt->setLastError($message);
                        $changed = true;
                    }

                    // Aggregate: a flag flip false->true with last_error
                    // already identical must still persist (corrective
                    // round 5 — quarantine must never be lost).
                    return $changed;
                default:
                    // FAILED (idempotent) / FINALIZED / STALE / EXPIRED.
                    return false;
            }
        });
    }

    /**
     * Record a verified amount mismatch: the provider holds money for a
     * different amount than the persisted snapshot.
     *
     * The attempt is kept money-real (PAID when the fresh state permits) with
     * the exact mismatch as evidence — NEVER auto-placed. When the LOCKED row
     * actually matches the paid amount (a stale caller copy), the normal
     * verified-paid handling applies instead.
     *
     * @param string $appTransId
     * @param int $paidAmount Provider-confirmed amount (VND).
     * @param string $source Short caller label for the evidence ("IPN"/"Return").
     * @param string|null $providerTransactionId
     * @return PaymentAttemptInterface The fresh locked attempt.
     * @throws LocalizedException Unknown app_trans_id or persistence failure.
     */
    public function recordAmountMismatch(
        string $appTransId,
        int $paidAmount,
        string $source,
        ?string $providerTransactionId = null
    ): PaymentAttemptInterface {
        return $this->mutateLocked($appTransId, function (PaymentAttemptInterface $attempt) use (
            $appTransId,
            $paidAmount,
            $source,
            $providerTransactionId
        ): bool {
            if ($attempt->getAmount() === $paidAmount) {
                // The locked row matches — the caller compared against a stale
                // copy. Normal verified-paid handling, no mismatch evidence.
                return $this->applyVerifiedPaid($attempt, $appTransId, $providerTransactionId);
            }

            if ($this->hasProviderTransactionConflict($attempt, $providerTransactionId)) {
                return $this->quarantineProviderTransactionConflict($attempt, (string)$providerTransactionId);
            }

            if ($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_PAID)) {
                $attempt->markPaid($providerTransactionId !== '' ? $providerTransactionId : null);
            } else {
                $this->backfillProviderTransactionId($attempt, $providerTransactionId);
            }
            $this->markRequiresReconciliation($attempt, PaymentAttemptInterface::RECON_AMOUNT_MISMATCH);
            $message = sprintf(
                '%s amount mismatch: paid %d, snapshot %d.',
                $source,
                $paidAmount,
                $attempt->getAmount()
            );
            $attempt->setLastError($message);

            return true;
        });
    }

    /**
     * Record the OrderFinalizer's contract-mismatch refusal — corrective
     * round 3, Blocker 2.
     *
     * The ONLY persistence path for a refused finalization: the finalizer
     * rolls its transaction back and calls THIS instead of saving its
     * (possibly stale, pre-rollback) attempt copy. The row lock is
     * re-acquired here and the mutation applies to the FRESH state, so a
     * racing FINALIZED binding can never be regressed, an order_id or
     * provider_transaction_id can never be erased, and evidence always
     * lands on current data.
     *
     * Non-finalized rows get the structured `requires_reconciliation`
     * quarantine (code contract_mismatch) — a refused contract can never be
     * auto-finalized by a later generic callback. A FINALIZED row owns a
     * bound order: evidence only — the quarantine flag would poison an
     * already-placed order, and FINALIZED is never regressed.
     *
     * @param string $appTransId
     * @param string $reason The ContractMismatchException message.
     * @return PaymentAttemptInterface The fresh locked attempt.
     * @throws LocalizedException Unknown app_trans_id or persistence failure.
     */
    public function recordContractMismatch(string $appTransId, string $reason): PaymentAttemptInterface
    {
        return $this->mutateLocked($appTransId, function (PaymentAttemptInterface $attempt) use ($reason): bool {
            if ($attempt->getPaymentStatus() === PaymentAttemptInterface::STATUS_FINALIZED) {
                // Evidence only: never regress FINALIZED, never clear
                // order_id / provider_transaction_id, never quarantine a
                // bound order's attempt.
                $message = 'Contract mismatch (order already bound; manual review): ' . $reason;
                if ($attempt->getLastError() !== $message) {
                    $attempt->setLastError($message);

                    return true;
                }

                return false;
            }

            $changed = $this->markRequiresReconciliation(
                $attempt,
                PaymentAttemptInterface::RECON_CONTRACT_MISMATCH
            );
            $message = 'Contract mismatch — no automatic order creation: ' . $reason;
            if ($attempt->getLastError() !== $message) {
                $attempt->setLastError($message);

                return true;
            }

            return $changed;
        });
    }

    /**
     * Record a provider transaction IDENTITY CONFLICT between two
     * authoritative proofs of the SAME app_trans_id — corrective round 4,
     * Blocker 1c (callback zp_trans_id A vs v2/query zp_trans_id B, A ≠ B).
     *
     * Neither identity is silently preferred: the money-real state is kept
     * (PAID where the fresh state permits), the attempt is quarantined with
     * the provider_transaction_conflict code and BOTH identities are
     * preserved verbatim in the evidence — manual reconciliation decides.
     *
     * @param string $appTransId
     * @param string $firstId The first authoritative zp_trans_id (e.g. from the callback).
     * @param string $secondId The second authoritative zp_trans_id (e.g. from v2/query).
     * @param string $source Short caller label ("IPN").
     * @return PaymentAttemptInterface The fresh locked attempt.
     * @throws LocalizedException Unknown app_trans_id or persistence failure.
     */
    public function recordProviderIdentityConflict(
        string $appTransId,
        string $firstId,
        string $secondId,
        string $source
    ): PaymentAttemptInterface {
        return $this->mutateLocked($appTransId, function (PaymentAttemptInterface $attempt) use (
            $firstId,
            $secondId,
            $source
        ): bool {
            return $this->applyMoneyRealQuarantine(
                $attempt,
                PaymentAttemptInterface::RECON_PROVIDER_TX_CONFLICT,
                sprintf(
                    '%s provider transaction conflict: callback zp_trans_id %s vs v2/query zp_trans_id %s. '
                    . 'Both identities preserved; NOT auto-finalizable — manual reconciliation required.',
                    $source,
                    $firstId,
                    $secondId
                )
            );
        });
    }

    /**
     * Record verified money (v2/query SUCCESS + exact amount) whose provider
     * transaction identity could NOT be proven: neither the callback nor the
     * query carried a positive zp_trans_id (corrective round 4, Blocker 1b —
     * automatic finalization requires authoritative positive zp_trans_id).
     *
     * Money-real quarantine: PAID where the fresh state permits +
     * provider_transaction_unavailable — never an order until manual
     * reconciliation resolves the identity.
     *
     * @param string $appTransId
     * @param string $source Short caller label ("IPN-query").
     * @return PaymentAttemptInterface The fresh locked attempt.
     * @throws LocalizedException Unknown app_trans_id or persistence failure.
     */
    public function recordProviderIdentityUnavailable(
        string $appTransId,
        string $source
    ): PaymentAttemptInterface {
        return $this->mutateLocked($appTransId, function (PaymentAttemptInterface $attempt) use ($source): bool {
            return $this->applyMoneyRealQuarantine(
                $attempt,
                PaymentAttemptInterface::RECON_PROVIDER_TX_UNAVAILABLE,
                sprintf(
                    '%s verified payment without a positive zp_trans_id from callback or v2/query; '
                    . 'provider identity unproven — no automatic order, manual reconciliation required.',
                    $source
                )
            );
        });
    }

    /**
     * Money-real quarantine shared by the round-4 identity outcomes: keep
     * the money-real state (PAID where the fresh state permits), set the
     * structured sticky reconciliation code, persist the exact evidence.
     *
     * @param PaymentAttemptInterface $attempt The FRESH locked attempt.
     * @param string $code A PaymentAttemptInterface::RECON_* constant.
     * @param string $message Full evidence line for last_error.
     * @return bool Whether the row changed.
     */
    private function applyMoneyRealQuarantine(
        PaymentAttemptInterface $attempt,
        string $code,
        string $message
    ): bool {
        $changed = false;
        if ($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_PAID)) {
            // markPaid(null) never overwrites a recorded provider id.
            $attempt->markPaid(null);
            $changed = true;
        }
        $changed = $this->markRequiresReconciliation($attempt, $code) || $changed;
        if ($attempt->getLastError() !== $message) {
            $attempt->setLastError($message);

            return true;
        }

        return $changed;
    }

    /**
     * Shared verified-paid state machine on a LOCKED attempt.
     *
     * @param PaymentAttemptInterface $attempt
     * @param string $appTransId
     * @param string|null $providerTransactionId
     * @return bool Whether the row changed.
     */
    private function applyVerifiedPaid(
        PaymentAttemptInterface $attempt,
        string $appTransId,
        ?string $providerTransactionId
    ): bool {
        switch ($attempt->getPaymentStatus()) {
            case PaymentAttemptInterface::STATUS_FINALIZED:
                return false;
            case PaymentAttemptInterface::STATUS_PAID:
                return $this->backfillProviderTransactionId($attempt, $providerTransactionId);
            case PaymentAttemptInterface::STATUS_ACTIVE:
                $attempt->markPaid($providerTransactionId !== '' ? $providerTransactionId : null);

                return true;
            default:
                return $this->recordTerminalPaidEvidence($attempt, $appTransId, $providerTransactionId);
        }
    }

    /**
     * Evidence-only persistence of authoritative PAID proof on a non-payable
     * state: provider identity + reason preserved, status NOT broadened.
     *
     * @param PaymentAttemptInterface $attempt
     * @param string $appTransId
     * @param string|null $providerTransactionId
     * @return bool Whether the row changed.
     */
    private function recordTerminalPaidEvidence(
        PaymentAttemptInterface $attempt,
        string $appTransId,
        ?string $providerTransactionId
    ): bool {
        $this->logger->error(
            'ZaloPay authoritative PAID evidence on non-payable attempt state; recorded for manual reconciliation.',
            [
                'app_trans_id' => $appTransId,
                'payment_status' => $attempt->getPaymentStatus(),
                'attempt_id' => $attempt->getEntityId(),
            ]
        );
        // STRUCTURED + STICKY (corrective round 4, Blocker 3): the money is
        // real but the attempt is terminal — persist the machine-readable
        // late_paid_terminal_state quarantine so a Start can never open a
        // second provider transaction and no callback can auto-finalize it.
        $changed = $this->markRequiresReconciliation(
            $attempt,
            PaymentAttemptInterface::RECON_LATE_PAID_TERMINAL_STATE
        );
        $changed = $this->backfillProviderTransactionId($attempt, $providerTransactionId) || $changed;
        $message = sprintf(
            'Authoritative PAID evidence received while attempt is "%s"; kept for manual reconciliation%s.',
            $attempt->getPaymentStatus(),
            $providerTransactionId !== null && $providerTransactionId !== ''
                ? sprintf(' (zp_trans_id %s)', $providerTransactionId)
                : ''
        );
        if ($attempt->getLastError() !== $message) {
            $attempt->setLastError($message);
            $changed = true;
        }

        return $changed;
    }

    /**
     * Whether an authoritative proof claims a DIFFERENT zp_trans_id than the
     * one already persisted on the attempt (corrective round 3, Blocker 5).
     *
     * @param PaymentAttemptInterface $attempt The FRESH locked attempt.
     * @param string|null $providerTransactionId Incoming authoritative id.
     * @return bool
     */
    private function hasProviderTransactionConflict(
        PaymentAttemptInterface $attempt,
        ?string $providerTransactionId
    ): bool {
        return $providerTransactionId !== null && $providerTransactionId !== ''
            && $attempt->getProviderTransactionId() !== null
            && $attempt->getProviderTransactionId() !== $providerTransactionId;
    }

    /**
     * Quarantine a conflicting provider transaction id: the FIRST recorded
     * identity is NEVER overwritten (the earlier authoritative proof owns
     * it), the attempt is kept money-real (PAID where the fresh state
     * permits) and marked requires_reconciliation — it can never be
     * silently auto-finalized on the strength of the conflicting proof.
     *
     * @param PaymentAttemptInterface $attempt The FRESH locked attempt.
     * @param string $conflictingId The incoming (differing) zp_trans_id.
     * @return bool Whether the row changed.
     */
    private function quarantineProviderTransactionConflict(
        PaymentAttemptInterface $attempt,
        string $conflictingId
    ): bool {
        $changed = false;
        if ($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_PAID)) {
            // markPaid(null) never overwrites the recorded provider id.
            $attempt->markPaid(null);
            $changed = true;
        }
        $changed = $this->markRequiresReconciliation(
            $attempt,
            PaymentAttemptInterface::RECON_PROVIDER_TX_CONFLICT
        ) || $changed;
        $message = sprintf(
            'Conflicting provider transaction id: recorded zp_trans_id %s, authoritative proof claims %s. '
            . 'Kept money-real; NOT auto-finalizable — manual reconciliation required.',
            (string)$attempt->getProviderTransactionId(),
            $conflictingId
        );
        if ($attempt->getLastError() !== $message) {
            $attempt->setLastError($message);

            return true;
        }

        return $changed;
    }

    /**
     * Set the structured reconciliation quarantine + machine-readable code
     * (corrective round 3, Blocker 5). Idempotent: the FIRST reason code is
     * kept, later evidence is appended to last_error by the callers.
     *
     * @param PaymentAttemptInterface $attempt
     * @param string $code A PaymentAttemptInterface::RECON_* constant.
     * @return bool Whether the row changed.
     */
    private function markRequiresReconciliation(PaymentAttemptInterface $attempt, string $code): bool
    {
        if ($attempt->getRequiresReconciliation()) {
            return false;
        }
        $attempt->setRequiresReconciliation(true);
        $attempt->setReconciliationCode($code);

        return true;
    }

    /**
     * Store a missing provider transaction id (never overwrite a recorded one).
     *
     * @param PaymentAttemptInterface $attempt
     * @param string|null $providerTransactionId
     * @return bool Whether the row changed.
     */
    private function backfillProviderTransactionId(
        PaymentAttemptInterface $attempt,
        ?string $providerTransactionId
    ): bool {
        if ($providerTransactionId === null || $providerTransactionId === ''
            || $attempt->getProviderTransactionId() !== null
        ) {
            return false;
        }
        $attempt->setProviderTransactionId($providerTransactionId);

        return true;
    }

    /**
     * Run one locked, short-transaction mutation on the CURRENT row state.
     *
     * The transaction is open ONLY around the SELECT ... FOR UPDATE, the
     * in-memory decision and the save — never around provider HTTP (which
     * already happened in the caller) or order placement (which the
     * OrderFinalizer runs in its own separate transaction afterwards).
     *
     * @param string $appTransId
     * @param callable(PaymentAttemptInterface):bool $mutator Applies the
     *        transition/evidence to the FRESH locked attempt, returns whether
     *        the row changed.
     * @return PaymentAttemptInterface The fresh locked attempt.
     * @throws LocalizedException Unknown app_trans_id.
     */
    private function mutateLocked(string $appTransId, callable $mutator): PaymentAttemptInterface
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $locked = $this->repository->lockByAppTransId($appTransId);
            if ($locked === null) {
                throw new LocalizedException(
                    __('ZaloPay payment attempt "%1" no longer exists.', $appTransId)
                );
            }
            $changed = (bool)$mutator($locked);
            if ($changed) {
                $this->repository->save($locked);
            }
            $connection->commit();

            return $locked;
        } catch (\Exception $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
