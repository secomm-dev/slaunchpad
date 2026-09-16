<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\RefundOperation;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Gateway\Command\RefundOutcome;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\RefundModel;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;
use Secomm\ZaloPay\Model\ResourceModel\RefundResource;
use Secomm\ZaloPay\Plugin\Model\Order\CreditmemoPlugin;

/**
 * Durable pending-refund lifecycle for async ZaloPay refunds
 * (TASK-CG6BG7 corrective round).
 *
 * Invariant: ZaloPay PROCESSING != Magento refund completed. While a refund
 * is pending at the provider, Magento does NOT mutate total_refunded /
 * qty_refunded and the order does NOT become CLOSED; the local finalization
 * runs exactly once, only on confirmed provider SUCCESS, through the NATIVE
 * core accounting (RefundOperation + Payment::refund with the gateway
 * provider call skipped via RefundOutcomeMarker).
 */
class PendingRefundManager
{
    /**
     * Bounded v2/query_refund budget shared with RefundCronjob: 96 runs on
     * the 15-minute schedule (~24h). Terminal outcomes saturate the budget
     * so the row drops out of the cron selection (never loops silently).
     */
    public const MAX_QUERY_ATTEMPTS = 96;

    /**
     * Prefix for reconciliation evidence in last_error (safe text only).
     */
    public const EVIDENCE_RECONCILE = 'reconcile_error: ';

    /**
     * Prefix for transport evidence in last_error (safe text only).
     */
    public const EVIDENCE_TRANSPORT = 'transport_error: ';

    /**
     * Prefix for terminal provider-refusal evidence in last_error (safe,
     * provider-map text only).
     */
    public const EVIDENCE_REFUND_FAILED = 'refund_failed: ';

    /**
     * Prefix for provider protocol anomaly evidence in last_error.
     */
    public const EVIDENCE_ANOMALY = 'protocol_anomaly: ';

    /**
     * Prefix for claims abandoned BEFORE provider I/O (round 4 F18 stale
     * policy): the provider was provably never asked (provider gate =
     * claim + stable m_refund_id + BOUND real credit_memo_id), so the
     * money did not move - truthfully terminal.
     */
    public const EVIDENCE_ABANDONED = 'abandoned_before_provider_io: ';

    /**
     * @param RefundResource $refundResource Refund resource: connection, transaction, row lock.
     * @param RefundCollectionFactory $refundCollectionFactory
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param RefundOperation $refundOperation Native core refund accounting.
     * @param RefundOutcomeMarker $outcomeMarker Makes the gateway skip the provider call.
     * @param Logger $logger
     */
    public function __construct(
        private readonly RefundResource                $refundResource,
        private readonly RefundCollectionFactory       $refundCollectionFactory,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly InvoiceRepositoryInterface    $invoiceRepository,
        private readonly OrderRepositoryInterface      $orderRepository,
        private readonly RefundOperation               $refundOperation,
        private readonly RefundOutcomeMarker           $outcomeMarker,
        private readonly Logger                        $logger
    ) {
    }

    /**
     * Whether the order has an unresolved durable refund attempt (round 3
     * semantic blocking): a row that is NOT processed and sits in a
     * BLOCKING state - initiating, processing, unknown, or
     * provider_success_local_pending. The is_processed = 0 filter keeps
     * HISTORICAL RESOLVED rows (is_processed=1, backfilled to a terminal
     * state by BackfillRefundState) from ever blocking. CONFIRMED_SUCCESS /
     * CONFIRMED_FAIL are final: success hands control to the normal
     * refundable balance, confirmed fail released the block.
     *
     * NOTE (round 3): the refund REQUEST path no longer relies on this
     * check-then-act query - the plugin acquires the ATOMIC durable claim
     * (unique (order_id, active_claim) index) instead; this method remains
     * the semantic view used by reporting/tests.
     */
    public function hasInFlight(int $orderId): bool
    {
        $collection = $this->refundCollectionFactory->create();
        $collection->addFieldToFilter(RefundInterface::ORDER_ID, ['eq' => $orderId]);
        $collection->addFieldToFilter(RefundInterface::IS_PROCESSED, ['eq' => RefundInterface::NOT_PROCESSED]);
        $collection->addFieldToFilter(
            RefundInterface::REFUND_STATE,
            ['in' => [
                RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::REFUND_STATE_PROCESSING,
                RefundInterface::REFUND_STATE_UNKNOWN,
                RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
            ]]
        );

        return (int)$collection->getSize() > 0;
    }

    /**
     * ATOMIC durable claim (round 3 BLOCKER F12, round 4 F17): inserts THE
     * active refund attempt row for the order BEFORE any provider I/O and
     * BEFORE the credit memo is bound. The DB-level unique indexes
     * (order_id, active_claim) and (m_refund_id, active_claim) guarantee
     * exactly one active attempt per order and per request identity - two
     * concurrent requesters race on the INSERT, the loser gets a
     * duplicate-key failure and NEVER reaches the provider. No
     * check-then-act. The single-row INSERT commits (autocommit) BEFORE the
     * provider call, so a crash after the request left always leaves a
     * durable row the cron can reconcile by the SAME m_refund_id (never a
     * fresh /refund).
     *
     * ROUND 4 F17: the real admin flow (CreditmemoLoader ->
     * CreditmemoFactory -> CreditmemoManagement::refund) presents an
     * UNSAVED credit memo (no entity_id yet), so the claim INSERT carries
     * credit_memo_id = NULL. The claim is ORDER-level; the credit memo is
     * bound to the claimed row by bindCreditMemo() (guarded by
     * active_claim = 1) BEFORE the provider may be asked - the provider
     * gate is: claim persisted AND stable m_refund_id persisted AND real
     * credit_memo_id bound.
     *
     * @param CreditmemoInterface|Creditmemo $creditmemo
     * @param RefundOutcome $tracking Prepared tracking outcome (stable
     *        m_refund_id + reconciliation payload from RefundCommand::prepare).
     * @return RefundModel The claimed row (refund_state = initiating,
     *         active_claim = 1, credit_memo_id still NULL).
     * @throws LocalizedException Another ACTIVE attempt exists for this
     *         order (or the same m_refund_id) - the provider must not be
     *         called; or the claim could not be recorded (abort pre-I/O).
     */
    public function acquireClaim(CreditmemoInterface $creditmemo, RefundOutcome $tracking): RefundModel
    {
        $refund = $this->refundCollectionFactory->create()->getNewEmptyItem();
        $refund->setData([
            RefundInterface::ORDER_ID => (int)$creditmemo->getOrderId(),
            RefundInterface::CREDIT_MEMO_ID => null,
            RefundInterface::INCREMENT_ID => (string)$creditmemo->getOrder()->getIncrementId(),
            RefundInterface::M_REFUND_ID => $tracking->getMRefundId(),
            RefundInterface::ADDITIONAL_INFORMATION => (string)$tracking->getQueryPayload(),
            RefundInterface::AMOUNT => (float)$tracking->getVndAmount(),
            RefundInterface::IS_PROCESSED => RefundInterface::NOT_PROCESSED,
            RefundInterface::QUERY_ATTEMPTS => 0,
            RefundInterface::LAST_ERROR => null,
            RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_INITIATING,
            RefundInterface::ACTIVE_CLAIM => 1,
        ]);
        try {
            $refund->save();
        } catch (\Throwable $exception) {
            if ($this->isDuplicateKey($exception)) {
                throw new LocalizedException(
                    __('Zalopay: Another refund for this order is active or awaiting reconciliation. Please wait until it is resolved before requesting another refund.')
                );
            }

            // Pre-I/O failure: the provider was NOT asked (the claim commits
            // before the network call) - safe abort with honest evidence.
            $this->logger->error(
                sprintf(
                    'ZaloPay refund claim %s (order %s) could not be recorded: %s - refund NOT requested at the provider.',
                    $tracking->getMRefundId(),
                    (string)$creditmemo->getOrder()->getIncrementId(),
                    $exception->getMessage()
                )
            );

            throw new LocalizedException(
                __('Zalopay: The refund attempt could not be recorded locally. Please try again.')
            );
        }

        return $refund;
    }

    /**
     * Bind the REAL credit memo entity_id to the claimed refund attempt
     * (round 4 F17) - the last gate before provider I/O. The conditional
     * UPDATE requires the row to STILL own the atomic claim
     * (active_claim = 1): a concurrent cron stale-claim release or a
     * terminal transition between claim and bind makes the bind fail and
     * the plugin MUST then never call the provider.
     *
     * Provider HTTP runs only when: claim persisted AND stable m_refund_id
     * persisted AND real credit_memo_id bound - and no DB transaction
     * stays open across the provider call (the claim INSERT commits in
     * autocommit, this bind is a single committed UPDATE).
     *
     * @param RefundModel $refund The claimed row (entity_id, active_claim=1).
     * @param int $creditMemoId The REAL persisted credit memo entity_id.
     * @return bool True when THIS row still owns the claim and is bound.
     */
    public function bindCreditMemo(RefundModel $refund, int $creditMemoId): bool
    {
        $connection = $this->refundResource->getConnection();
        $affected = $connection->update(
            $this->refundResource->getMainTable(),
            [RefundInterface::CREDIT_MEMO_ID => $creditMemoId],
            [
                RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId(),
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ]
        );
        if ($affected === 1) {
            $refund->setData(RefundInterface::CREDIT_MEMO_ID, $creditMemoId);

            return true;
        }

        return false;
    }

    /**
     * Provider accepted the refund (PROCESSING): outcome open, refund
     * tracked durably, still blocking. The persisted credit memo is parked
     * in the custom PROCESSING state (round 4 state contract) - a park
     * failure is critical-logged and swallowed: the durable row state
     * drives the cron recovery (which accepts any non-canceled creditmemo
     * state), never the display state.
     *
     * @param RefundModel $refund
     * @param CreditmemoInterface|null $creditmemo Persisted credit memo to
     *        park in the custom PROCESSING state (when resolvable).
     * @return void
     * @throws LocalizedException When the transition cannot persist (the
     *         initiating claim row keeps the order blocked regardless).
     */
    public function markProcessing(RefundModel $refund, ?CreditmemoInterface $creditmemo = null): void
    {
        $refund->setData(RefundInterface::REFUND_STATE, RefundInterface::REFUND_STATE_PROCESSING);
        $this->saveState($refund, 'processing');

        if ($creditmemo !== null) {
            try {
                $creditmemo->setState(CreditmemoPlugin::STATE_PROCESSING);
                $this->creditmemoRepository->save($creditmemo);
            } catch (\Throwable $parkException) {
                $this->logger->critical(
                    sprintf(
                        'ZaloPay refund row #%d: credit memo #%d could not be parked in PROCESSING: %s - the refund row stays durable and reconcilable.',
                        (int)$refund->getId(),
                        (int)$creditmemo->getEntityId(),
                        $parkException->getMessage()
                    )
                );
            }
        }
    }

    /**
     * Outcome UNKNOWN (transport failure on the initial request): the
     * semantic state stored is UNKNOWN - explicitly NOT processing
     * (round 3 F16). Still blocking.
     *
     * @param RefundModel $refund
     * @param string|null $evidence
     * @return void
     * @throws LocalizedException When the transition cannot persist.
     */
    public function markUnknown(RefundModel $refund, ?string $evidence): void
    {
        $refund->setData(RefundInterface::REFUND_STATE, RefundInterface::REFUND_STATE_UNKNOWN);
        $refund->setData(RefundInterface::LAST_ERROR, $evidence);
        $this->saveState($refund, 'unknown');
    }

    /**
     * Provider CONFIRMED refusal (money provably NOT refunded): land
     * confirmed_fail - releases the block AND the atomic claim slot - with
     * safe evidence. The synchronous creditmemo was never parked by this
     * lifecycle on this path; the parked (async) case is released back to
     * OPEN by RefundCronjob.
     *
     * @param RefundModel $refund
     * @param string $evidence
     * @return void
     */
    public function markConfirmedFail(RefundModel $refund, string $evidence): void
    {
        $refund->setData(RefundInterface::REFUND_STATE, RefundInterface::REFUND_STATE_CONFIRMED_FAIL);
        $refund->setData(RefundInterface::LAST_ERROR, $evidence);
        $refund->setData(RefundInterface::ACTIVE_CLAIM, null);
        $this->saveState($refund, 'confirmed_fail');
    }

    /**
     * Provider confirmed SUCCESS but the local Magento finalize failed:
     * durable CONFIRMED-PROVIDER-SUCCESS / LOCAL-PENDING state (round 3
     * F13). The cron finalizes the Magento accounting ONLY - it must NEVER
     * re-ask the provider /refund for this row. Still blocking until the
     * finalize lands confirmed_success.
     *
     * @param RefundModel $refund
     * @param string $evidence Safe evidence text (local failure reason).
     * @return void
     * @throws LocalizedException When the transition cannot persist.
     */
    public function markProviderSuccessLocalPending(RefundModel $refund, string $evidence): void
    {
        $refund->setData(
            RefundInterface::REFUND_STATE,
            RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING
        );
        $refund->setData(RefundInterface::LAST_ERROR, $evidence);
        $this->saveState($refund, 'provider_success_local_pending');
    }

    /**
     * Fully finalized SUCCESS (native accounting applied with the gateway
     * provider call skipped): terminal non-blocking bookkeeping - releases
     * the atomic claim slot. A persistence failure here is critical-logged,
     * NEVER thrown into an already-completed refund: the row stays
     * blocking (initiating) and the cron self-heals (creditmemo REFUNDED ->
     * row resolve, never a second provider refund).
     *
     * @param RefundModel $refund
     * @return void
     */
    public function markConfirmedSuccess(RefundModel $refund): void
    {
        $refund->setData(RefundInterface::REFUND_STATE, RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS);
        $refund->setData(RefundInterface::IS_PROCESSED, RefundInterface::PROCESSED);
        $refund->setData(RefundInterface::LAST_ERROR, null);
        $refund->setData(RefundInterface::ACTIVE_CLAIM, null);
        $this->saveState($refund, 'confirmed_success');
    }

    /**
     * Persist one state transition. A persistence failure on a TERMINAL
     * transition (confirmed_*) is critical-logged and swallowed (the money
     * side is already resolved; the row recovers via the cron); on a
     * BLOCKING transition it is rethrown as a customer-safe
     * LocalizedException - the initiating claim row keeps the order blocked
     * either way, so nothing is lost.
     *
     * @param RefundModel $refund
     * @param string $transition Log label.
     * @return void
     * @throws LocalizedException When a BLOCKING transition cannot persist.
     */
    private function saveState(RefundModel $refund, string $transition): void
    {
        try {
            $refund->save();
        } catch (\Throwable $exception) {
            $state = (string)$refund->getData(RefundInterface::REFUND_STATE);
            $blocking = in_array($state, [
                RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::REFUND_STATE_PROCESSING,
                RefundInterface::REFUND_STATE_UNKNOWN,
                RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
            ], true);
            $this->logger->critical(
                sprintf(
                    'ZaloPay refund row #%d: state transition to %s could not be persisted (%s)%s.',
                    (int)$refund->getId(),
                    $transition,
                    $exception->getMessage(),
                    $blocking ? ' - row remains unresolved, the cron will retry' : ''
                )
            );
            if ($blocking) {
                throw new LocalizedException(
                    __('Zalopay: The refund state could not be recorded locally. The refund is tracked and will be reconciled automatically.')
                );
            }
        }
    }

    /**
     * MySQL DUPLICATE-KEY detection for the atomic claim INSERT (round 4
     * F22): ONLY the driver duplicate-entry error (1062) classifies as a
     * claim conflict. SQLSTATE 23000 alone is NOT sufficient - foreign-key
     * violations (driver 1452) share it - so an invalid credit_memo_id
     * must surface as a local persistence failure, never as the misleading
     * "another refund is active" message. Detection walks the exception
     * chain for the PDOException and reads its driver code (errorInfo[1]);
     * without a PDO in the chain, the MySQL-specific "Duplicate entry"
     * text is the fallback (foreign keys produce different text).
     *
     * @param \Throwable $exception
     * @return bool
     */
    private function isDuplicateKey(\Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof \PDOException) {
                $driverCode = is_array($current->errorInfo) && isset($current->errorInfo[1])
                    ? (int)$current->errorInfo[1]
                    : (int)$current->getCode();

                return $driverCode === 1062;
            }
        }

        return stripos($exception->getMessage(), 'duplicate entry') !== false;
    }

    /**
     * Consume one unit of the bounded query budget with observable state
     * progression: increments query_attempts and records safe evidence.
     * Used by RefundCronjob so EVERY genuine attempt (query transport
     * failure, protocol anomaly, finalize failure) is measurable - rows
     * never sit selected-forever (BLOCKER 2 fix).
     *
     * @param RefundModel $refund
     * @param string|null $evidence Safe evidence text (null clears a stale error).
     * @return int The new attempts value.
     */
    public function consumeQueryBudget(RefundModel $refund, ?string $evidence): int
    {
        $attempts = (int)$refund->getData(RefundInterface::QUERY_ATTEMPTS) + 1;
        $refund->setData(RefundInterface::QUERY_ATTEMPTS, $attempts);
        $refund->setData(RefundInterface::LAST_ERROR, $evidence);
        if ($attempts >= self::MAX_QUERY_ATTEMPTS
            && $refund->getData(RefundInterface::REFUND_STATE) !== RefundInterface::REFUND_STATE_UNKNOWN) {
            // Budget exhausted without a confirmed outcome: quarantine as
            // UNKNOWN - the row keeps blocking new refund requests until
            // deliberately resolved (exhaustion is never "safe to refund").
            $refund->setData(RefundInterface::REFUND_STATE, RefundInterface::REFUND_STATE_UNKNOWN);
        }
        $refund->save();

        return $attempts;
    }

    /**
     * Terminate a row without finalizing: saturate the query budget (the row
     * drops out of the cron selection, evidence retained - RefundCleanup
     * only deletes PROCESSED rows) and record safe evidence. Used for
     * non-retryable classifications: provider FAIL, malformed stored payload,
     * missing creditmemo, creditmemo state drift. The EXPLICIT semantic
     * terminal state decides blocking: CONFIRMED_FAIL (provider refused -
     * money provably NOT refunded) releases the block on future refund
     * requests; UNKNOWN (malformed payload, missing creditmemo, state drift -
     * outcome never confirmed) keeps blocking until deliberately resolved.
     *
     * @param RefundModel $refund
     * @param string $evidence Safe evidence text.
     * @param string $refundState Semantic terminal state (default UNKNOWN -
     *        an unconfirmed outcome is never assumed safe).
     * @return void
     */
    public function terminate(
        RefundModel $refund,
        string $evidence,
        string $refundState = RefundInterface::REFUND_STATE_UNKNOWN
    ): void {
        $refund->setData(RefundInterface::QUERY_ATTEMPTS, self::MAX_QUERY_ATTEMPTS);
        $refund->setData(RefundInterface::LAST_ERROR, $evidence);
        $refund->setData(RefundInterface::REFUND_STATE, $refundState);
        if ($refundState === RefundInterface::REFUND_STATE_CONFIRMED_FAIL) {
            // Provider-confirmed refusal: release the atomic claim slot.
            // UNKNOWN keeps BOTH the slot and the block (quarantine).
            $refund->setData(RefundInterface::ACTIVE_CLAIM, null);
        }
        $refund->save();
    }

    /**
     * Finalize a provider-confirmed refund exactly once through the NATIVE
     * core accounting - the gateway provider call is skipped via
     * RefundOutcomeMarker, the accounting replicates CreditmemoService's
     * success path minus the provider interaction: invoice update ->
     * RefundOperation (order refund totals + payment transactions) ->
     * creditmemo save -> order save, all in one transaction guarded by
     * SELECT ... FOR UPDATE on the refund row (a concurrent or repeated
     * run re-checks is_processed under the lock and never re-finalizes).
     *
     * @param RefundModel $refund The pending refund row.
     * @return bool True if THIS call finalized it; false if already finalized.
     * @throws LocalizedException On local accounting failure: the transaction
     *         is rolled back (provider money is out but local accounting is
     *         not - critical-logged; the row keeps pending evidence and its
     *         remaining query budget for the next cron attempt).
     */
    public function finalizeSuccess(RefundModel $refund): bool
    {
        $connection = $this->refundResource->getConnection();
        $connection->beginTransaction();
        try {
            $locked = $this->lockRefundRow((int)$refund->getId());
            if ($locked === null || (bool)$locked[RefundInterface::IS_PROCESSED]) {
                // Lost the race: another run already finalized this refund.
                $connection->commit();

                return false;
            }

            $creditmemo = $this->creditmemoRepository->get((int)$locked[RefundInterface::CREDIT_MEMO_ID]);
            if ((int)$creditmemo->getState() === Creditmemo::STATE_REFUNDED) {
                // Recovery: the local accounting already happened (e.g. a
                // crash between the creditmemo save and the row update) -
                // complete only the bookkeeping, never re-run accounting.
                $connection->update(
                    $this->refundResource->getMainTable(),
                    [
                        RefundInterface::IS_PROCESSED => 1,
                        RefundInterface::LAST_ERROR => null,
                        RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
                    RefundInterface::ACTIVE_CLAIM => null,
                    ],
                    [RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId()]
                );
                $connection->commit();

                return false;
            }

            $order = $creditmemo->getOrder();

            $invoice = null;
            if ((int)$creditmemo->getInvoiceId() > 0) {
                $invoice = $this->invoiceRepository->get((int)$creditmemo->getInvoiceId());
                $creditmemo->setInvoice($invoice);
            }

            // Core success-path replication (CreditmemoService::refund lines
            // 152-177, MINUS the gateway provider interaction which is skipped
            // via the marker below).
            if ($invoice !== null) {
                $invoice->setIsUsedForRefund(true);
                $invoice->setBaseTotalRefunded(
                    (float)$invoice->getBaseTotalRefunded() + (float)$creditmemo->getBaseGrandTotal()
                );
                $this->invoiceRepository->save($invoice);
            }

            $creditmemo->setState(Creditmemo::STATE_REFUNDED);
            // One provider interaction per refund: the outcome is already
            // known (SUCCESS) - the gateway refund command must NOT re-ask
            // the provider while Payment::refund runs the gateway command.
            $this->outcomeMarker->markProviderAlreadyAsked((int)$order->getId());
            $this->refundOperation->execute($creditmemo, $order, true);

            $this->creditmemoRepository->save($creditmemo);
            $this->orderRepository->save($order);

            $connection->update(
                $this->refundResource->getMainTable(),
                [
                    RefundInterface::IS_PROCESSED => 1,
                    RefundInterface::LAST_ERROR => null,
                    RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
                    RefundInterface::ACTIVE_CLAIM => null,
                ],
                [RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId()]
            );
            $connection->commit();
        } catch (NoSuchEntityException $exception) {
            $connection->rollBack();
            throw $exception;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw new LocalizedException(
                __('Zalopay: refund finalization failed locally: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    /**
     * SELECT ... FOR UPDATE on one refund row (the exact-once finalize
     * guard; same connection-scoped pattern as
     * PaymentAttemptResource::lockRowByAppTransId).
     *
     * @param int $refundId
     * @return array|null The locked row, or null when it no longer exists.
     */
    private function lockRefundRow(int $refundId): ?array
    {
        $connection = $this->refundResource->getConnection();
        $select = $connection->select()
            ->from($this->refundResource->getMainTable())
            ->where(RefundInterface::ENTITY_ID . ' = ?', $refundId)
            ->forUpdate(true);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }
}
