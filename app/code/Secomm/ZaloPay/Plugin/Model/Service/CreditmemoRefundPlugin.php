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
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Service\CreditmemoService;
use Secomm\ZaloPay\Exception\RefundTransportException;
use Secomm\ZaloPay\Gateway\Command\RefundCommand;
use Secomm\ZaloPay\Gateway\Command\RefundOutcome;
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
 *                  (round 3 F12);
 *  5. PROVIDER   - executePrepared() performs the ONE provider /refund for
 *                  the claimed identity;
 *  6a. SUCCESS   - the marker pins the known outcome and the NATIVE core
 *                  flow runs (proceed()); if the local finalize fails the
 *                  durable PROVIDER_SUCCESS_LOCAL_PENDING state keeps the
 *                  money reconcilable and the cron finalizes the Magento
 *                  accounting ONLY (never /refund again) (round 3 F13);
 *  6b. PROCESSING- durable PROCESSING, core does NOT run: totals untouched,
 *                  order cannot CLOSE; the cron finalizes on confirmed
 *                  provider SUCCESS;
 *  6c. FAIL      - provider-confirmed refusal: durable CONFIRMED_FAIL
 *                  (releases the claim) and the safe mapped message
 *                  propagates - accounting untouched;
 *  6d. TRANSPORT - outcome UNKNOWN: durable UNKNOWN (not processing -
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
     */
    public function __construct(
        private readonly MethodInterface           $method,
        private readonly PaymentDataObjectFactory  $paymentDataObjectFactory,
        private readonly RefundCommand             $refundCommand,
        private readonly PendingRefundManager      $pendingRefundManager,
        private readonly RefundOutcomeMarker       $outcomeMarker,
        private readonly ManagerInterface          $messageManager,
        private readonly CreditmemoRefundPreflight $preflight
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
     *         untrackable transport failure, local finalize failure).
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

        $orderId = (int)$order->getId();

        // MAGENTO VALIDATION BEFORE PROVIDER (round 2): the core refund
        // validation (CreditmemoService::validateForRefund - protected,
        // mirrored by the preflight service) must PASS before any provider
        // I/O. An invalid Magento refund (over-refund, already-processed
        // credit memo, missing order, non-positive amount) is rejected here
        // - the provider is never asked, and nothing is persisted.
        $this->preflight->validateRefundable($creditmemo);

        // The capture (invoice) transaction identifies the provider payment:
        // Payment::refund sets parentTransactionId only inside its own flow,
        // which we preempt - pin it here so the request builder can resolve
        // zp_trans_id. An online ZaloPay refund always has an invoice.
        $parentTxnId = (string)($creditmemo->getInvoice()?->getTransactionId() ?? '');
        if ($parentTxnId === '') {
            throw new LocalizedException(
                __('Zalopay: The original invoice transaction for this refund cannot be found. The credit memo cannot be refunded.')
            );
        }

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
            // Defensive: the marker claims the provider was already asked for
            // this order but this plugin did not ask - hand over to the core
            // flow unchanged.
            return $proceed($creditmemo, false);
        }

        // ATOMIC DURABLE CLAIM (round 3 F12): committed BEFORE any provider
        // I/O. The DB unique indexes decide the winner - a concurrent second
        // requester fails HERE and never reaches the provider.
        $claim = $this->pendingRefundManager->acquireClaim($creditmemo, $request->getTracking());

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
            $this->pendingRefundManager->markProcessing($claim);
            $this->messageManager->addSuccessMessage(
                __('Zalopay: Refund accepted by the provider and is being processed. The credit memo will be finalized automatically.')
            );

            return $creditmemo;
        }

        // Confirmed SUCCESS: the money is refunded at the provider - the
        // native core flow owns the Magento accounting (exactly once: the
        // gateway refund command skips its provider call via the marker).
        $this->outcomeMarker->markProviderAlreadyAsked($orderId);
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
        }

        // Terminal bookkeeping (a persistence failure is critical-logged and
        // swallowed inside the manager: a completed refund is never failed on
        // bookkeeping - the blocking row self-heals via the cron).
        $this->pendingRefundManager->markConfirmedSuccess($claim);

        return $result;
    }
}
