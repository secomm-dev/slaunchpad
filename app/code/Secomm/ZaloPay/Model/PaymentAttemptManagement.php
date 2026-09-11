<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Secomm\ZaloPay\Gateway\Helper\TransactionReader;
use Secomm\ZaloPay\Model\QuoteContractFingerprint;

/**
 * Payment-first initiation (ZALOPAY-PAYMENT-FIRST Phase 1).
 *
 * Active Quote -> validate -> collectTotals -> reserveOrderId (persisted)
 * -> snapshot the exact VND amount -> persist an INITIATED attempt
 * (unique app_trans_id) -> create the ZaloPay transaction -> store the
 * pay URL (ACTIVE). No Magento order exists at any point in this flow.
 *
 * Idempotency is data-level: a reusable ACTIVE attempt whose snapshot
 * matches the current quote total is returned as-is (no second provider
 * transaction); anything else transitions explicitly (STALE) and a new
 * attempt is minted. The quote row is briefly locked (SELECT ... FOR
 * UPDATE) to serialize concurrent Starts for the same cart.
 */
class PaymentAttemptManagement
{
    /**
     * Default attempt TTL in minutes when no config value is set. ZaloPay
     * itself expires an unpaid order after ~15 minutes.
     */
    public const DEFAULT_ATTEMPT_TTL = 15;

    /**
     * PaymentAttemptManagement constructor.
     *
     * @param CommandPoolInterface $commandPool
     * @param PaymentAttemptRepositoryInterface $repository
     * @param PaymentAttemptFactory $paymentAttemptFactory
     * @param CartRepositoryInterface $cartRepository
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param Rate $rate
     * @param AppTransIdBuilder $appTransIdBuilder
     * @param QuoteContractFingerprint $fingerprint
     * @param MethodInterface $method
     * @param ConfigInterface $config
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CommandPoolInterface              $commandPool,
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly PaymentAttemptFactory             $paymentAttemptFactory,
        private readonly CartRepositoryInterface           $cartRepository,
        private readonly PaymentDataObjectFactory          $paymentDataObjectFactory,
        private readonly Rate                              $rate,
        private readonly AppTransIdBuilder                 $appTransIdBuilder,
        private readonly QuoteContractFingerprint          $fingerprint,
        private readonly MethodInterface                   $method,
        private readonly ConfigInterface                   $config,
        private readonly ResourceConnection                $resourceConnection,
        private readonly LoggerInterface                   $logger
    ) {
    }

    /**
     * Whether the quote can enter the payment-first flow right now.
     *
     * Active, non-empty, with ZaloPay selected as its payment method.
     *
     * @param Quote $quote
     * @return bool
     */
    public function isInitiable(Quote $quote): bool
    {
        if (!$quote->getId() || !$quote->getIsActive() || $quote->getItemsCount() < 1) {
            return false;
        }
        $payment = $quote->getPayment();

        return $payment !== null && $payment->getMethod() === $this->method->getCode();
    }

    /**
     * Initiate (or reuse) a ZaloPay payment attempt for the given active quote.
     *
     * @param Quote $quote
     * @return PaymentAttemptInterface ACTIVE attempt carrying the pay URL.
     * @throws LocalizedException When the quote is not initiable, totals are
     * empty or the provider rejects the transaction creation.
     * @throws \Exception On gateway errors (attempt already marked FAILED).
     */
    public function initiate(Quote $quote): PaymentAttemptInterface
    {
        if (!$this->isInitiable($quote)) {
            throw new LocalizedException(
                __('The cart is no longer payable with ZaloPay. Please refresh your cart and try again.')
            );
        }
        $quote->collectTotals();
        if ((float)$quote->getGrandTotal() <= 0) {
            throw new LocalizedException(
                __('Your cart total is zero. Please add products before paying with ZaloPay.')
            );
        }

        $quoteId = (int)$quote->getId();
        $amount = (int)$this->rate->getVndAmountByCurrency(
            (string)$quote->getQuoteCurrencyCode(),
            (float)$quote->getGrandTotal()
        );

        $attempt = $this->createOrReuseAttempt($quote, $amount);
        if ($attempt->isReusable() && (int)$attempt->getAmount() === $amount) {
            return $attempt; // Reused ACTIVE attempt: NO second provider transaction.
        }

        // Provider call OUTSIDE any DB transaction — never hold locks over HTTP.
        try {
            $result = $this->commandPool->get('get_pay_url')->execute(
                [
                    'payment' => $this->paymentDataObjectFactory->create($quote->getPayment()),
                    'amount' => (float)$quote->getGrandTotal(),
                    'currency' => (string)$quote->getQuoteCurrencyCode(),
                    'app_trans_id' => (string)$attempt->getAppTransId(),
                    'quote' => $quote,
                ]
            );
            $attempt->markActive(TransactionReader::readPayUrl($result->get()));
            $this->repository->save($attempt);
        } catch (\Exception $e) {
            try {
                $attempt->markFailed($e->getMessage());
                $this->repository->save($attempt);
            } catch (\Exception $saveError) {
                $this->logger->critical('ZaloPay attempt failure could not be persisted: ' . $saveError->getMessage());
            }
            throw $e;
        }

        return $attempt;
    }

    /**
     * Guarded attempt creation. Runs in a short DB transaction that locks the
     * quote row: concurrent Starts serialize here.
     *
     * DOUBLE-PAYMENT GUARD (corrective round 4, Blocker 2): under the SAME
     * quote lock, BEFORE any reuse/stale/mint decision, the quote's attempt
     * history is inspected for money-real or quarantined rows (PAID,
     * FINALIZED, requires_reconciliation — structured flags only, never
     * last_error parsing). When one exists, NO new attempt and NO new
     * provider transaction is created — a customer clicking Pay again after
     * a paid-but-not-yet-finalized attempt can never mint a second provider
     * transaction (double-charge protection). Convergence to FINALIZED is
     * owned by IPN/Return/PaymentRecovery; the OrderFinalizer is NEVER
     * invoked while the quote row lock is held.
     *
     * A pending INITIATED attempt that has not expired is another Start's
     * in-flight provider transaction: a concurrent Start refuses with a
     * retry-safe message instead of stale-marking it and minting a second
     * provider transaction (at most ONE provider transaction per payment).
     *
     * Otherwise a matching reusable ACTIVE attempt is returned as-is (no
     * second provider transaction); anything else transitions explicitly
     * (STALE) and a new attempt is minted whose app_trans_id is persisted
     * before the provider call.
     *
     * @param Quote $quote
     * @param int $amount VND snapshot of the current quote total.
     * @return PaymentAttemptInterface
     * @throws LocalizedException
     */
    private function createOrReuseAttempt(Quote $quote, int $amount): PaymentAttemptInterface
    {
        $quoteId = (int)$quote->getId();
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $connection->fetchOne(
                $connection->select()
                    ->from($this->resourceConnection->getTableName('quote'), 'entity_id')
                    ->where('entity_id = ?', $quoteId)
                    ->forUpdate(true)
            );

            // BLOCKER 2 (round 4): money-real/quarantined evidence BLOCKS a
            // new provider transaction. Bounded lookup (LIMIT 1), under the
            // quote lock, so two concurrent Starts can never both decide
            // "nothing blocks" and both mint a transaction.
            $blocking = $this->repository->getBlockingAttemptByQuoteId($quoteId);
            if ($blocking !== null) {
                throw new LocalizedException($this->blockingMessage($blocking));
            }

            $existing = $this->repository->getActiveByQuoteId($quoteId);
            if ($existing !== null) {
                if ($existing->getPaymentStatus() === PaymentAttemptInterface::STATUS_INITIATED
                    && !$existing->isExpired()
                ) {
                    // Round 4 (concurrency): an unexpired INITIATED row is
                    // another Start's in-flight provider transaction — never
                    // stale-mark it into a SECOND provider transaction.
                    throw new LocalizedException(
                        __(
                            'Your ZaloPay payment is being initialized. Please wait a moment and try again.'
                        )
                    );
                }
                // Reuse ONLY when the whole contract is unchanged: a qty or
                // address edit landing on the same total must not reuse a
                // pay URL minted for a different contract (BLOCKER 1).
                if ($existing->isReusable() && (int)$existing->getAmount() === $amount
                    && $this->fingerprint->matches(
                        $existing->getContractHash(),
                        $this->fingerprint->calculate($quote, $amount)
                    )
                ) {
                    $connection->commit();

                    return $existing; // Same contract, still valid: reuse, no new provider transaction.
                }
                // Contract changed or the in-flight attempt never became
                // ACTIVE: explicit transition, then a fresh attempt below.
                // (Only reachable with NO money-real evidence — the blocking
                // check above already refused those.)
                $existing->markStale();
                $this->repository->save($existing);
            }

            if (!(string)$quote->getReservedOrderId()) {
                $quote->reserveOrderId();
                $this->cartRepository->save($quote);
            }

            $attempt = $this->paymentAttemptFactory->create();
            $attempt->setQuoteId($quoteId);
            $attempt->setReservedOrderId((string)$quote->getReservedOrderId());
            $attempt->setAmount($amount);
            $attempt->setCurrency(PaymentAttemptInterface::CURRENCY_VND);
            // Lock the payment contract this provider transaction pays for.
            $attempt->setContractHash($this->fingerprint->calculate($quote, $amount));
            $attempt->setStoreId((int)$quote->getStoreId());
            $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_INITIATED);
            $attempt->setRetryCount(count($this->repository->getListByQuoteId($quoteId)));
            $attempt->setExpiresAt($this->getExpiryTime());
            $attempt->setAppTransId(
                $this->appTransIdBuilder->build((string)$quote->getReservedOrderId())
            );
            $this->repository->save($attempt); // unique app_trans_id = hard duplicate guard

            $connection->commit();

            return $attempt;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Customer-safe refusal for a quote that must not start a second
     * payment (corrective round 4, Blocker 2). No internal identifiers,
     * no jargon. The quarantine check comes FIRST: a quarantined row will
     * NOT auto-finalize, so even a PAID one must never be promised
     * "the order will appear shortly" — it reads as "under review — do
     * not pay twice". PAID-but-unfinalized reads as "payment received,
     * being finalized"; FINALIZED as "order created".
     *
     * @param PaymentAttemptInterface $blocking
     * @return \Magento\Framework\Phrase
     */
    private function blockingMessage(PaymentAttemptInterface $blocking): \Magento\Framework\Phrase
    {
        if ($blocking->getRequiresReconciliation()) {
            return __(
                'Your previous ZaloPay payment is under review. Please contact customer support '
                . 'before trying again — do not pay twice for the same cart.'
            );
        }
        $status = $blocking->getPaymentStatus();
        if ($status === PaymentAttemptInterface::STATUS_PAID) {
            return __(
                'Your ZaloPay payment has already been received and is being finalized. '
                . 'Please do not pay again — the order will appear shortly.'
            );
        }
        if ($status === PaymentAttemptInterface::STATUS_FINALIZED) {
            return __(
                'Your ZaloPay payment has already been received and the order has been created. '
                . 'Please do not pay again.'
            );
        }

        return __(
            'Your previous ZaloPay payment is under review. Please contact customer support '
            . 'before trying again — do not pay twice for the same cart.'
        );
    }

    /**
     * Expiry timestamp for a fresh attempt (now + configured TTL minutes).
     *
     * @return string
     */
    private function getExpiryTime(): string
    {
        $ttlMinutes = (int)($this->config->getValue('attempt_ttl') ?? self::DEFAULT_ATTEMPT_TTL);
        if ($ttlMinutes <= 0) {
            $ttlMinutes = self::DEFAULT_ATTEMPT_TTL;
        }

        return date('Y-m-d H:i:s', time() + $ttlMinutes * 60);
    }
}
