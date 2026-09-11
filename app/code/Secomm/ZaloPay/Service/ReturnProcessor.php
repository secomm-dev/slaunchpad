<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Exception\ContractMismatchException;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Helper\Data as ZaloPayHelper;
use Secomm\ZaloPay\Logger\Logger;

/**
 * Return (browser redirect) processing for the payment-first flow.
 *
 * THE BROWSER REDIRECT CAN NEVER TERMINALLY FAIL A PAYMENT (review-
 * corrective TASK-EDS9T5, Blocker 1): the earlier race — browser params
 * marking the attempt FAILED BEFORE the authoritative query ran — let a
 * valid later IPN arrive on a FAILED (terminal) attempt: acknowledged, no
 * order, customer paid with nothing to show. The fixed contract:
 *
 *  1. attempt lookup by apptransid (lookup key only — no state decisions);
 *  2. best-effort key2 checksum on the redirect params = tamper EVIDENCE
 *     only — a mismatch is logged and NEVER blocks the authoritative
 *     verification, never terminally mutates state (corrective round 3,
 *     Blocker 1);
 *  3. browser params (including `status`) are NEVER payment proof: the
 *     authoritative server-side v2/query ALWAYS runs and owns EVERY
 *     payment-state decision, using real ZaloPay query semantics
 *     (return_code 1 = paid, 3 = processing);
 *  4. processing (return_code 3): non-terminal — no mutation, the attempt
 *     keeps its current state (IPN, a later return or the TTL resolves it);
 *  5. authoritative failure: FAILED transition ONLY where the persisted
 *     state permits, via PaymentAttemptLifecycle (locked, short
 *     transaction) — never from a stale copy, never out of a terminal or
 *     money-real state;
 *  6. authoritative PAID: amount lock against the persisted snapshot, then
 *     PaymentAttemptLifecycle::recordVerifiedPaid -> OrderFinalizer
 *     (exactly one Sales Order, duplicate returns recover the bound one) ->
 *     SuccessSessionPreparer (the customer session is a Return-path concern).
 *
 * All attempt mutations go through PaymentAttemptLifecycle — this class
 * never marks or saves an attempt directly.
 */
class ReturnProcessor
{
    /**
     * v2/query return codes: 3 = unpaid / still processing.
     */
    private const QUERY_PROCESSING = 3;

    /**
     * ReturnProcessor constructor.
     *
     * @param PaymentAttemptRepositoryInterface $repository
     * @param CommandPoolInterface $commandPool
     * @param OrderFinalizer $orderFinalizer
     * @param PaymentAttemptLifecycle $lifecycle
     * @param SuccessSessionPreparer $successSessionPreparer
     * @param ZaloPayHelper $helperData
     * @param Logger $logger
     */
    public function __construct(
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly CommandPoolInterface             $commandPool,
        private readonly OrderFinalizer                   $orderFinalizer,
        private readonly PaymentAttemptLifecycle          $lifecycle,
        private readonly SuccessSessionPreparer           $successSessionPreparer,
        private readonly ZaloPayHelper                    $helperData,
        private readonly Logger                           $logger
    ) {
    }

    /**
     * Process the ZaloPay return redirect.
     *
     * @param array $params Raw GET params from the redirect.
     * @return string Redirect path for the controller.
     * @throws LocalizedException Always with a customer-safe message on failure.
     */
    public function process(array $params): string
    {
        $appTransId = trim((string)($params['apptransid'] ?? $params['app_trans_id'] ?? ''));
        if ($appTransId === '') {
            throw new LocalizedException(__('Invalid ZaloPay return payload.'));
        }

        $attempt = $this->repository->getByAppTransId($appTransId);
        if ($attempt === null) {
            $this->logger->warning('ZaloPay return: no payment attempt found.', ['app_trans_id' => $appTransId]);
            throw new LocalizedException(__('ZaloPay payment session not found. Please contact support.'));
        }

        // NOTE: a FINALIZED attempt is NOT short-circuited here — the
        // duplicate return still recovers the bound order and rebuilds the
        // customer success session below.

        // Best-effort tamper EVIDENCE ONLY (corrective round 3, Blocker 1):
        // a bad browser checksum is logged as a tamper signal and NEVER
        // blocks, terminates or pre-judges the payment. The redirect
        // checksum signs browser-visible redirect fields for the hosted
        // page UX — the authoritative v2/query below is a server-to-server
        // call signed with OUR key1 whose ONLY input is the app_trans_id
        // and whose result comes straight from ZaloPay. Forged browser
        // params therefore cannot influence the query outcome, so there is
        // no security reason (and no provider-documented reason — ZaloPay
        // explicitly recommends the proactive QueryOrder fallback) to refuse
        // verification because a browser checksum looks wrong. Refusing the
        // query here would RE-CREATE the round-1 blocker: a customer who
        // genuinely paid could be terminally failed on the strength of a
        // tampered redirect alone.
        if (!empty($params['checksum']) && !$this->helperData->verifyRedirect($params)) {
            $this->logger->error(
                'ZaloPay return checksum mismatch — tamper evidence recorded; the authoritative v2/query '
                . 'still decides the payment state.',
                ['app_trans_id' => $appTransId, 'browser_status' => (int)($params['status'] ?? 0)]
            );
        }

        // Authoritative server-side verification — EVERY payment-state
        // decision below is derived from THIS result, never from the
        // browser params.
        $query = $this->queryTransaction($appTransId);
        $returnCode = (int)($query[AbstractResponseValidator::RETURN_CODE] ?? 0);
        $paidAmount = (int)($query[AbstractResponseValidator::TOTAL_AMOUNT] ?? 0);
        $zpTransId = (string)($query[AbstractResponseValidator::ZP_TRANS_ID] ?? '');

        if ($returnCode === self::QUERY_PROCESSING) {
            // Non-terminal: the provider has not concluded. The attempt
            // keeps its current state — the IPN, a later return hit or the
            // attempt TTL resolves it. No mutation here.
            throw new LocalizedException(
                __('Your ZaloPay payment is still being processed. Please check back shortly.')
            );
        }

        if ($returnCode === AbstractResponseValidator::RETURN_CODE_ACCEPT) {
            return $this->finalizeVerifiedPaid($attempt, $appTransId, $paidAmount, $zpTransId);
        }

        return $this->recordAuthoritativeFailure($attempt, $appTransId, $returnCode);
    }

    /**
     * Authoritative PAID: amount lock, lifecycle PAID transition, order
     * finalization, customer success session.
     *
     * @param PaymentAttemptInterface $attempt
     * @param string $appTransId
     * @param int $paidAmount
     * @param string $zpTransId
     * @return string Redirect path for the controller.
     * @throws LocalizedException
     */
    private function finalizeVerifiedPaid(
        PaymentAttemptInterface $attempt,
        string $appTransId,
        int $paidAmount,
        string $zpTransId
    ): string {
        // Amount lock: provider-confirmed amount vs the persisted snapshot —
        // never re-converted through FX. Mismatch = evidence persisted by the
        // lifecycle, NO order, customer-safe failure.
        if ($paidAmount !== (int)$attempt->getAmount()) {
            $this->logger->critical(
                'ZaloPay amount mismatch: refusing automatic order placement.',
                [
                    'app_trans_id' => $appTransId,
                    'paid_amount' => $paidAmount,
                    'snapshot_amount' => (int)$attempt->getAmount(),
                    'attempt_id' => $attempt->getEntityId(),
                ]
            );
            $this->lifecycle->recordAmountMismatch($appTransId, $paidAmount, 'Return', $zpTransId !== '' ? $zpTransId : null);
            throw new LocalizedException(
                __('Payment amount mismatch detected. Please contact support with reference %1.', $appTransId)
            );
        }

        // v2/query confirmation IS the payment proof. The lifecycle applies
        // the PAID transition on the LOCKED fresh row (idempotent for PAID/
        // FINALIZED — e.g. the IPN got here first).
        $fresh = $this->lifecycle->recordVerifiedPaid($appTransId, $zpTransId !== '' ? $zpTransId : null);
        $status = $fresh->getPaymentStatus();
        if ($status !== PaymentAttemptInterface::STATUS_PAID
            && $status !== PaymentAttemptInterface::STATUS_FINALIZED
        ) {
            // Money-real PAID evidence arrived on a FAILED/STALE/EXPIRED
            // attempt: the evidence and provider transaction identity are
            // persisted, the terminal state is NOT broadened and NO order
            // follows — manual reconciliation.
            $this->logger->critical(
                'ZaloPay return: authoritative PAID evidence on non-payable attempt state; no order placed.',
                [
                    'app_trans_id' => $appTransId,
                    'payment_status' => $status,
                    'attempt_id' => $fresh->getEntityId(),
                ]
            );
            throw new LocalizedException(
                __(
                    'We could not match your payment to your current cart. '
                    . 'Please contact support with reference %1.',
                    $appTransId
                )
            );
        }

        try {
            $order = $this->orderFinalizer->finalizeOrRecover($fresh, $zpTransId);
        } catch (ContractMismatchException $e) {
            // Provider money is real but the quote/order contract cannot be
            // verified — the finalizer already recorded the reason and kept
            // the money-real state. Surface a customer-safe message.
            throw new LocalizedException(
                __(
                    'We could not match your payment to your current cart. '
                    . 'Please contact support with reference %1.',
                    $appTransId
                )
            );
        }

        // Customer concern, Return path only: the success page validates.
        // Covers BOTH fresh placement and recovery of an order the IPN
        // finalized earlier (IPN itself never touches the session).
        $this->successSessionPreparer->prepare($fresh, $order);

        return 'checkout/onepage/success';
    }

    /**
     * Authoritative non-paid, non-processing result: transition ONLY where
     * the persisted state permits — a stale browser hit can never regress a
     * money-real (PAID) or finalized attempt.
     *
     * @param PaymentAttemptInterface $attempt
     * @param string $appTransId
     * @param int $returnCode
     * @return string Redirect path for the controller.
     * @throws LocalizedException
     */
    private function recordAuthoritativeFailure(
        PaymentAttemptInterface $attempt,
        string $appTransId,
        int $returnCode
    ): string {
        $fresh = $this->lifecycle->recordVerifiedFailure(
            $appTransId,
            sprintf('v2/query return_code %d.', $returnCode),
            'failed'
        );

        if ($fresh->getPaymentStatus() === PaymentAttemptInterface::STATUS_FINALIZED) {
            // Finalized earlier on prior authoritative proof — the order
            // exists; recover it and send the customer to the success page.
            try {
                $order = $this->orderFinalizer->finalizeOrRecover($fresh);
            } catch (ContractMismatchException $e) {
                throw new LocalizedException(
                    __(
                        'We could not match your payment to your current cart. '
                        . 'Please contact support with reference %1.',
                        $appTransId
                    )
                );
            }
            $this->successSessionPreparer->prepare($fresh, $order);

            return 'checkout/onepage/success';
        }

        if ($fresh->getPaymentStatus() === PaymentAttemptInterface::STATUS_PAID) {
            // Conflicting authoritative evidence (PAID recorded by a prior
            // proof, this query says otherwise): the lifecycle kept the
            // money-real state and recorded the conflict — never regress,
            // never place, manual reconciliation.
            $this->logger->critical(
                'ZaloPay return: authoritative failure conflicts with recorded PAID; kept money-real.',
                ['app_trans_id' => $appTransId, 'return_code' => $returnCode, 'attempt_id' => $fresh->getEntityId()]
            );
        }

        throw new LocalizedException(__('Your ZaloPay payment was not completed.'));
    }

    /**
     * Run the authoritative v2/query for the attempt (OUTSIDE any DB
     * transaction — verification never holds row locks).
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
                'ZaloPay v2/query failed for return verification: ' . $e->getMessage(),
                ['app_trans_id' => $appTransId]
            );
            throw new LocalizedException(
                __('ZaloPay payment could not be verified right now. Please try again or contact support.')
            );
        }

        return $result->get();
    }
}
