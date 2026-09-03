<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Exception\ContractMismatchException;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Secomm\ZaloPay\Model\QuoteContractFingerprint;

/**
 * Idempotent creation of the Magento Order from a VERIFIED paid attempt
 * (ZALOPAY-PAYMENT-FIRST Phase 1 + review fixes for both lifecycle
 * blockers).
 *
 * One DB transaction with the attempt row locked (SELECT ... FOR UPDATE on
 * app_trans_id) — see the class-level atomicity note below:
 *  - FINALIZED already   -> recover the BOUND order (binding validated) and
 *                           rebuild the checkout success session — the ONLY
 *                           place session state is written (BLOCKER 2);
 *  - PAID                -> verify the CURRENT quote still matches the paid
 *                           payment contract (amount + fingerprint,
 *                           BLOCKER 1), then place, bind, FINALIZE, capture;
 *  - anything else       -> refused (illegal transition).
 *
 * Contract mismatch (quote edited after payment, bound order missing/wrong):
 * NO order is created, the attempt keeps its money-real state (PAID or
 * FINALIZED — never FAILED), the reason lands in last_error for
 * reconciliation, and a ContractMismatchException surfaces.
 *
 * Atomicity (reviewed with source evidence, vendor/magento/framework/DB/
 * Adapter/Pdo/Mysql.php:371-435): nested begin/commit only adjust the
 * transaction LEVEL — the real BEGIN runs at level 0 and the real COMMIT at
 * level 1; a nested rollBack flags the whole transaction and the outermost
 * boundary performs the real ROLLBACK. There are NO savepoints: the unit is
 * flattened. The placeOrder chain (QuoteManagement -> OrderManagement::place
 * -> MSI AppendReservationsAfterOrderPlacementPlugin) opens no transaction of
 * its own and writes synchronously on the same connection, and the ZaloPay
 * `capture` command is a NullCommand (no HTTP). Therefore placeOrder + MSI
 * reservations + attempt FINALIZED + local capture commit together at THIS
 * class's commit() — or not at all. An intermediate ORDER_CREATED state is
 * unnecessary.
 */
class OrderFinalizer
{
    /**
     * OrderFinalizer constructor.
     *
     * @param PaymentAttemptRepositoryInterface $repository
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagement
     * @param CartRepositoryInterface $cartRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ConfigInterface $config
     * @param MethodInterface $method
     * @param Rate $rate
     * @param QuoteContractFingerprint $fingerprint
     * @param ResourceConnection $resourceConnection
     * @param Session $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly \Magento\Quote\Api\CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface           $cartRepository,
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly SearchCriteriaBuilder             $searchCriteriaBuilder,
        private readonly ConfigInterface                   $config,
        private readonly MethodInterface                   $method,
        private readonly Rate                              $rate,
        private readonly QuoteContractFingerprint          $fingerprint,
        private readonly ResourceConnection                $resourceConnection,
        private readonly Session                           $checkoutSession,
        private readonly LoggerInterface                   $logger
    ) {
    }

    /**
     * Finalize (or recover) the order for a verified-paid attempt.
     *
     * @param PaymentAttemptInterface $attempt Attempt in PAID state, or FINALIZED for recovery.
     * @param string $providerTransactionId ZaloPay zp_trans_id (may be empty when unavailable).
     * @return OrderInterface The bound order.
     * @throws ContractMismatchException No automatic finalization possible — manual reconciliation.
     * @throws LocalizedException
     */
    public function finalizeOrRecover(
        PaymentAttemptInterface $attempt,
        string $providerTransactionId = ''
    ): OrderInterface {
        $appTransId = (string)$attempt->getAppTransId();
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        $locked = null;
        try {
            $locked = $this->repository->lockByAppTransId($appTransId);
            if ($locked === null) {
                throw new LocalizedException(
                    __('ZaloPay payment attempt "%1" disappeared during finalization.', $appTransId)
                );
            }

            if ($locked->getPaymentStatus() === PaymentAttemptInterface::STATUS_FINALIZED) {
                // Duplicate return/callback: recover the bound order and
                // rebuild the success session (BLOCKER 2). Validation inside
                // recoverBoundOrder throws on a broken binding.
                $existing = $this->recoverBoundOrder($locked, $appTransId);
                $connection->commit();
                $this->prepareSuccessSession($locked, $existing);

                return $existing;
            }

            if (!$locked->canTransitionTo(PaymentAttemptInterface::STATUS_FINALIZED)) {
                throw new LocalizedException(
                    __(
                        'ZaloPay attempt "%1" cannot be finalized from state "%2".',
                        $appTransId,
                        $locked->getPaymentStatus()
                    )
                );
            }

            $order = $locked->getOrderId()
                ? $this->recoverBoundOrder($locked, $appTransId)
                : $this->placeOrderForAttempt($locked);

            if ($providerTransactionId !== '') {
                $locked->setProviderTransactionId($providerTransactionId);
            }
            $locked->markFinalized((int)$order->getEntityId());
            $this->repository->save($locked);

            $this->captureOrder($order, $appTransId, $providerTransactionId);

            $connection->commit();
        } catch (ContractMismatchException $exception) {
            $connection->rollBack();
            // Persist AFTER the rollback so the reason survives; the attempt
            // keeps its money-real state (PAID/FINALIZED) — never FAILED.
            $this->recordContractMismatch($locked ?? $attempt, $exception, $appTransId);
            throw $exception;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->prepareSuccessSession($attempt, $order);

        return $order;
    }

    /**
     * Legacy alias kept for callers that pre-date the review rename.
     *
     * @param PaymentAttemptInterface $attempt
     * @param string $providerTransactionId
     * @return OrderInterface
     * @throws ContractMismatchException
     * @throws LocalizedException
     */
    public function finalize(PaymentAttemptInterface $attempt, string $providerTransactionId = ''): OrderInterface
    {
        return $this->finalizeOrRecover($attempt, $providerTransactionId);
    }

    /**
     * Place the order from the attempt's quote — but only after the CURRENT
     * quote is proven to still represent the paid contract (BLOCKER 1).
     * An inactive/absent quote falls back to recovering the order by the
     * reserved increment id (quote already submitted by a concurrent
     * finalizer or an order-first path).
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
            try {
                $orderId = $this->cartManagement->placeOrder((int)$attempt->getQuoteId());

                return $this->orderRepository->get((int)$orderId);
            } catch (NoSuchEntityException $e) {
                // Race: the quote was validated ACTIVE but submitted between
                // the check and placeOrder — recover by reserved id below.
                $this->logger->debug(
                    'ZaloPay finalizer: quote submitted concurrently, recovering by reserved order id.',
                    ['app_trans_id' => $attempt->getAppTransId()]
                );
            }
        }

        // Quote inactive/absent: the contract can only be honoured by the
        // order that was already created from it (same reserved increment id).
        $order = $this->findOrderByIncrementId((string)$attempt->getReservedOrderId());
        if ($order === null || !$this->isBoundToAttempt($attempt, $order)) {
            throw new ContractMismatchException(
                __(
                    'ZaloPay attempt "%1": the quote is inactive and no matching order exists '
                    . '(reserved_order_id "%2").',
                    $attempt->getAppTransId(),
                    $attempt->getReservedOrderId()
                )
            );
        }

        return $order;
    }

    /**
     * BLOCKER 1 gate: the current quote must still be the contract the
     * provider was paid for — same payment method, same payable VND amount,
     * same contract fingerprint (items/qty/addresses/shipping/discount).
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
                __('ZaloPay attempt "%1": the quote payment method changed.', $attempt->getAppTransId())
            );
        }

        $quote->collectTotals();
        $currentAmount = (int)$this->rate->getVndAmountByCurrency(
            (string)$quote->getQuoteCurrencyCode(),
            (float)$quote->getGrandTotal()
        );
        if ($currentAmount !== (int)$attempt->getAmount()) {
            throw new ContractMismatchException(
                __(
                    'ZaloPay attempt "%1": quote total changed since payment (current %2 VND, contract %3 VND).',
                    $attempt->getAppTransId(),
                    $currentAmount,
                    (int)$attempt->getAmount()
                )
            );
        }

        $currentHash = $this->fingerprint->calculate($quote, $currentAmount);
        if (!$this->fingerprint->matches($attempt->getContractHash(), $currentHash)) {
            throw new ContractMismatchException(
                __(
                    'ZaloPay attempt "%1": quote contract fingerprint mismatch (amount unchanged at %2 VND).',
                    $attempt->getAppTransId(),
                    $currentAmount
                )
            );
        }
    }

    /**
     * The order bound to a FINALIZED (or already-bound) attempt: loaded,
     * identity-validated (increment id, quote id, ZaloPay payment) and
     * returned. Broken bindings are a reconciliation case, never a silent
     * success.
     *
     * @param PaymentAttemptInterface $attempt
     * @param string $appTransId
     * @return OrderInterface
     * @throws ContractMismatchException
     */
    private function recoverBoundOrder(PaymentAttemptInterface $attempt, string $appTransId): OrderInterface
    {
        $orderId = $attempt->getOrderId();
        if ($orderId === null) {
            throw new ContractMismatchException(
                __('ZaloPay attempt "%1" is finalized without a bound order.', $appTransId)
            );
        }
        try {
            /** @var OrderInterface $order */
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            throw new ContractMismatchException(
                __('ZaloPay attempt "%1": bound order %2 no longer exists.', $appTransId, $orderId)
            );
        }
        if (!$this->isBoundToAttempt($attempt, $order)) {
            throw new ContractMismatchException(
                __('ZaloPay attempt "%1": bound order %2 does not match the attempt contract.', $appTransId, $orderId)
            );
        }

        return $order;
    }

    /**
     * Whether the order is provably THIS attempt's order.
     *
     * Identity of the bound order: reserved increment id, originating quote
     * and ZaloPay payment method must all match the attempt.
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
     * Capture a PENDING_PAYMENT order (mirrors UpdateOrderCommand semantics;
     * here the capture is driven by the authoritative v2/query verification).
     * Local only — the ZaloPay gateway `capture` command is a NullCommand,
     * so no HTTP runs inside the transaction.
     *
     * @param OrderInterface $order
     * @param string $appTransId
     * @param string $providerTransactionId
     * @return void
     * @throws LocalizedException
     */
    private function captureOrder(OrderInterface $order, string $appTransId, string $providerTransactionId): void
    {
        if ($order->getState() !== \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
            return;
        }
        $payment = $order->getPayment();
        ContextHelper::assertOrderPayment($payment);
        if ($providerTransactionId !== '') {
            $payment->setTransactionId($providerTransactionId);
        }
        if ($this->config->getValue('payment_action') === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
            $payment->capture();
        }
        $message = __('ZaloPay payment verified via v2/query (app_trans_id: %1).', $appTransId);
        $payment->prependMessage($message);
        /** @var \Magento\Sales\Model\Order $order */
        $order->addCommentToStatusHistory($message);
        $this->orderRepository->save($order);
    }

    /**
     * Persist the reconciliation reason AFTER the outer transaction has been
     * rolled back (a save inside the rollback path would be lost). The
     * attempt keeps its money-real state — FAILED is never used here.
     *
     * @param PaymentAttemptInterface $attempt
     * @param ContractMismatchException $exception
     * @param string $appTransId
     * @return void
     */
    private function recordContractMismatch(
        PaymentAttemptInterface $attempt,
        ContractMismatchException $exception,
        string $appTransId
    ): void {
        $this->logger->critical(
            'ZaloPay contract mismatch — automatic order creation refused.',
            [
                'app_trans_id' => $appTransId,
                'attempt_id' => $attempt->getEntityId(),
                'status' => $attempt->getPaymentStatus(),
                'reason' => $exception->getMessage(),
            ]
        );
        try {
            $attempt->setLastError(
                'Contract mismatch — no automatic order creation: ' . $exception->getMessage()
            );
            $this->repository->save($attempt);
        } catch (\Exception $saveError) {
            $this->logger->critical(
                'ZaloPay contract-mismatch state could not be persisted: ' . $saveError->getMessage(),
                ['app_trans_id' => $appTransId, 'attempt_id' => $attempt->getEntityId()]
            );
        }
    }

    /**
     * Mirror the core Onepage::saveOrder session updates so the standard
     * success page validates after the payment-first redirect flow. This is
     * the ONLY writer of the success-session state (BLOCKER 2).
     *
     * @param PaymentAttemptInterface $attempt
     * @param OrderInterface $order
     * @return void
     */
    private function prepareSuccessSession(PaymentAttemptInterface $attempt, OrderInterface $order): void
    {
        try {
            $this->checkoutSession
                ->setLastQuoteId($attempt->getQuoteId())
                ->setLastSuccessQuoteId($attempt->getQuoteId())
                ->setLastOrderId((int)$order->getEntityId())
                ->setLastRealOrderId((string)$order->getIncrementId())
                ->setLastOrderStatus((string)$order->getState());
        } catch (\Exception $e) {
            $this->logger->error('ZaloPay success session preparation failed: ' . $e->getMessage());
        }
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
