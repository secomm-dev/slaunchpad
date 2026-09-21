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
use Magento\Framework\DB\Sql\Expression;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\RefundOperation;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Gateway\Command\RefundOutcome;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\RefundModel;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;
use Secomm\ZaloPay\Model\ResourceModel\RefundResource;

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
 *
 * ROUND 7 (F29): every post-claim transition is an ATOMIC conditional
 * UPDATE (compare-and-set) on the connection - expected refund_state +
 * active_claim guards in the WHERE, new values in the SET. A stale cron
 * snapshot can NEVER overwrite a newer owner transition: the loser of the
 * CAS reloads the row, critical-logs the loss and (blocking forward
 * transitions) throws a customer-safe LocalizedException, while terminal
 * transitions (confirmed_fail / confirmed_success / terminate) swallow -
 * the winner owns the row. No read-modify-write `$model->save()` remains
 * in these transitions.
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
     * Reconciliation grace (round 5 F23): the cron must NEVER query a row
     * while its provider HTTP may still be in flight. The gateway transfer
     * factory sets NO client timeout override, so the provider HTTP is
     * bounded by the Laminas client default timeout = 10s. The grace
     * (120s) is >= 12x that hard ceiling: a query can only ever run after
     * the request must have completed or timed out. Persisted as
     * provider_request_started_at (UTC).
     */
    public const RECONCILIATION_GRACE_SECONDS = 120;

    /**
     * LOCAL_READY grace (round 6 F26): an initiating row is LOCAL_READY
     * (provider provably not contacted), so the cron must not release a
     * FRESH claim whose owner may still be inside the local bind phase
     * (acquireClaim -> save Credit Memo -> bindCreditMemo ->
     * markProviderRequestStarted). That path is local DB-only (NO
     * provider I/O) and completes in well under a second; 300s gives
     * >300x headroom for any realistic local stall. Only a STALE row
     * (age >= grace) proves the owner crashed before provider-start,
     * and there provider I/O was impossible by construction, so release
     * is safe. Anchored on the persisted created_at (UTC) of the claim
     * row - never in-memory time. DISTINCT from RECONCILIATION_GRACE_
     * SECONDS above: that grace bounds an in-flight provider HTTP (10s
     * timeout); this grace bounds a purely local phase (no network).
     */
    public const LOCAL_READY_GRACE_SECONDS = 300;

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
     *
     * @param int $orderId
     * @return bool
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
                RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
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
                    __(
                        'Zalopay: Another refund for this order is active or awaiting'
                        . ' reconciliation. Please wait until it is resolved before'
                        . ' requesting another refund.'
                    )
                );
            }

            // Pre-I/O failure: the provider was NOT asked (the claim commits
            // before the network call) - safe abort with honest evidence.
            $this->logger->error(
                sprintf(
                    'ZaloPay refund claim %s (order %s) could not be recorded:'
                    . ' %s - refund NOT requested at the provider.',
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
     * The durable, timestamped PROVIDER-START boundary (round 5 F23):
     * persists `provider_request_started` + provider_request_started_at
     * (UTC) BEFORE the provider HTTP may run. This is the last gate: the
     * provider gate is now claim + stable m_refund_id + real bound
     * credit_memo_id + THIS persisted provider-start state. The UPDATE is
     * CAS (round 7 F29): guarded by refund_state = initiating (the
     * LOCAL_READY state this transition must start from) AND
     * active_claim = 1 - if the claim slot or the state was moved between
     * the bind and this mark (concurrent cron stale-claim release /
     * terminal transition), the mark fails and the plugin MUST never
     * reach the provider. No DB transaction stays open across the
     * provider HTTP (single committed autocommit UPDATE).
     *
     * @param RefundModel $refund The claimed, bound row.
     * @return bool True when THIS row still owns the claim and the
     *         provider-start boundary is persisted.
     */
    public function markProviderRequestStarted(RefundModel $refund): bool
    {
        $connection = $this->refundResource->getConnection();
        $startedAt = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $affected = $connection->update(
            $this->refundResource->getMainTable(),
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                RefundInterface::PROVIDER_REQUEST_STARTED_AT => $startedAt,
            ],
            [
                RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId(),
                RefundInterface::REFUND_STATE . ' = ?' => RefundInterface::REFUND_STATE_INITIATING,
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ]
        );
        if ($affected === 1) {
            $refund->setData(RefundInterface::REFUND_STATE, RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED);
            $refund->setData(RefundInterface::PROVIDER_REQUEST_STARTED_AT, $startedAt);

            return true;
        }

        return false;
    }

    /**
     * Provider accepted the refund (PROCESSING): outcome open, refund
     * tracked durably, still blocking. CAS (round 7 F29): the atomic
     * UPDATE requires the row to still sit in provider_request_started or
     * processing AND still own the claim - a stale owner can never
     * overwrite a newer transition (a cron snapshot holding an old
     * initiating row loses this CAS by construction). The credit memo is
     * deliberately NOT touched here (round 7 F34): it stays OPEN until
     * the finalize lands; no custom PROCESSING parking exists anymore.
     *
     * @param RefundModel $refund
     * @return void
     * @throws LocalizedException When the CAS is lost (another owner/state
     *         transition won) - customer-safe, the row stays tracked and
     *         the cron reconciles it.
     */
    public function markProcessing(RefundModel $refund): void
    {
        $affected = $this->refundResource->getConnection()->update(
            $this->refundResource->getMainTable(),
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROCESSING,
                RefundInterface::LAST_ERROR => null,
            ],
            [
                RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId(),
                RefundInterface::REFUND_STATE . ' IN (?)' => [
                    RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                    RefundInterface::REFUND_STATE_PROCESSING,
                ],
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ]
        );
        if ($affected === 1) {
            $refund->setData(RefundInterface::REFUND_STATE, RefundInterface::REFUND_STATE_PROCESSING);
            $refund->setData(RefundInterface::LAST_ERROR, null);

            return;
        }

        $this->handleLostTransition($refund, 'processing');
        throw new LocalizedException(
            __(
                'Zalopay: The refund state could not be recorded locally.'
                . ' The refund is tracked and will be reconciled automatically.'
            )
        );
    }

    /**
     * Outcome UNKNOWN (transport failure on the initial request): the
     * semantic state stored is UNKNOWN - explicitly NOT processing
     * (round 3 F16). Still blocking. CAS (round 7 F29): guarded by the
     * provider-started / processing / unknown source states AND the live
     * claim - a stale snapshot can never drag a terminal row back.
     *
     * @param RefundModel $refund
     * @param string|null $evidence Safe evidence text.
     * @return void
     * @throws LocalizedException When the CAS is lost (another owner won).
     */
    public function markUnknown(RefundModel $refund, ?string $evidence): void
    {
        $affected = $this->refundResource->getConnection()->update(
            $this->refundResource->getMainTable(),
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_UNKNOWN,
                RefundInterface::LAST_ERROR => $evidence,
            ],
            [
                RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId(),
                RefundInterface::REFUND_STATE . ' IN (?)' => [
                    RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                    RefundInterface::REFUND_STATE_PROCESSING,
                    RefundInterface::REFUND_STATE_UNKNOWN,
                ],
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ]
        );
        if ($affected === 1) {
            $refund->setData(RefundInterface::REFUND_STATE, RefundInterface::REFUND_STATE_UNKNOWN);
            $refund->setData(RefundInterface::LAST_ERROR, $evidence);

            return;
        }

        $this->handleLostTransition($refund, 'unknown');
        throw new LocalizedException(
            __(
                'Zalopay: The refund state could not be recorded locally.'
                . ' The refund is tracked and will be reconciled automatically.'
            )
        );
    }

    /**
     * Provider CONFIRMED refusal (money provably NOT refunded): land
     * confirmed_fail - releases the block AND the atomic claim slot - with
     * safe evidence. CAS (round 7 F29): guarded by the pre-provider-I/O or
     * open-outcome source states AND the live claim. A LOST CAS is
     * SWALLOWED (round 7 F29): another owner (e.g. a newer transition)
     * owns the row - logged only, never overwritten, never thrown into
     * the caller's flow.
     *
     * @param RefundModel $refund
     * @param string $evidence Safe evidence text.
     * @return void
     */
    public function markConfirmedFail(RefundModel $refund, string $evidence): void
    {
        $affected = $this->refundResource->getConnection()->update(
            $this->refundResource->getMainTable(),
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_FAIL,
                RefundInterface::LAST_ERROR => $evidence,
                RefundInterface::ACTIVE_CLAIM => null,
            ],
            [
                RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId(),
                RefundInterface::REFUND_STATE . ' IN (?)' => [
                    RefundInterface::REFUND_STATE_INITIATING,
                    RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                    RefundInterface::REFUND_STATE_PROCESSING,
                    RefundInterface::REFUND_STATE_UNKNOWN,
                ],
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ]
        );
        if ($affected === 1) {
            $refund->setData(RefundInterface::REFUND_STATE, RefundInterface::REFUND_STATE_CONFIRMED_FAIL);
            $refund->setData(RefundInterface::LAST_ERROR, $evidence);
            $refund->setData(RefundInterface::ACTIVE_CLAIM, null);

            return;
        }

        $this->handleLostTransition($refund, 'confirmed_fail');
    }

    /**
     * Provider confirmed SUCCESS but the local Magento finalize failed:
     * durable CONFIRMED-PROVIDER-SUCCESS / LOCAL-PENDING state (round 3
     * F13). The cron finalizes the Magento accounting ONLY - it must NEVER
     * re-ask the provider /refund for this row. Still blocking until the
     * finalize lands confirmed_success. CAS (round 7 F29): guarded by the
     * open-outcome source states AND the live claim.
     *
     * @param RefundModel $refund
     * @param string $evidence Safe evidence text (local failure reason).
     * @return void
     * @throws LocalizedException When the CAS is lost (another owner won).
     */
    public function markProviderSuccessLocalPending(RefundModel $refund, string $evidence): void
    {
        $affected = $this->refundResource->getConnection()->update(
            $this->refundResource->getMainTable(),
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING,
                RefundInterface::LAST_ERROR => $evidence,
            ],
            [
                RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId(),
                RefundInterface::REFUND_STATE . ' IN (?)' => [
                    RefundInterface::REFUND_STATE_PROVIDER_REQUEST_STARTED,
                    RefundInterface::REFUND_STATE_PROCESSING,
                    RefundInterface::REFUND_STATE_UNKNOWN,
                ],
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ]
        );
        if ($affected === 1) {
            $refund->setData(
                RefundInterface::REFUND_STATE,
                RefundInterface::REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING
            );
            $refund->setData(RefundInterface::LAST_ERROR, $evidence);

            return;
        }

        $this->handleLostTransition($refund, 'provider_success_local_pending');
        throw new LocalizedException(
            __(
                'Zalopay: The refund state could not be recorded locally.'
                . ' The refund is tracked and will be reconciled automatically.'
            )
        );
    }

    /**
     * Fully finalized SUCCESS (native accounting applied with the gateway
     * provider call skipped): terminal non-blocking bookkeeping - releases
     * the atomic claim slot. CAS (round 7 F29): guarded by is_processed = 0
     * - exactly one writer lands the terminal bookkeeping; a LOST CAS is
     * SWALLOWED (another run already finalized it - logged only), never
     * thrown into an already-completed refund.
     *
     * @param RefundModel $refund
     * @return void
     */
    public function markConfirmedSuccess(RefundModel $refund): void
    {
        $affected = $this->refundResource->getConnection()->update(
            $this->refundResource->getMainTable(),
            [
                RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
                RefundInterface::IS_PROCESSED => 1,
                RefundInterface::LAST_ERROR => null,
                RefundInterface::ACTIVE_CLAIM => null,
            ],
            [
                RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId(),
                RefundInterface::IS_PROCESSED . ' = ?' => 0,
            ]
        );
        if ($affected === 1) {
            $refund->setData(RefundInterface::REFUND_STATE, RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS);
            $refund->setData(RefundInterface::IS_PROCESSED, RefundInterface::PROCESSED);
            $refund->setData(RefundInterface::LAST_ERROR, null);
            $refund->setData(RefundInterface::ACTIVE_CLAIM, null);

            return;
        }

        $this->handleLostTransition($refund, 'confirmed_success');
    }

    /**
     * A LOST state-transition race (round 7 F29): another owner's
     * transition matched the conditional UPDATE first. Reload the row
     * fresh and critical-log the loss (safe text only: internal state
     * constants, never provider payload). The caller must NOT overwrite,
     * NOT release the claim and NOT downgrade the state - the winning
     * owner keeps the row exactly as it persisted it.
     *
     * @param RefundModel $refund The stale snapshot row (left untouched).
     * @param string $transition Log label of the intended transition.
     * @return void
     */
    private function handleLostTransition(RefundModel $refund, string $transition): void
    {
        $current = $this->reloadRefundRow((int)$refund->getId());
        $persistedState = $current === null
            ? 'row-gone'
            : (string)($current[RefundInterface::REFUND_STATE] ?? 'unknown');
        $this->logger->critical(
            sprintf(
                'ZaloPay refund row #%d: state transition to %s was not applied - another owner already moved the row (persisted state: %s). No overwrite, claim ownership unchanged.',
                (int)$refund->getId(),
                $transition,
                $persistedState
            )
        );
    }

    /**
     * Fresh read of one refund row by entity_id (no lock, no cache) - used
     * ONLY for evidence after a lost CAS, never as a write basis.
     *
     * @param int $refundId
     * @return array|null
     */
    private function reloadRefundRow(int $refundId): ?array
    {
        $connection = $this->refundResource->getConnection();
        $select = $connection->select()
            ->from($this->refundResource->getMainTable())
            ->where(RefundInterface::ENTITY_ID . ' = ?', $refundId);

        $row = $connection->fetchRow($select);

        return $row ?: null;
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
     * progression: an ATOMIC server-side increment (round 7 F29/F32) -
     * `query_attempts = query_attempts + 1` guarded by is_processed = 0 -
     * plus safe evidence. The refund_state is NEVER mutated here (F32): a
     * provider_success_local_pending row must never be demoted to unknown
     * at the cap - budget exhaustion only drops the row out of the cron
     * selection (see RefundCronjob::getUnprocessedRefunds), never mutates
     * its semantic state.
     *
     * @param RefundModel $refund
     * @param string|null $evidence Safe evidence text (null clears a stale error).
     * @return int The new attempts value (read back from the row).
     */
    public function consumeQueryBudget(RefundModel $refund, ?string $evidence): int
    {
        $connection = $this->refundResource->getConnection();
        $connection->update(
            $this->refundResource->getMainTable(),
            [
                RefundInterface::QUERY_ATTEMPTS => new Expression(
                    RefundInterface::QUERY_ATTEMPTS . ' + 1'
                ),
                RefundInterface::LAST_ERROR => $evidence,
            ],
            [
                RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId(),
                RefundInterface::IS_PROCESSED . ' = ?' => 0,
            ]
        );
        $attempts = (int)$connection->fetchOne(
            $connection->select()
                ->from($this->refundResource->getMainTable(), [RefundInterface::QUERY_ATTEMPTS])
                ->where(RefundInterface::ENTITY_ID . ' = ?', (int)$refund->getId())
        );
        // Mirror the persisted values into the in-memory model (never the
        // refund_state - F32). No blind model save: the UPDATE above IS
        // the transition.
        $refund->setData(RefundInterface::QUERY_ATTEMPTS, $attempts);
        $refund->setData(RefundInterface::LAST_ERROR, $evidence);

        return $attempts;
    }

    /**
     * Terminate a row without finalizing: saturate the query budget (the row
     * drops out of the cron selection, evidence retained - RefundCleanup
     * only deletes processed confirmed_success rows, round 7 F36) and
     * record safe evidence. Used for non-retryable classifications:
     * provider FAIL, malformed stored payload, missing creditmemo,
     * creditmemo state drift, stale LOCAL_READY claims. The EXPLICIT
     * semantic terminal state decides blocking: CONFIRMED_FAIL (provider
     * refused - money provably NOT refunded) releases the block on future
     * refund requests; UNKNOWN (malformed payload, missing creditmemo,
     * state drift - outcome never confirmed) keeps blocking until
     * deliberately resolved.
     *
     * CAS (round 7 F29, tightened by the deadline micro-correction): the
     * conditional UPDATE requires the row to STILL be in the state the
     * caller's snapshot observed (refund_state = snapshot state) AND to
     * STILL own the atomic claim (active_claim = 1). A cron holding a
     * stale initiating snapshot can therefore never terminate a row the
     * owner already moved to provider_request_started (claim alone is not
     * enough - the owner keeps active_claim = 1 across the provider-start
     * boundary), so money-out rows can never be flipped to confirmed_fail
     * behind the owner's back. A LOST CAS is SWALLOWED (logged only - the
     * newer owner/state wins; no overwrite, no claim release, NO reload
     * and retry against the newer state).
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
        $bind = [
            RefundInterface::QUERY_ATTEMPTS => self::MAX_QUERY_ATTEMPTS,
            RefundInterface::LAST_ERROR => $evidence,
            RefundInterface::REFUND_STATE => $refundState,
        ];
        if ($refundState === RefundInterface::REFUND_STATE_CONFIRMED_FAIL) {
            // Provider-confirmed refusal: release the atomic claim slot.
            // UNKNOWN keeps BOTH the slot and the block (quarantine).
            $bind[RefundInterface::ACTIVE_CLAIM] = null;
        }
        $affected = $this->refundResource->getConnection()->update(
            $this->refundResource->getMainTable(),
            $bind,
            [
                RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId(),
                RefundInterface::REFUND_STATE . ' = ?' => (string)$refund->getData(RefundInterface::REFUND_STATE),
                RefundInterface::ACTIVE_CLAIM . ' = ?' => 1,
            ]
        );
        if ($affected === 1) {
            foreach ($bind as $field => $value) {
                $refund->setData($field, $value);
            }

            return;
        }

        // Lost the row to another owner: swallow - the winning transition
        // (e.g. a newer provider_request_started or a finalize) owns the
        // row; never overwrite, never release its claim.
        $this->handleLostTransition($refund, 'terminate:' . $refundState);
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
                // complete only the bookkeeping, only the row owner (the
                // CAS AND is_processed = 0 guard) lands it.
                $connection->update(
                    $this->refundResource->getMainTable(),
                    [
                        RefundInterface::IS_PROCESSED => 1,
                        RefundInterface::LAST_ERROR => null,
                        RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
                        RefundInterface::ACTIVE_CLAIM => null,
                    ],
                    [
                        RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId(),
                        RefundInterface::IS_PROCESSED . ' = ?' => 0,
                    ]
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
            // Round 7 marker contract: the authorization is credit-memo
            // scoped and one-shot (consume), cleared in a finally so a
            // failed accounting run can never leave stale skip-state.
            $creditMemoId = (int)$locked[RefundInterface::CREDIT_MEMO_ID];
            $this->outcomeMarker->authorize($creditMemoId);
            try {
                $this->refundOperation->execute($creditmemo, $order, true);
            } finally {
                $this->outcomeMarker->clear($creditMemoId);
            }

            $this->creditmemoRepository->save($creditmemo);
            $this->orderRepository->save($order);

            // Terminal bookkeeping, CAS-guarded (F29): AND is_processed = 0
            // - exactly one writer lands confirmed_success.
            $connection->update(
                $this->refundResource->getMainTable(),
                [
                    RefundInterface::IS_PROCESSED => 1,
                    RefundInterface::LAST_ERROR => null,
                    RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS,
                    RefundInterface::ACTIVE_CLAIM => null,
                ],
                [
                    RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId(),
                    RefundInterface::IS_PROCESSED . ' = ?' => 0,
                ]
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
