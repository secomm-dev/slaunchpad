<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\MoMo\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Exception\ContractMismatchException;

/**
 * Return (browser redirect) processing for the payment-first flow
 * (MOMO-01).
 *
 * THE BROWSER REDIRECT CAN NEVER TERMINALLY FAIL A PAYMENT and can never
 * CREATE one either — the browser Return is UX/recovery only (AC7):
 *
 *  1. attempt lookup by the echoed orderId (= order_ref; lookup key only —
 *     no state decisions);
 *  2. browser params — resultCode, transId, signature, everything the
 *     customer's browser carries — are NEVER payment proof; they are not
 *     even parsed. The authoritative server-side v2/query ALWAYS runs and
 *     owns EVERY payment-state decision (spec §4.3.3);
 *  3. the query response is signature-validated inside the command
 *     (QueryValidator: signature + merchant identity + echo) — a
 *     signature-invalid response throws before any mutation;
 *  4. resultCode 7002 (unpaid/still processing): non-terminal — no
 *     mutation, the attempt keeps its current state (the IPN, a later
 *     return hit or MoMo's IPN retry resolves it);
 *  5. authoritative non-paid: FAILED transition ONLY where the persisted
 *     state permits, via PaymentAttemptLifecycle (locked, short
 *     transaction) — never from a stale copy, never out of a terminal or
 *     money-real state;
 *  6. authoritative PAID (resultCode 0): amount lock against the persisted
 *     frozen snapshot, positive-transId identity requirement,
 *     PaymentAttemptLifecycle::recordVerifiedPaid -> OrderFinalizer
 *     (exactly one Sales Order; duplicate returns recover the bound one) ->
 *     SuccessSessionPreparer (AC8: rebuilds the 5 checkout success keys).
 *
 * All attempt mutations go through PaymentAttemptLifecycle — this class
 * never marks or saves an attempt directly.
 */
class ReturnProcessor
{
    /**
     * MoMo v2/query resultCode: the transaction exists but is not paid yet
     * (processing) — per MoMo's query API documentation.
     */
    private const QUERY_PROCESSING = 7002;

    /**
     * ReturnProcessor constructor.
     *
     * @param PaymentAttemptRepositoryInterface $repository
     * @param CommandPoolInterface $commandPool
     * @param OrderFinalizer $orderFinalizer
     * @param PaymentAttemptLifecycle $lifecycle
     * @param SuccessSessionPreparer $successSessionPreparer
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly CommandPoolInterface $commandPool,
        private readonly OrderFinalizer $orderFinalizer,
        private readonly PaymentAttemptLifecycle $lifecycle,
        private readonly SuccessSessionPreparer $successSessionPreparer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process the MoMo return redirect.
     *
     * @param array $params Raw GET params from the redirect (orderId expected).
     * @return string Redirect path for the controller.
     * @throws LocalizedException Always with a customer-safe message on failure.
     */
    public function process(array $params): string
    {
        $orderRef = trim((string)($params['orderId'] ?? ''));
        if ($orderRef === '') {
            throw new LocalizedException(__('Invalid MoMo return payload.'));
        }

        $attempt = $this->repository->getByOrderRef($orderRef);
        if ($attempt === null) {
            $this->logger->warning('MoMo return: no payment attempt found.', ['order_ref' => $orderRef]);
            throw new LocalizedException(__('MoMo payment session not found. Please contact support.'));
        }

        // NOTE: a FINALIZED attempt is NOT short-circuited here — the
        // duplicate return still recovers the bound order and rebuilds the
        // customer success session below.

        // Authoritative server-side verification — EVERY payment-state
        // decision below is derived from THIS result, never from the
        // browser params. Signature validation happens inside the command
        // (QueryValidator); a signature-invalid response throws here with
        // ZERO mutation.
        $query = $this->queryTransaction($orderRef, (string)$attempt->getRequestId(), $attempt);
        $resultCode = isset($query['resultCode']) ? (int)$query['resultCode'] : -1;
        $paidAmount = isset($query['amount']) ? (int)$query['amount'] : 0;
        $transId = (string)($query['transId'] ?? '');

        if ($resultCode === self::QUERY_PROCESSING) {
            // Non-terminal: the provider has not concluded. The attempt
            // keeps its current state — the IPN, a later return hit or the
            // attempt TTL resolves it. No mutation here.
            throw new LocalizedException(
                __('Your MoMo payment is still being processed. Please check back shortly.')
            );
        }

        if ($resultCode === 0) {
            return $this->finalizeVerifiedPaid($attempt, $orderRef, $paidAmount, $transId);
        }

        return $this->recordAuthoritativeFailure($attempt, $orderRef, $resultCode);
    }

    /**
     * Authoritative PAID: amount lock, provider identity requirement,
     * lifecycle PAID transition, order finalization, customer success
     * session.
     *
     * @param PaymentAttemptInterface $attempt
     * @param string $orderRef
     * @param int $paidAmount
     * @param string $transId
     * @return string Redirect path for the controller.
     * @throws LocalizedException
     */
    private function finalizeVerifiedPaid(
        PaymentAttemptInterface $attempt,
        string $orderRef,
        int $paidAmount,
        string $transId
    ): string {
        // Amount lock: provider-confirmed amount vs the persisted frozen
        // snapshot — never re-converted through FX. Mismatch = evidence
        // persisted by the lifecycle, NO order, customer-safe failure (AC4).
        if ($paidAmount !== (int)$attempt->getAmount()) {
            $this->logger->critical(
                'MoMo amount mismatch: refusing automatic order placement.',
                [
                    'order_ref' => $orderRef,
                    'paid_amount' => $paidAmount,
                    'snapshot_amount' => (int)$attempt->getAmount(),
                    'attempt_id' => $attempt->getEntityId(),
                ]
            );
            $this->lifecycle->recordAmountMismatch($orderRef, $paidAmount, 'Return', $transId !== '' ? $transId : null);
            throw new LocalizedException(
                __('Payment amount mismatch detected. Please contact support with reference %1.', $orderRef)
            );
        }

        // Automatic finalization requires a POSITIVE transId (authoritative
        // provider identity): verified money without one is quarantined —
        // never an order.
        if (preg_match('/^\d+$/', $transId) !== 1 || (int)$transId <= 0) {
            $this->logger->critical(
                'MoMo return: verified payment without a positive transId; quarantined for reconciliation, '
                . 'no order placed.',
                ['order_ref' => $orderRef]
            );
            $this->lifecycle->recordProviderIdentityUnavailable($orderRef, 'Return-query');

            throw new LocalizedException(
                __('We could not match your payment to your current cart. Please contact support with reference %1.', $orderRef)
            );
        }

        // v2/query confirmation IS the payment proof. The lifecycle applies
        // the PAID transition on the LOCKED fresh row (idempotent for PAID/
        // FINALIZED — e.g. the IPN got here first).
        $fresh = $this->lifecycle->recordVerifiedPaid($orderRef, $transId);
        $status = $fresh->getPaymentStatus();
        if ($status !== PaymentAttemptInterface::STATUS_PAID
            && $status !== PaymentAttemptInterface::STATUS_FINALIZED
        ) {
            // Money-real PAID evidence arrived on a FAILED/STALE/EXPIRED
            // attempt: the evidence and provider identity are persisted, the
            // terminal state is NOT broadened and NO order follows — manual
            // reconciliation.
            $this->logger->critical(
                'MoMo return: authoritative PAID evidence on non-payable attempt state; no order placed.',
                [
                    'order_ref' => $orderRef,
                    'payment_status' => $status,
                    'attempt_id' => $fresh->getEntityId(),
                ]
            );
            throw new LocalizedException(
                __(
                    'We could not match your payment to your current cart. '
                    . 'Please contact support with reference %1.',
                    $orderRef
                )
            );
        }

        if ($fresh->getRequiresReconciliation()) {
            // Quarantined during the lifecycle (conflicting identity etc.).
            throw new LocalizedException(
                __(
                    'We could not match your payment to your current cart. '
                    . 'Please contact support with reference %1.',
                    $orderRef
                )
            );
        }

        try {
            $order = $this->orderFinalizer->finalizeOrRecover($fresh, $transId);
        } catch (ContractMismatchException $e) {
            // Provider money is real but the quote/order contract cannot be
            // verified — the finalizer already recorded the reason and kept
            // the money-real state. Surface a customer-safe message.
            throw new LocalizedException(
                __(
                    'We could not match your payment to your current cart. '
                    . 'Please contact support with reference %1.',
                    $orderRef
                )
            );
        }

        // Customer concern, Return path only: the success page validates.
        // Covers BOTH fresh placement and recovery of an order the IPN
        // finalized earlier (IPN itself never touches the session) — AC8.
        $this->successSessionPreparer->prepare($fresh, $order);

        return 'checkout/onepage/success';
    }

    /**
     * Authoritative non-paid, non-processing result: transition ONLY where
     * the persisted state permits — a stale browser hit can never regress a
     * money-real (PAID) or finalized attempt.
     *
     * @param PaymentAttemptInterface $attempt
     * @param string $orderRef
     * @param int $resultCode
     * @return string Redirect path for the controller.
     * @throws LocalizedException
     */
    private function recordAuthoritativeFailure(
        PaymentAttemptInterface $attempt,
        string $orderRef,
        int $resultCode
    ): string {
        $fresh = $this->lifecycle->recordVerifiedFailure(
            $orderRef,
            sprintf('v2/query resultCode %d.', $resultCode),
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
                        $orderRef
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
                'MoMo return: authoritative failure conflicts with recorded PAID; kept money-real.',
                ['order_ref' => $orderRef, 'result_code' => $resultCode, 'attempt_id' => $fresh->getEntityId()]
            );
        }

        throw new LocalizedException(__('Your MoMo payment was not completed.'));
    }

    /**
     * Run the authoritative v2/query for the attempt (OUTSIDE any DB
     * transaction — verification never holds row locks).
     *
     * @param string $orderRef
     * @param string $requestId
     * @param PaymentAttemptInterface $attempt Carried for the response
     *        validator's echo checks.
     * @return array
     * @throws LocalizedException
     */
    private function queryTransaction(string $orderRef, string $requestId, PaymentAttemptInterface $attempt): array
    {
        try {
            $result = $this->commandPool->get('query_transaction')->execute(
                ['order_ref' => $orderRef, 'request_id' => $requestId, 'attempt' => $attempt]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'MoMo v2/query failed for return verification: ' . $e->getMessage(),
                ['order_ref' => $orderRef]
            );
            throw new LocalizedException(
                __('MoMo payment could not be verified right now. Please try again or contact support.')
            );
        }

        return $result->get();
    }
}
