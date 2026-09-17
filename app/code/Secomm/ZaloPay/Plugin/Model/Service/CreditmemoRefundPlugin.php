<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Plugin\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Service\CreditmemoService;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Exception\RefundProtocolException;
use Secomm\ZaloPay\Exception\RefundTransportException;
use Secomm\ZaloPay\Gateway\Command\RefundCommand;
use Secomm\ZaloPay\Gateway\Command\RefundOutcome;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Service\CreditmemoRefundPreflight;
use Secomm\ZaloPay\Service\PendingRefundManager;
use Secomm\ZaloPay\Service\RefundOutcomeMarker;

/**
 * Orchestrates the ZaloPay async refund lifecycle around the Magento core
 * refund flow (CreditmemoService::refund, the CreditmemoManagement entry
 * the admin "Refund" action calls) - TASK-CG6BM7 corrective rounds.
 *
 * Lifecycle per refund request (exactly ONE provider interaction):
 *
 *  1. GUARD      - an invalid/unresolvable order surfaces the Magento
 *                  compatible NoSuchEntityException BEFORE anything else
 *                  (round 3); offline refunds are refused;
 *  2. VALIDATION - the core-mirror preflight (CreditmemoService::
 *                  validateForRefund) must PASS before ANY provider I/O and
 *                  before ANY persistence (round 2);
 *  3. IDENTITY   - RefundCommand::prepare() builds the stable provider
 *                  request identity (m_refund_id + reconciliation payload)
 *                  with NO network I/O (round 3);
 *  4. CLAIM      - the ATOMIC durable claim is committed (single INSERT
 *                  guarded by the DB unique (order_id, active_claim) and
 *                  (m_refund_id, active_claim) indexes) BEFORE the provider
 *                  call: two concurrent requesters race on the INSERT, the
 *                  loser never reaches the provider - no check-then-act
 *                  (round 3 F12). The real admin credit memo is UNSAVED at
 *                  this point, so the claim row carries credit_memo_id NULL
 *                  (round 4 F17);
 *  4b. BIND      - the credit memo is PERSISTED (real entity_id) in the
 *                  NATIVE STATE_OPEN with its invoice_id pinned (round 7
 *                  F27/F31 - metadata only, never refund accounting) and
 *                  its id is bound to the claimed row (UPDATE guarded by
 *                  active_claim = 1). Provider HTTP runs ONLY when the
 *                  claim, the stable m_refund_id AND the real
 *                  credit_memo_id are all persisted; any bind-phase failure
 *                  terminates the claim as abandoned-before-I/O and never
 *                  touches the provider (round 4 F17);
 *  4c. START     - the durable provider_request_started state + UTC
 *                  timestamp is persisted (UPDATE guarded by
 *                  active_claim = 1): the explicit LOCAL_READY ->
 *                  PROVIDER_REQUEST_STARTED boundary (round 5 F23). The
 *                  cron never queries before this boundary and never
 *                  queries within the reconciliation grace after it
 *                  (grace > HTTP timeout);
 *  5. PROVIDER   - executePrepared() performs the ONE provider /refund for
 *                  the claimed identity;
 *  6a. SUCCESS   - a ONE-SHOT provider-skip authorization (round 7 F28,
 *                  keyed by THIS credit memo id) is granted and the NATIVE
 *                  core flow runs (proceed()); the gateway command consumes
 *                  it exactly once, the finally always drops the pin; if
 *                  the local finalize fails the durable
 *                  PROVIDER_SUCCESS_LOCAL_PENDING state keeps the money
 *                  reconcilable and the cron finalizes the Magento
 *                  accounting ONLY (never /refund again) (round 3 F13);
 *  6b. PROCESSING- durable PROCESSING, core does NOT run: totals untouched,
 *                  order cannot CLOSE; the credit memo STAYS STATE_OPEN
 *                  (round 7 F34); the cron finalizes on confirmed provider
 *                  SUCCESS;
 *  6c. FAIL      - provider-confirmed refusal (return_code = 2 only):
 *                  durable CONFIRMED_FAIL (releases the claim) and the safe
 *                  mapped message propagates - accounting untouched;
 *  6d. ANOMALY   - protocol violation (round 7 F30): return_code missing /
 *                  non-numeric / outside {1,2,3} is NOT a refusal - durable
 *                  UNKNOWN, claim NOT released, reconciled by m_refund_id;
 *  6e. TRANSPORT - outcome UNKNOWN: durable UNKNOWN (not processing -
 *                  round 3 F16), reconciled by m_refund_id, never
 *                  re-requested.
 *
 * The provider call runs BEFORE CreditmemoService::refund opens its
 * database transaction: no provider I/O inside a DB transaction.
 */
class CreditmemoRefundPlugin
{
    /**
     * @param MethodInterface $method ZaloPayFacade (injected via di.xml).
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param RefundCommand $refundCommand
     * @param PendingRefundManager $pendingRefundManager
     * @param RefundOutcomeMarker $outcomeMarker
     * @param ManagerInterface $messageManager
     * @param CreditmemoRefundPreflight $preflight Magento refund validation
     *        that runs BEFORE the provider call (corrective round 2).
     * @param CreditmemoRepositoryInterface $creditmemoRepository Persists
     *        the UNSAVED admin credit memo to obtain the REAL entity_id
     *        before the provider is asked (round 4 F17). NOTE (round 7
     *        F27): this is the ONLY repository the plugin persists
     *        through pre-provider - order/invoice accounting stays
     *        exclusively with the native core flow / the cron finalize.
     * @param Logger $logger Safe-evidence logging (never key material,
     *        never raw provider payloads).
     */
    public function __construct(
        private readonly MethodInterface               $method,
        private readonly PaymentDataObjectFactory      $paymentDataObjectFactory,
        private readonly RefundCommand                 $refundCommand,
        private readonly PendingRefundManager          $pendingRefundManager,
        private readonly RefundOutcomeMarker           $outcomeMarker,
        private readonly ManagerInterface              $messageManager,
        private readonly CreditmemoRefundPreflight     $preflight,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly Logger                        $logger
    ) {
    }

    /**
     * @param CreditmemoService $subject
     * @param callable $proceed (CreditmemoInterface $creditmemo, bool $offlineRequested): CreditmemoInterface
     * @param CreditmemoInterface $creditmemo
     * @param bool $offlineRequested
     * @return CreditmemoInterface
     * @throws LocalizedException On every path that must NOT run the core
     *         refund accounting (offline, claim conflict, provider refusal,
     *         untrackable transport failure, inconsistent local tracking
     *         state, local finalize failure).
     * @throws RefundProtocolException Provider protocol anomaly (round 7
     *         F30) - rethrown as-is after landing durable UNKNOWN.
     * @throws NoSuchEntityException Invalid/unresolvable order reference.
     */
    public function aroundRefund(
        CreditmemoService   $subject,
        callable            $proceed,
        CreditmemoInterface $creditmemo,
        $offlineRequested = false
    ) {
        // ROBUSTNESS (round 3): resolve the order defensively FIRST - a
        // malformed/missing order must surface as the Magento-compatible
        // NoSuchEntityException (same contract as core validateForRefund)
        // instead of a fatal dereference: provider call 0, persistence 0.
        try {
            $order = $creditmemo->getOrder();
        } catch (\Throwable $exception) {
            $order = null;
        }
        if ($order === null) {
            throw new NoSuchEntityException(__('We found an invalid order to refund.'));
        }
        $payment = $order->getPayment();

        if ($payment === null || $payment->getMethod() !== $this->method->getCode()) {
            // Not a ZaloPay refund: the core flow is untouched.
            return $proceed($creditmemo, $offlineRequested);
        }

        if ((bool)$offlineRequested) {
            // An offline refund would finalize Magento's accounting without
            // ever telling the provider - the money would stay at ZaloPay.
            throw new LocalizedException(
                __('Zalopay: Offline refunds are not supported. Please refund online so the provider is informed.')
            );
        }

        // MAGENTO VALIDATION BEFORE PROVIDER (round 2): the core refund
        // validation (CreditmemoService::validateForRefund - protected,
        // mirrored by the preflight service) must PASS before any provider
        // I/O. An invalid Magento refund (over-refund, already-processed
        // credit memo, missing order, non-positive amount) is rejected here
        // - the provider is never asked, and nothing is persisted.
        $this->preflight->validateRefundable($creditmemo);

        // INVOICE IDENTITY (round 7 F31): an online ZaloPay refund must
        // carry the ORIGINAL capture invoice with a REAL entity id. Guard
        // order: invoice object first (the transaction id below lives on
        // it), then the capture transaction id. Without a persisted
        // invoice_id a cron-reloaded credit memo would finalize as OFFLINE
        // (Payment::refund classifies online only when the credit memo's
        // invoice carries a transaction id) - corrupting accounting
        // semantics. Metadata linkage ONLY: is_used_for_refund /
        // base_total_refunded stay untouched here (the native core flow or
        // the cron finalize owns refund accounting, never this plugin).
        $invoice = $creditmemo->getInvoice();
        if ($invoice === null || !(int)$invoice->getEntityId()) {
            throw new LocalizedException(
                __('Zalopay: The original invoice for this refund cannot be found. The credit memo cannot be refunded.')
            );
        }

        // The capture (invoice) transaction identifies the provider payment:
        // Payment::refund sets parentTransactionId only inside its own flow,
        // which we preempt - pin it here so the request builder can resolve
        // zp_trans_id. An online ZaloPay refund always has an invoice.
        $parentTxnId = (string)($invoice->getTransactionId() ?? '');
        if ($parentTxnId === '') {
            throw new LocalizedException(
                __('Zalopay: The original invoice transaction for this refund cannot be found. The credit memo cannot be refunded.')
            );
        }

        // Pin the invoice linkage on the credit memo BEFORE the pre-provider
        // persistence: creditmemoRepository->save() does NOT persist the
        // in-memory invoice association, so without this the reloaded credit
        // memo would carry invoice_id NULL (F31).
        $creditmemo->setInvoiceId((int)$invoice->getEntityId());

        $payment->setCreditmemo($creditmemo);
        $payment->setParentTransactionId($parentTxnId);
        $commandSubject = [
            'payment' => $this->paymentDataObjectFactory->create($payment),
            'amount'  => (float)$creditmemo->getBaseGrandTotal(),
        ];

        // PROVIDER IDENTITY BEFORE I/O (round 3): the stable m_refund_id and
        // the reconciliation payload are built with NO network I/O, so the
        // claim below carries the exact identity the provider will see.
        $request = $this->refundCommand->prepare($commandSubject);
        if ($request === null) {
            // FAIL-CLOSED (round 7 F28): prepare() returning null now means
            // a one-shot provider-skip was consumed for THIS credit memo -
            // an authorization only the success path below grants. Reaching
            // it here means the local tracking state is inconsistent; running
            // the core flow would finalize accounting with the provider
            // never asked for this money. Provider call 0, accounting 0 -
            // abort with a safe message and safe log evidence (no secrets,
            // no payloads).
            $this->logger->error(
                sprintf(
                    'ZaloPay refund aborted fail-closed: provider-skip authorization present for credit memo #%d '
                    . 'before the provider was asked (order #%s).',
                    (int)$creditmemo->getEntityId(),
                    (string)$order->getIncrementId()
                )
            );

            throw new LocalizedException(
                __('Zalopay: The refund could not be started because its local'
                    . ' tracking state is inconsistent. Please try again.')
            );
        }

        // ATOMIC DURABLE CLAIM (round 3 F12): committed BEFORE any provider
        // I/O. The DB unique indexes decide the winner - a concurrent second
        // requester fails HERE and never reaches the provider. The admin
        // credit memo is UNSAVED at this point: credit_memo_id is NULL
        // (round 4 F17) - the bind below completes the provider gate.
        $claim = $this->pendingRefundManager->acquireClaim($creditmemo, $request->getTracking());

        // BIND PHASE (round 4 F17): persist the credit memo (real
        // entity_id) and bind it to the claimed row. Provider HTTP is
        // gated on claim + stable m_refund_id + BOUND real credit_memo_id;
        // any failure here terminates the claim as
        // abandoned_before_provider_io (money provably never moved) and
        // never reaches the provider.
        //
        // STATE CONTRACT (round 7 F27): the credit memo is persisted in the
        // NATIVE STATE_OPEN. Semantics of the pre-provider persisted credit
        // memo: it exists locally, the refund accounting is NOT applied and
        // the provider result is NOT final. The synchronous SUCCESS path
        // needs OPEN: the core validateForRefund() refuses an EXISTING
        // credit memo (id > 0) whose state is not OPEN - without this the
        // native flow could never complete. Persistence is metadata-only:
        // order.total_refunded / base_total_refunded, item qty_refunded,
        // invoice.is_used_for_refund / base_total_refunded and the payment
        // refunded amounts are NEVER touched here (the native core flow or
        // the cron finalize owns refund accounting exclusively).
        $creditmemo->setState(Creditmemo::STATE_OPEN);
        try {
            if (!(int)$creditmemo->getEntityId()) {
                $this->creditmemoRepository->save($creditmemo);
            }
        } catch (\Throwable $saveException) {
            $this->pendingRefundManager->terminate(
                $claim,
                PendingRefundManager::EVIDENCE_ABANDONED
                . 'local creditmemo persist failed: ' . $saveException->getMessage(),
                RefundInterface::REFUND_STATE_CONFIRMED_FAIL
            );

            throw new LocalizedException(
                __('Zalopay: The refund could not be recorded locally. Please try again.'),
                $saveException
            );
        }

        if (!$this->pendingRefundManager->bindCreditMemo($claim, (int)$creditmemo->getEntityId())) {
            // The claim slot was lost between acquire and bind (concurrent
            // stale-claim release / terminal transition): provider I/O is
            // forbidden - the construction invariant.
            $this->pendingRefundManager->terminate(
                $claim,
                PendingRefundManager::EVIDENCE_ABANDONED . 'claim lost before bind - provider I/O forbidden',
                RefundInterface::REFUND_STATE_CONFIRMED_FAIL
            );

            throw new LocalizedException(
                __('Zalopay: The refund attempt could not be bound locally. Please try again.')
            );
        }

        // PROVIDER-START BOUNDARY (round 5 F23): persist the durable,
        // timestamped provider-start state BEFORE the provider HTTP may
        // run. The cron can then never query/release a row whose provider
        // request may still be in flight: it honors the reconciliation
        // grace (grace > HTTP timeout). Guarded by active_claim = 1 - a
        // lost slot means provider I/O is forbidden.
        if (!$this->pendingRefundManager->markProviderRequestStarted($claim)) {
            $this->pendingRefundManager->terminate(
                $claim,
                PendingRefundManager::EVIDENCE_ABANDONED
                . 'provider-start state could not be persisted - provider I/O forbidden',
                RefundInterface::REFUND_STATE_CONFIRMED_FAIL
            );

            throw new LocalizedException(
                __('Zalopay: The refund attempt could not be started. Please try again.')
            );
        }

        try {
            $outcome = $this->refundCommand->executePrepared($request, $commandSubject);
        } catch (RefundTransportException $exception) {
            if ($exception->getOutcome() === null) {
                // Defensive: no carried identity - the outcome is UNKNOWN
                // beyond the claim row itself.
                $this->pendingRefundManager->markUnknown(
                    $claim,
                    PendingRefundManager::EVIDENCE_TRANSPORT . 'tracking outcome missing'
                );

                throw new LocalizedException(
                    __('Zalopay: Refund failed. Please try again later.'),
                    $exception
                );
            }

            // Outcome UNKNOWN: the semantic state is UNKNOWN (NOT processing,
            // round 3 F16) - reconciled by m_refund_id, never re-requested.
            $this->pendingRefundManager->markUnknown(
                $claim,
                PendingRefundManager::EVIDENCE_TRANSPORT
                . 'initial refund outcome unknown - reconciliation pending'
            );

            throw new LocalizedException(
                __('Zalopay: Refund status could not be confirmed. The refund is tracked and will be reconciled automatically.'),
                $exception
            );
        } catch (RefundProtocolException $exception) {
            // Round 7 F30: provider protocol anomaly (return_code missing /
            // non-numeric / outside {1,2,3}) - the provider state is NOT
            // confirmable, which is NOT a confirmed refusal: land durable
            // UNKNOWN. The claim row keeps its atomic slot AND the same
            // m_refund_id - never released, never re-requested fresh; the
            // cron reconciles via v2/query_refund. The exception message is
            // already our own customer-safe text - rethrow as-is.
            $this->pendingRefundManager->markUnknown(
                $claim,
                PendingRefundManager::EVIDENCE_ANOMALY
                . 'refund status not confirmable: ' . $exception->getMessage()
            );

            throw $exception;
        } catch (LocalizedException $exception) {
            // Provider CONFIRMED refusal (money provably NOT refunded): land
            // confirmed_fail - releases the claim and the block - and let the
            // safe mapped message propagate. Accounting untouched.
            $this->pendingRefundManager->markConfirmedFail(
                $claim,
                PendingRefundManager::EVIDENCE_REFUND_FAILED . $exception->getMessage()
            );

            throw $exception;
        }

        if ($outcome->getStatus() === RefundOutcome::STATUS_PROCESSING) {
            // Provider accepted, outcome open: durable PROCESSING and STOP -
            // the core refund accounting must NOT run (invariant:
            // PROCESSING != refunded; totals untouched; order cannot CLOSE).
            // The credit memo STAYS in the native STATE_OPEN for every
            // non-success outcome (round 7 F34: the invalid custom
            // PROCESSING credit memo state is gone - the durable refund row
            // state, not the display state, drives the cron recovery).
            $this->pendingRefundManager->markProcessing($claim);
            $this->messageManager->addSuccessMessage(
                __('Zalopay: Refund accepted by the provider and is being processed. The credit memo will be finalized automatically.')
            );

            return $creditmemo;
        }

        // Confirmed SUCCESS: the money is refunded at the provider - the
        // native core flow owns the Magento accounting. Round 7 F28: grant
        // the ONE-SHOT provider-skip for THIS exact credit memo id, then let
        // the core flow consume it inside the gateway refund command
        // (Payment::refund). The finally drops the pin on EVERY exit path:
        // a core finalize that throws before the gateway command consumed
        // the authorization can never leak the skip into a later refund.
        $creditMemoId = (int)$creditmemo->getEntityId();
        $this->outcomeMarker->authorize($creditMemoId);
        try {
            $result = $proceed($creditmemo, false);
        } catch (\Throwable $finalizeException) {
            // F13: the provider money is OUT but the Magento accounting is
            // incomplete - durable PROVIDER_SUCCESS_LOCAL_PENDING. The cron
            // finalizes the Magento accounting ONLY (never /refund again).
            $this->pendingRefundManager->markProviderSuccessLocalPending(
                $claim,
                PendingRefundManager::EVIDENCE_RECONCILE
                . 'core finalize failed: ' . $finalizeException->getMessage()
            );

            throw new LocalizedException(
                __('Zalopay: Refund succeeded at the provider but the local accounting is incomplete. It will be completed automatically.'),
                $finalizeException
            );
        } finally {
            $this->outcomeMarker->clear($creditMemoId);
        }

        // Terminal bookkeeping (a persistence failure is critical-logged and
        // swallowed inside the manager: a completed refund is never failed on
        // bookkeeping - the blocking row self-heals via the cron).
        $this->pendingRefundManager->markConfirmedSuccess($claim);

        return $result;
    }
}
