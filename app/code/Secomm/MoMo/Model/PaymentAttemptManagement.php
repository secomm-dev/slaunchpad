<?php
/**
 * Payment-first initiation for MoMo (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Gateway\Helper\TransactionReader;

/**
 * Active Quote -> validate -> collectTotals -> VND amount snapshot ->
 * persist an INITIATED attempt (unique order_ref/request_id + contract
 * fingerprint + TTL) -> MoMo create-order API (HTTP OUTSIDE any DB
 * transaction) -> store the pay URL (ACTIVE).
 *
 * NO Magento order exists at any point in this flow.
 *
 * Idempotency is data-level: a reusable ACTIVE attempt whose amount AND
 * contract fingerprint match the current quote is returned as-is (no
 * second provider transaction); anything else transitions explicitly
 * (STALE) and a new attempt is minted. The quote row is briefly locked
 * (SELECT ... FOR UPDATE) to serialize concurrent Starts for the same
 * cart; under the same lock money-real/quarantined evidence BLOCKS any
 * new provider transaction (double-payment guard).
 */
class PaymentAttemptManagement
{
    /**
     * Default attempt TTL in minutes when no config value is set. MoMo
     * itself expires an unpaid wallet order after ~15 minutes.
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
     * @param OrderRefBuilder $orderRefBuilder
     * @param QuoteContractFingerprint $fingerprint
     * @param MethodInterface $method
     * @param ConfigInterface $config
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CommandPoolInterface $commandPool,
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly PaymentAttemptFactory $paymentAttemptFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PaymentDataObjectFactory $paymentDataObjectFactory,
        private readonly OrderRefBuilder $orderRefBuilder,
        private readonly QuoteContractFingerprint $fingerprint,
        private readonly MethodInterface $method,
        private readonly ConfigInterface $config,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Whether the quote can enter the payment-first flow right now.
     *
     * Active, non-empty, paying with MoMo, and in MoMo's settlement
     * currency (VND).
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
        if ($payment === null || $payment->getMethod() !== $this->method->getCode()) {
            return false;
        }

        return strtoupper((string)$quote->getQuoteCurrencyCode()) === PaymentAttemptInterface::CURRENCY_VND;
    }

    /**
     * Initiate (or reuse) a MoMo payment attempt for the given active quote.
     *
     * @param Quote $quote
     * @return PaymentAttemptInterface ACTIVE attempt carrying the pay URL.
     * @throws LocalizedException When the quote is not initiable, totals are
     *         empty, another payment is in flight, or the provider rejects
     *         the transaction creation.
     */
    public function initiate(Quote $quote): PaymentAttemptInterface
    {
        if (!$this->isInitiable($quote)) {
            throw new LocalizedException(
                __('The cart is no longer payable with MoMo. Please refresh your cart and try again.')
            );
        }
        $quote->collectTotals();
        if ((float)$quote->getGrandTotal() <= 0) {
            throw new LocalizedException(
                __('Your cart total is zero. Please add products before paying with MoMo.')
            );
        }

        $amount = (int)round((float)$quote->getGrandTotal());
        $attempt = $this->createOrReuseAttempt($quote, $amount);
        if ($attempt->isReusable() && $attempt->getAmount() === $amount) {
            return $attempt; // Reused ACTIVE attempt: NO second provider transaction.
        }

        // Provider call OUTSIDE any DB transaction — never hold locks over HTTP.
        try {
            $result = $this->commandPool->get('get_pay_url')->execute(
                [
                    'payment' => $this->paymentDataObjectFactory->create($quote->getPayment()),
                    'amount' => (float)$quote->getGrandTotal(),
                    'attempt' => $attempt,
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
                $this->logger->critical('MoMo attempt failure could not be persisted: ' . $saveError->getMessage());
            }
            throw $e;
        }

        return $attempt;
    }

    /**
     * Guarded attempt creation. Runs in a short DB transaction that locks
     * the quote row: concurrent Starts serialize here.
     *
     * Double-payment guard: under the SAME quote lock, money-real or
     * quarantined evidence (PAID, FINALIZED, requires_reconciliation) BLOCKS
     * a new attempt — a customer clicking Pay again after a paid-but-not-
     * yet-finalized attempt can never mint a second provider transaction.
     * An unexpired INITIATED attempt is another Start's in-flight provider
     * transaction and is refused retry-safely. Otherwise a matching reusable
     * ACTIVE attempt is reused, a mismatching one is STALE-marked and a new
     * attempt is minted with its order_ref persisted before the provider call.
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

            $blocking = $this->repository->getBlockingAttemptByQuoteId($quoteId);
            if ($blocking !== null) {
                throw new LocalizedException($this->blockingMessage($blocking));
            }

            $existing = $this->repository->getActiveByQuoteId($quoteId);
            if ($existing !== null) {
                if ($existing->getPaymentStatus() === PaymentAttemptInterface::STATUS_INITIATED
                    && !$existing->isExpired()
                ) {
                    // An unexpired INITIATED row is another Start's in-flight
                    // provider transaction — never stale-mark it into a
                    // SECOND provider transaction.
                    throw new LocalizedException(
                        __('Your MoMo payment is being initialized. Please wait a moment and try again.')
                    );
                }
                // Reuse ONLY when the whole contract is unchanged: a qty or
                // address edit landing on the same total must not reuse a
                // pay URL minted for a different contract.
                if ($existing->isReusable() && $existing->getAmount() === $amount
                    && $this->fingerprint->matches(
                        $existing->getContractHash(),
                        $this->fingerprint->calculate($quote, $amount)
                    )
                ) {
                    $connection->commit();

                    return $existing;
                }
                // Contract changed or the in-flight attempt never became
                // ACTIVE: explicit transition, then a fresh attempt below.
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
            $orderRef = $this->orderRefBuilder->buildOrderRef((string)$quote->getReservedOrderId());
            $attempt->setOrderRef($orderRef);
            $attempt->setRequestId($this->orderRefBuilder->buildRequestId($orderRef));
            $this->repository->save($attempt); // unique order_ref = hard duplicate guard

            $connection->commit();

            return $attempt;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Customer-safe refusal for a quote that must not start a second
     * payment. No internal identifiers, no jargon.
     *
     * @param PaymentAttemptInterface $blocking
     * @return \Magento\Framework\Phrase
     */
    private function blockingMessage(PaymentAttemptInterface $blocking): \Magento\Framework\Phrase
    {
        if ($blocking->getRequiresReconciliation()) {
            return __(
                'Your previous MoMo payment is under review. Please contact customer support '
                . 'before trying again — do not pay twice for the same cart.'
            );
        }
        $status = $blocking->getPaymentStatus();
        if ($status === PaymentAttemptInterface::STATUS_PAID) {
            return __(
                'Your MoMo payment has already been received and is being finalized. '
                . 'Please do not pay again — the order will appear shortly.'
            );
        }
        if ($status === PaymentAttemptInterface::STATUS_FINALIZED) {
            return __(
                'Your MoMo payment has already been received and the order has been created. '
                . 'Please do not pay again.'
            );
        }

        return __(
            'Your previous MoMo payment is under review. Please contact customer support '
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
