<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\MoMo\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Exception\ContractMismatchException;
use Secomm\MoMo\Model\Config;
use Secomm\MoMo\Model\QuoteContractFingerprint;

/**
 * Idempotent creation of the Magento Order from a VERIFIED paid attempt
 * (MOMO-01 payment-first).
 *
 * One DB transaction with the attempt row locked (SELECT ... FOR UPDATE on
 * order_ref):
 *  - FINALIZED already -> recover the BOUND order (binding validated);
 *  - PAID -> verify the CURRENT quote still matches the paid payment
 *    contract (amount + fingerprint), then place, bind, FINALIZE, capture;
 *  - anything else -> refused (illegal transition).
 *
 * PURE business boundary: no checkout session dependency — customer-facing
 * session state is prepared by SuccessSessionPreparer on the browser Return
 * path only, so the IPN (server-to-server, no browser) never reads or
 * writes a session.
 *
 * Contract mismatch (quote edited after payment, bound order missing/wrong):
 * NO order is created, the attempt keeps its money-real state (PAID or
 * FINALIZED — never FAILED), the reason lands in last_error via
 * PaymentAttemptLifecycle::recordContractMismatch(), and a
 * ContractMismatchException surfaces.
 *
 * Atomicity (vendor/magento/framework/DB/Adapter/Pdo/Mysql.php:371-435):
 * nested begin/commit only adjust the transaction LEVEL — the real BEGIN
 * runs at level 0 and the real COMMIT at level 1; a nested rollBack flags
 * the whole transaction and the outermost boundary performs the real
 * ROLLBACK. There are NO savepoints: the unit is flattened. The placeOrder
 * chain (QuoteManagement -> OrderManagement::place -> MSI
 * AppendReservationsAfterOrderPlacementPlugin) opens no transaction of its
 * own and writes synchronously on the same connection, and the MoMo
 * `capture` command is a NullCommand (no HTTP). Therefore placeOrder + MSI
 * reservations + attempt FINALIZED + local capture commit together at THIS
 * class's commit() — or not at all. An intermediate ORDER_CREATED state is
 * unnecessary.
 *
 * Sole quote -> Sales Order boundary: this class is the ONLY place a MoMo
 * quote is converted automatically. Its internal placeOrder() call is
 * authorized by OrderPlacementAuthorization (opened/cleared around the
 * call) — every other caller of CartManagementInterface::placeOrder is
 * refused by CartManagementPlaceOrderGuard for MoMo quotes.
 */
class OrderFinalizer
{
    /**
     * Age (seconds) at which an unreleased email dispatch claim becomes
     * reclaimable by a later finalize driver: a sender that claimed the
     * dispatch and then crashed (process death between commit and send)
     * must not block the retry forever, while a live send in progress is
     * never double-claimed.
     */
    public const EMAIL_CLAIM_GRACE = 900;

    /**
     * OrderFinalizer constructor.
     *
     * @param PaymentAttemptRepositoryInterface $repository
     * @param CartManagementInterface $cartManagement
     * @param CartRepositoryInterface $cartRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ConfigInterface $config
     * @param MethodInterface $method
     * @param QuoteContractFingerprint $fingerprint
     * @param ResourceConnection $resourceConnection
     * @param OrderPlacementAuthorization $placementAuthorization
     * @param PaymentAttemptLifecycle $lifecycle
     * @param LoggerInterface $logger
     * @param OrderSender $orderSender
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ConfigInterface $config,
        private readonly MethodInterface $method,
        private readonly QuoteContractFingerprint $fingerprint,
        private readonly ResourceConnection $resourceConnection,
        private readonly OrderPlacementAuthorization $placementAuthorization,
        private readonly PaymentAttemptLifecycle $lifecycle,
        private readonly LoggerInterface $logger,
        private readonly OrderSender $orderSender,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * Finalize (or recover) the order for a verified-paid attempt.
     *
     * @param PaymentAttemptInterface $attempt Attempt in PAID state, or FINALIZED for recovery.
     * @param string $providerTransactionId MoMo transId (may be empty when unavailable).
     * @return OrderInterface The bound order.
     * @throws ContractMismatchException No automatic finalization possible — manual reconciliation.
     * @throws LocalizedException
     */
    public function finalizeOrRecover(
        PaymentAttemptInterface $attempt,
        string $providerTransactionId = ''
    ): OrderInterface {
        $orderRef = (string)$attempt->getOrderRef();
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        $locked = null;
        $emailClaimToken = null;
        try {
            $locked = $this->repository->lockByOrderRef($orderRef);
            if ($locked === null) {
                throw new LocalizedException(
                    __('MoMo payment attempt "%1" disappeared during finalization.', $orderRef)
                );
            }

            if ($locked->getRequiresReconciliation()) {
                // Quarantined (amount/contract/provider-id conflict): money
                // is real but automatic placement is structurally refused —
                // only an explicit manual reconciliation may clear it.
                throw new ContractMismatchException(
                    __(
                        'MoMo attempt "%1" requires reconciliation (%2) and cannot be auto-finalized.',
                        $orderRef,
                        (string)$locked->getReconciliationCode()
                    )
                );
            }

            if ($locked->getPaymentStatus() === PaymentAttemptInterface::STATUS_FINALIZED) {
                // Duplicate return/callback: recover the bound order.
                $existing = $this->recoverBoundOrder($locked, $orderRef);
                // Claim the email dispatch INSIDE the transaction: the FOR
                // UPDATE row lock serializes concurrent finalizers, so two
                // concurrent senders can never both claim (no duplicate
                // dispatch).
                $emailClaimToken = $this->claimEmailDispatch($locked);
                $connection->commit();

                // A duplicate Return/IPN on a FINALIZED attempt is also the
                // retry driver for a confirmation email that was never
                // successfully sent (crash between commit and send, or a
                // previous send failure).
                if ($emailClaimToken !== null) {
                    $this->sendConfirmationEmail(
                        $existing,
                        $orderRef,
                        (int)$locked->getEntityId(),
                        $emailClaimToken
                    );
                }

                return $existing;
            }

            if (!$locked->canTransitionTo(PaymentAttemptInterface::STATUS_FINALIZED)) {
                throw new LocalizedException(
                    __(
                        'MoMo attempt "%1" cannot be finalized from state "%2".',
                        $orderRef,
                        $locked->getPaymentStatus()
                    )
                );
            }

            $order = $locked->getOrderId()
                ? $this->recoverBoundOrder($locked, $orderRef)
                : $this->placeOrderForAttempt($locked);

            // The FIRST authoritative provider id owns the row: only
            // backfill when empty — never overwrite a recorded transId.
            if ($providerTransactionId !== '' && $locked->getProviderTransactionId() === null) {
                $locked->setProviderTransactionId($providerTransactionId);
            }
            $locked->markFinalized((int)$order->getEntityId());
            $this->repository->save($locked);

            $this->captureOrder($order, $orderRef, $providerTransactionId);

            // Claim the confirmation email dispatch INSIDE the transaction
            // (row lock held): the placing finalizer is the single sender.
            $emailClaimToken = $this->claimEmailDispatch($locked);

            $connection->commit();
        } catch (ContractMismatchException $exception) {
            $connection->rollBack();
            // Persist AFTER the rollback so the reason survives — through the
            // lifecycle (fresh row lock), NEVER by saving this possibly stale
            // pre-rollback copy. The attempt keeps its money-real state
            // (PAID/FINALIZED) — never FAILED.
            $this->lifecycle->recordContractMismatch($orderRef, (string)$exception->getMessage());
            throw $exception;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        // Send order confirmation email AFTER the DB transaction commits.
        // InitializeCommand intentionally sets canSendNewEmailFlag = false so
        // SubmitObserver skips the email on initial order placement (order is
        // still pending_payment at that point). Now that the order is captured
        // and fully finalized we send it ourselves - only when THIS finalizer
        // holds the dispatch claim (concurrent drivers skip, never duplicate).
        if ($emailClaimToken !== null) {
            $this->sendConfirmationEmail(
                $order,
                $orderRef,
                (int)$locked->getEntityId(),
                $emailClaimToken
            );
        }

        return $order;
    }

    /**
     * Send the order confirmation email AFTER the DB transaction commits —
     * never inside it, never before the payment is verified and captured,
     * and ONLY when the caller holds the dispatch claim (claimed inside the
     * locked finalize transaction — concurrent finalizers can never both
     * send).
     *
     * Retry semantics: the claim marks the in-flight dispatch only and is
     * released after the send attempt (success or failure) — the durable
     * "sent" record is the order's email_sent (OrderSender persists it on a
     * successful synchronous send). A claimed-but-crashed sender's claim is
     * reclaimable by a later finalize driver after EMAIL_CLAIM_GRACE; a
     * failed send releases immediately so the next finalizeOrRecover driver
     * (duplicate Return/IPN) retries right away. A failed send is non-fatal:
     * it must never roll back a successful payment — the order stays
     * FINALIZED.
     *
     * @param OrderInterface $order
     * @param string $orderRef
     * @param int $attemptEntityId
     * @param int $claimToken
     * @return void
     */
    private function sendConfirmationEmail(
        OrderInterface $order,
        string $orderRef,
        int $attemptEntityId,
        int $claimToken
    ): void {
        if ((int)$order->getEmailSent() === 1) {
            // Already emailed by an earlier driver: drop our redundant claim.
            $this->repository->releaseEmailDispatch($attemptEntityId, $claimToken);

            return;
        }
        try {
            $this->orderSender->send($order);
        } catch (\Throwable $e) {
            // Non-fatal: a failed email must not roll back a successful payment.
            $this->logger->critical(
                'MoMo OrderFinalizer: failed to send order confirmation email.',
                ['order_ref' => $orderRef, 'exception' => $e->getMessage()]
            );
        } finally {
            // Release in every path so a failed/crashed send can be retried
            // by the next driver (immediately on failure, after the reclaim
            // grace for a crashed sender).
            $this->repository->releaseEmailDispatch($attemptEntityId, $claimToken);
        }
    }

    /**
     * Claim the confirmation email dispatch for the locked attempt row
     * (must be called inside the finalize transaction while the FOR UPDATE
     * row lock is held — the row lock serializes concurrent claimers).
     *
     * @param PaymentAttemptInterface $locked The FOR UPDATE-locked attempt.
     * @return int|null The claim token when THIS finalizer holds the dispatch, null otherwise.
     */
    private function claimEmailDispatch(PaymentAttemptInterface $locked): ?int
    {
        $token = $this->dateTime->timestamp();

        return $this->repository->claimEmailDispatch(
            (int)$locked->getEntityId(),
            $token,
            self::EMAIL_CLAIM_GRACE
        ) ? $token : null;
    }

    /**
     * Place the order from the attempt's quote — but only after the CURRENT
     * quote is proven to still represent the paid contract. An inactive or
     * absent quote falls back to recovering the order by the reserved
     * increment id (quote already submitted by a concurrent finalizer).
     *
     * @param PaymentAttemptInterface $attempt
     * @return OrderInterface
     * @throws ContractMismatchException
     * @throws LocalizedException
     */
    private function placeOrderForAttempt(PaymentAttemptInterface $attempt): OrderInterface
    {
        $quote = $this->loadCurrentQuote($attempt);

        if ($quote !== null && $quote->getIsActive()) {
            $this->assertQuoteMatchesContract($attempt, $quote);
            // THE internal authorization: payment verification alone (PAID)
            // never permits a MoMo placement — the placeOrder guard only
            // lets the call through when it consumes THIS grant, bound to
            // the exact (quote, attempt, order_ref). Single-use + finally
            // clear: a second generic call in this request can never pass.
            $this->placementAuthorization->grant(
                (int)$attempt->getQuoteId(),
                (int)$attempt->getEntityId(),
                (string)$attempt->getOrderRef()
            );
            try {
                $orderId = $this->cartManagement->placeOrder((int)$attempt->getQuoteId());

                return $this->orderRepository->get((int)$orderId);
            } catch (NoSuchEntityException $e) {
                // Race: the quote was validated ACTIVE but submitted between
                // the check and placeOrder — recover by reserved id below.
                $this->logger->debug(
                    'MoMo finalizer: quote submitted concurrently, recovering by reserved order id.',
                    ['order_ref' => $attempt->getOrderRef()]
                );
            } finally {
                $this->placementAuthorization->clear();
            }
        }

        // Quote inactive/absent: the contract can only be honoured by the
        // order that was already created from it (same reserved increment id).
        $order = $this->findOrderByIncrementId((string)$attempt->getReservedOrderId());
        if ($order === null || !$this->isBoundToAttempt($attempt, $order)) {
            throw new ContractMismatchException(
                __(
                    'MoMo attempt "%1": the quote is inactive and no matching order exists '
                    . '(reserved_order_id "%2").',
                    $attempt->getOrderRef(),
                    $attempt->getReservedOrderId()
                )
            );
        }

        return $order;
    }

    /**
     * Contract gate: the current quote must still be the contract the
     * provider was paid for — same payment method, same frozen VND amount,
     * same contract fingerprint (items/qty/addresses/shipping/discount).
     *
     * MoMo settles VND only: the frozen attempt amount IS the VND snapshot
     * (no FX re-conversion — see spec §4.3.2).
     *
     * @param PaymentAttemptInterface $attempt
     * @param Quote $quote
     * @return void
     * @throws ContractMismatchException
     */
    private function assertQuoteMatchesContract(PaymentAttemptInterface $attempt, Quote $quote): void
    {
        $payment = $quote->getPayment();
        if ($payment === null || $payment->getMethod() !== $this->method->getCode()) {
            throw new ContractMismatchException(
                __('MoMo attempt "%1": the quote payment method changed.', $attempt->getOrderRef())
            );
        }

        $quote->collectTotals();
        $currentAmount = (int)round((float)$quote->getGrandTotal());
        if ($currentAmount !== (int)$attempt->getAmount()) {
            throw new ContractMismatchException(
                __(
                    'MoMo attempt "%1": quote total changed since payment (current %2 VND, contract %3 VND).',
                    $attempt->getOrderRef(),
                    $currentAmount,
                    (int)$attempt->getAmount()
                )
            );
        }

        $currentHash = $this->fingerprint->calculate($quote, $currentAmount);
        if (!$this->fingerprint->matches($attempt->getContractHash(), $currentHash)) {
            throw new ContractMismatchException(
                __(
                    'MoMo attempt "%1": quote contract fingerprint mismatch (amount unchanged at %2 VND).',
                    $attempt->getOrderRef(),
                    $currentAmount
                )
            );
        }
    }

    /**
     * The order bound to a FINALIZED (or already-bound) attempt: loaded,
     * identity-validated (increment id, quote id, MoMo payment) and
     * returned. Broken bindings are a reconciliation case, never a silent
     * success.
     *
     * @param PaymentAttemptInterface $attempt
     * @param string $orderRef
     * @return OrderInterface
     * @throws ContractMismatchException
     */
    private function recoverBoundOrder(PaymentAttemptInterface $attempt, string $orderRef): OrderInterface
    {
        $orderId = $attempt->getOrderId();
        if ($orderId === null) {
            throw new ContractMismatchException(
                __('MoMo attempt "%1" is finalized without a bound order.', $orderRef)
            );
        }
        try {
            /** @var OrderInterface $order */
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            throw new ContractMismatchException(
                __('MoMo attempt "%1": bound order %2 no longer exists.', $orderRef, $orderId)
            );
        }
        if (!$this->isBoundToAttempt($attempt, $order)) {
            throw new ContractMismatchException(
                __('MoMo attempt "%1": bound order %2 does not match the attempt contract.', $orderRef, $orderId)
            );
        }

        return $order;
    }

    /**
     * Whether the order is provably THIS attempt's order.
     *
     * Identity of the bound order: reserved increment id, originating quote
     * and MoMo payment method must all match the attempt.
     *
     * @param PaymentAttemptInterface $attempt
     * @param OrderInterface $order
     * @return bool
     */
    private function isBoundToAttempt(PaymentAttemptInterface $attempt, OrderInterface $order): bool
    {
        if ($attempt->getReservedOrderId() !== '' && $order->getIncrementId() !== $attempt->getReservedOrderId()) {
            return false;
        }
        if ((int)$order->getQuoteId() > 0 && (int)$order->getQuoteId() !== (int)$attempt->getQuoteId()) {
            return false;
        }
        $payment = $order->getPayment();

        return $payment !== null && $payment->getMethod() === $this->method->getCode();
    }

    /**
     * Load the CURRENT quote behind the attempt (null when absent).
     *
     * @param PaymentAttemptInterface $attempt
     * @return Quote|null
     */
    private function loadCurrentQuote(PaymentAttemptInterface $attempt): ?Quote
    {
        try {
            $quote = $this->cartRepository->get((int)$attempt->getQuoteId());
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $quote instanceof Quote ? $quote : null;
    }

    /**
     * Capture a PENDING_PAYMENT order — the authoritative IPN/v2/query
     * verification has already confirmed the money, so the local capture
     * finalises the order state. Local only — the MoMo gateway `capture`
     * command is a NullCommand, so no HTTP runs inside the transaction.
     *
     * Also binds the MoMo identity onto the order payment
     * (`momo_order_ref` + `momo_trans_id` additional information): the
     * refund path resolves the provider transaction from there (legacy
     * orders fall back to the increment id inside RefundBuilder).
     *
     * The capture gate reads the canonical Payment Action config key
     * `payment/momo_payment/payment_action` (MOMO-05 — admin field and
     * runtime read share ONE key; strict comparison: only
     * `authorize_capture` captures, any other/missing value still
     * finalizes the order without capture).
     *
     * @param OrderInterface $order
     * @param string $orderRef
     * @param string $providerTransactionId
     * @return void
     * @throws LocalizedException
     */
    private function captureOrder(OrderInterface $order, string $orderRef, string $providerTransactionId): void
    {
        if ($order->getState() !== \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
            return;
        }
        $payment = $order->getPayment();
        ContextHelper::assertOrderPayment($payment);
        if ($providerTransactionId !== '') {
            $payment->setTransactionId($providerTransactionId);
            $payment->setAdditionalInformation('momo_trans_id', $providerTransactionId);
        }
        $payment->setAdditionalInformation('momo_order_ref', $orderRef);
        if ($this->config->getValue(Config::KEY_PAYMENT_ACTION) === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
            $payment->capture();
        }
        $message = __('MoMo payment verified (order_ref: %1).', $orderRef);
        $payment->prependMessage($message);
        /** @var \Magento\Sales\Model\Order $order */
        $order->addCommentToStatusHistory($message);
        $this->orderRepository->save($order);
    }

    /**
     * Find the order by reserved increment id (null when none exists).
     *
     * @param string $incrementId
     * @return OrderInterface|null
     */
    private function findOrderByIncrementId(string $incrementId): ?OrderInterface
    {
        if ($incrementId === '') {
            return null;
        }
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId)
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        return $orders ? reset($orders) : null;
    }
}
