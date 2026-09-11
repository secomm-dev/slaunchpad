<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Secomm\ZaloPay\Model\AppTransIdBuilder;
use Secomm\ZaloPay\Model\QuoteContractFingerprint;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Model\PaymentAttemptFactory;
use Secomm\ZaloPay\Model\PaymentAttemptManagement;
use Secomm\ZaloPay\Test\Unit\Model\QuoteStub;

/**
 * ZALOPAY-PAYMENT-FIRST Phase 1 unit contract for the initiation flow.
 *
 * Covers the mandatory Phase 1 cases:
 *  1/2/3. Start from the ACTIVE QUOTE: reserved_order_id persisted, NO
 *         Magento order placed anywhere in the flow;
 *  5.    provider transaction reference (app_trans_id) + pay URL persisted;
 *  6.    duplicate Start reuses the ACTIVE attempt — no second provider
 *         transaction;
 *  7.    exact VND amount snapshot persisted at creation;
 *  8.    changed quote total stale-marks the old attempt instead of
 *         silently re-validating it.
 */
class PaymentAttemptManagementTest extends TestCase
{
    /**
     * @var PaymentAttemptRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var PaymentAttemptFactory|MockObject
     */
    private $attemptFactory;

    /**
     * @var CommandPoolInterface|MockObject
     */
    private $commandPool;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepository;

    /**
     * @var Rate|MockObject
     */
    private $rate;

    /**
     * @var AppTransIdBuilder|MockObject
     */
    private $appTransIdBuilder;

    /**
     * @var QuoteContractFingerprint|MockObject
     */
    private $fingerprint;

    /**
     * @var ConfigInterface|MockObject
     */
    private $config;

    /**
     * @var ResourceConnection|MockObject
     */
    private $resourceConnection;

    /**
     * @var PaymentAttemptManagement
     */
    private $management;

    /**
     * @var array Saved attempts, in save order.
     */
    private array $savedAttempts = [];

    /**
     * @var string[] Commands fetched from the pool, in call order.
     */
    private array $commandPoolCalls = [];

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->attemptFactory = $this->createMock(PaymentAttemptFactory::class);
        $this->attemptFactory->method('create')->willReturnCallback(fn () => $this->newAttempt());
        $this->commandPool = $this->createMock(CommandPoolInterface::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->rate = $this->createMock(Rate::class);
        $this->appTransIdBuilder = $this->createMock(AppTransIdBuilder::class);
        $this->appTransIdBuilder->method('build')->willReturn('260826_1000_000000123');
        $this->fingerprint = $this->createMock(QuoteContractFingerprint::class);
        $this->fingerprint->method('calculate')->willReturn('CONTRACT_HASH');
        $this->fingerprint->method('matches')->willReturn(true);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->config->method('getValue')->willReturnCallback(
            fn ($field) => $field === 'attempt_ttl' ? 15 : null
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('forUpdate')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $this->resourceConnection->method('getTableName')->willReturn('quote');

        $paymentDataObjectFactory = $this->createMock(PaymentDataObjectFactory::class);
        $method = $this->createMock(MethodInterface::class);
        $method->method('getCode')->willReturn('zalopay');

        $this->management = new PaymentAttemptManagement(
            $this->commandPool,
            $this->repository,
            $this->attemptFactory,
            $this->cartRepository,
            $paymentDataObjectFactory,
            $this->rate,
            $this->appTransIdBuilder,
            $this->fingerprint,
            $method,
            $this->config,
            $this->resourceConnection,
            $this->createMock(\Psr\Log\LoggerInterface::class)
        );
    }

    /**
     * Cases 1/2/3/5/7: active quote -> attempt persisted (reserved id, VND
     * snapshot, app_trans_id, pay URL) and NOTHING places an order.
     *
     * @return void
     */
    public function testInitiateFromActiveQuotePersistsAttemptWithoutAnyOrder(): void
    {
        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->with('VND', 100.0)->willReturn(100000.0);
        $this->repository->method('getActiveByQuoteId')->willReturn(null);
        $this->repository->method('getListByQuoteId')->willReturn([]);
        $this->stubSave();
        $this->stubGetPayUrlCommand('https://pay.zalopay.vn/order/abc');
        // Case 2: reserved order id persisted on the quote (set BEFORE the call —
        // PHPUnit expectations count only invocations after configuration).
        $this->cartRepository->expects($this->atLeastOnce())->method('save')->with($quote);

        $attempt = $this->management->initiate($quote);

        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
        $this->assertSame('https://pay.zalopay.vn/order/abc', $attempt->getPayUrl());
        $this->assertSame('260826_1000_000000123', $attempt->getAppTransId());
        // Case 7: exact VND snapshot locked at creation.
        $this->assertSame(100000, $attempt->getAmount());
        $this->assertSame(PaymentAttemptInterface::CURRENCY_VND, $attempt->getCurrency());
        $this->assertSame('000000123', $attempt->getReservedOrderId());
        // BLOCKER 1: the payment contract fingerprint is locked at creation.
        $this->assertSame('CONTRACT_HASH', $attempt->getContractHash());
        $this->assertSame(42, $attempt->getQuoteId());
        // Case 3: no order placement dependency exists in the flow at all —
        // the only command touched is the provider transaction creation.
        $this->assertSame(['get_pay_url'], $this->commandPoolCalls);
    }

    /**
     * Case 6: a duplicate Start with a matching ACTIVE attempt reuses it and
     * never talks to the provider again.
     *
     * @return void
     */
    public function testDuplicateStartReusesActiveAttemptWithoutNewTransaction(): void
    {
        $existing = $this->newAttempt();
        $existing->markActive('https://pay.zalopay.vn/order/abc');
        $existing->setAmount(100000);

        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getActiveByQuoteId')->willReturn($existing);

        $attempt = $this->management->initiate($quote);

        $this->assertSame($existing, $attempt);
        $this->commandPool->expects($this->never())->method('get');
    }

    /**
     * BLOCKER 1 tightening: same amount but a different contract (qty/items/
     * address edited) -> the ACTIVE attempt is stale-marked and a NEW attempt
     * with a fresh fingerprint is minted; the old pay URL is never reused.
     *
     * @return void
     */
    public function testSameAmountWithChangedContractIsNotReused(): void
    {
        $existing = $this->newAttempt();
        $existing->markActive('https://pay.zalopay.vn/order/abc');
        $existing->setAmount(100000);

        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getActiveByQuoteId')->willReturn($existing);
        $this->repository->method('getListByQuoteId')->willReturn([$existing]);
        $this->stubSave();
        $this->stubGetPayUrlCommand('https://pay.zalopay.vn/order/new');
        // Fresh mock with matches()=false: a second registration on the setUp
        // mock would not win — PHPUnit uses the first matching stub.
        $this->fingerprint = $this->createMock(QuoteContractFingerprint::class);
        $this->fingerprint->method('calculate')->willReturn('CONTRACT_HASH_V2');
        $this->fingerprint->method('matches')->willReturn(false); // contract changed
        $this->rebuildManagement();

        $attempt = $this->management->initiate($quote);

        $this->assertNotSame($existing, $attempt);
        $this->assertSame(PaymentAttemptInterface::STATUS_STALE, $existing->getPaymentStatus());
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
        $this->assertSame('CONTRACT_HASH_V2', $attempt->getContractHash());
    }

    /**
     * Case 8: a changed quote total stale-marks the old attempt and mints a
     * new one with the new snapshot — the old one is never re-validated.
     *
     * @return void
     */
    public function testChangedQuoteTotalStalesOldAttemptAndCreatesNew(): void
    {
        $existing = $this->newAttempt();
        $existing->markActive('https://pay.zalopay.vn/order/abc');
        $existing->setAmount(90000);

        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getActiveByQuoteId')->willReturn($existing);
        $this->repository->method('getListByQuoteId')->willReturn([$existing]);
        $this->stubSave();
        $this->stubGetPayUrlCommand('https://pay.zalopay.vn/order/new');

        $attempt = $this->management->initiate($quote);

        $this->assertNotSame($existing, $attempt);
        $this->assertSame(PaymentAttemptInterface::STATUS_STALE, $existing->getPaymentStatus());
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
        $this->assertSame(100000, $attempt->getAmount());
        $this->assertSame(1, $attempt->getRetryCount());
    }

    // ---- Round 4, Blocker 2: double-payment guard (matrix #11-#22) ----

    /**
     * Round-4 #11/#12/#13: a PAID attempt on the quote BLOCKS Start — no new
     * PaymentAttempt, no new provider transaction, customer-safe
     * "payment received / being finalized" message. This holds even while
     * finalization is failing transiently: convergence is owned by
     * IPN/Return/Recovery, never by a second payment.
     *
     * @return void
     */
    public function testPaidAttemptBlocksSecondPaymentAndProviderTransaction(): void
    {
        $paid = $this->newAttempt()->markActive('https://pay')->markPaid('240801000001');
        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getBlockingAttemptByQuoteId')->willReturn($paid);
        $this->repository->expects($this->never())->method('save');
        $this->commandPool->expects($this->never())->method('get');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('already been received and is being finalized');
        $this->management->initiate($quote);
    }

    /**
     * Round-4 #14: a FINALIZED attempt (order bound) BLOCKS a Start even
     * when the quote is still active (anomaly) — never a second payment.
     *
     * @return void
     */
    public function testFinalizedAttemptBlocksSecondPayment(): void
    {
        $finalized = $this->newAttempt()->markActive('https://pay')
            ->markPaid('240801000001')->markFinalized(77);
        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getBlockingAttemptByQuoteId')->willReturn($finalized);
        $this->repository->expects($this->never())->method('save');
        $this->commandPool->expects($this->never())->method('get');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('the order has been created');
        $this->management->initiate($quote);
    }

    /**
     * Round-4 #15/#17: a quarantined attempt (requires_reconciliation,
     * e.g. provider_transaction_conflict) BLOCKS a new payment until an
     * explicit reconciliation resolves it — customer-safe "under review"
     * message, no new provider transaction.
     *
     * @return void
     */
    public function testQuarantinedAttemptBlocksSecondPayment(): void
    {
        $quarantined = $this->newAttempt()->markActive('https://pay')->markPaid('240801000001');
        $quarantined->setRequiresReconciliation(true);
        $quarantined->setReconciliationCode(PaymentAttemptInterface::RECON_PROVIDER_TX_CONFLICT);
        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getBlockingAttemptByQuoteId')->willReturn($quarantined);
        $this->repository->expects($this->never())->method('save');
        $this->commandPool->expects($this->never())->method('get');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('under review');
        $this->management->initiate($quote);
    }

    /**
     * Round-4 #16: a late-paid TERMINAL attempt (FAILED status + structured
     * late_paid_terminal_state quarantine — money-real evidence) BLOCKS a
     * new payment. Blocking is structured-flags-only: no last_error parsing.
     *
     * @return void
     */
    public function testLatePaidTerminalAttemptBlocksSecondPayment(): void
    {
        $latePaid = $this->newAttempt()->markActive('https://pay')->markFailed('query said no.');
        $latePaid->setRequiresReconciliation(true);
        $latePaid->setReconciliationCode(PaymentAttemptInterface::RECON_LATE_PAID_TERMINAL_STATE);
        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getBlockingAttemptByQuoteId')->willReturn($latePaid);
        $this->repository->expects($this->never())->method('save');
        $this->commandPool->expects($this->never())->method('get');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('under review');
        $this->management->initiate($quote);
    }

    /**
     * Round-4 #18: an ORDINARY unpaid failure (FAILED, no money-real
     * evidence anywhere) does NOT block — a fresh provider transaction is
     * minted (retry UX preserved) and the FAILED row is never re-mutated.
     *
     * @return void
     */
    public function testOrdinaryUnpaidFailureAllowsFreshPayment(): void
    {
        $failed = $this->newAttempt()->markActive('https://pay')->markFailed('query said no.');
        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getBlockingAttemptByQuoteId')->willReturn(null);
        // A real repository never returns a terminal FAILED row as "active".
        $this->repository->method('getActiveByQuoteId')->willReturn(null);
        $this->repository->method('getListByQuoteId')->willReturn([$failed]);
        $this->stubSave();
        $this->stubGetPayUrlCommand('https://pay.zalopay.vn/order/new');

        $attempt = $this->management->initiate($quote);

        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
        $this->assertNotSame($failed, $attempt);
        // The FAILED row was untouched; the NEW attempt is persisted twice
        // (INITIATED inside the locked creation, ACTIVE after the provider
        // call) — never a third time, never the FAILED row.
        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $failed->getPaymentStatus());
        $this->assertCount(2, $this->savedAttempts);
        $this->assertSame($attempt, $this->savedAttempts[0]);
        $this->assertSame($attempt, $this->savedAttempts[1]);
        $this->assertSame(1, $attempt->getRetryCount());
    }

    /**
     * Round-4 #19: an ordinary EXPIRED/unpaid attempt (no money-real
     * evidence) is stale-marked and a fresh payment is allowed.
     *
     * @return void
     */
    public function testExpiredUnpaidAttemptMayStartFreshPayment(): void
    {
        $expired = $this->newAttempt();
        $expired->markActive('https://pay');
        $expired->setExpiresAt('2020-01-01 00:00:00'); // long past TTL
        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getBlockingAttemptByQuoteId')->willReturn(null);
        $this->repository->method('getActiveByQuoteId')->willReturn($expired);
        $this->repository->method('getListByQuoteId')->willReturn([$expired]);
        $this->stubSave();
        $this->stubGetPayUrlCommand('https://pay.zalopay.vn/order/new');

        $attempt = $this->management->initiate($quote);

        $this->assertNotSame($expired, $attempt);
        $this->assertSame(PaymentAttemptInterface::STATUS_STALE, $expired->getPaymentStatus());
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
    }

    /**
     * Corrective round 5: an ORDINARY STALE attempt (no money-real
     * evidence, not quarantined) neither blocks Start nor counts as an
     * active row — a fresh provider transaction is minted.
     *
     * @return void
     */
    public function testStaleUnpaidAttemptMayStartFreshPayment(): void
    {
        $stale = $this->newAttempt()->markActive('https://pay');
        $stale->setPaymentStatus(PaymentAttemptInterface::STATUS_STALE);
        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        // A real repository never returns an ordinary STALE row from the
        // blocking lookup (PAID/FINALIZED/requires_reconciliation only).
        $this->repository->method('getBlockingAttemptByQuoteId')->willReturn(null);
        $this->repository->method('getActiveByQuoteId')->willReturn(null);
        $this->repository->method('getListByQuoteId')->willReturn([$stale]);
        $this->stubSave();
        $this->stubGetPayUrlCommand('https://pay.zalopay.vn/order/new');

        $attempt = $this->management->initiate($quote);

        $this->assertNotSame($stale, $attempt);
        $this->assertSame(PaymentAttemptInterface::STATUS_STALE, $stale->getPaymentStatus());
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
    }

    /**
     * Round-4 #22 (concurrency): an UNEXPIRED INITIATED attempt is another
     * Start's in-flight provider transaction — the concurrent Start refuses
     * with a retry-safe message instead of stale-marking it and minting a
     * SECOND provider transaction.
     *
     * @return void
     */
    public function testConcurrentStartDuringInitializationRefusesInsteadOfMintingSecond(): void
    {
        $inFlight = $this->newAttempt(); // INITIATED, expires far in the future
        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getBlockingAttemptByQuoteId')->willReturn(null);
        $this->repository->method('getActiveByQuoteId')->willReturn($inFlight);
        $this->repository->expects($this->never())->method('save');
        $this->commandPool->expects($this->never())->method('get');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('being initialized');
        try {
            $this->management->initiate($quote);
        } finally {
            // Never stale-marked into a second provider transaction.
            $this->assertSame(PaymentAttemptInterface::STATUS_INITIATED, $inFlight->getPaymentStatus());
        }
    }

    /**
     * Provider rejection marks the attempt FAILED with the error before
     * rethrowing (explicit transition, auditable row).
     *
     * @return void
     */
    public function testProviderRejectionMarksAttemptFailed(): void
    {
        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getActiveByQuoteId')->willReturn(null);
        $this->repository->method('getListByQuoteId')->willReturn([]);
        $this->stubSave();

        $command = $this->createMock(\Magento\Payment\Gateway\CommandInterface::class);
        $command->method('execute')->willThrowException(new LocalizedException(__('App id invalid')));
        $this->commandPool->method('get')->with('get_pay_url')->willReturn($command);

        $this->expectException(LocalizedException::class);
        try {
            $this->management->initiate($quote);
        } finally {
            $failed = end($this->savedAttempts);
            $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $failed->getPaymentStatus());
            $this->assertSame('App id invalid', $failed->getLastError());
        }
    }

    /**
     * Guards: an inactive, empty or non-ZaloPay quote never enters the flow.
     *
     * @return void
     */
    public function testIsInitiableGuards(): void
    {
        $inactive = $this->newPayableQuote(100.0, 'VND', 'zalopay', false);
        $this->assertFalse($this->management->isInitiable($inactive));

        $empty = $this->newPayableQuote(100.0, 'VND', 'zalopay', true, 0);
        $this->assertFalse($this->management->isInitiable($empty));

        $otherMethod = $this->newPayableQuote(100.0, 'VND', 'checkmo');
        $this->assertFalse($this->management->isInitiable($otherMethod));

        $good = $this->newPayableQuote(100.0, 'VND');
        $this->assertTrue($this->management->isInitiable($good));
    }

    /**
     * The TTL is read from config (attempt_ttl minutes).
     *
     * @return void
     */
    public function testAttemptTtlComesFromConfig(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->config->method('getValue')->willReturnCallback(
            fn ($field) => $field === 'attempt_ttl' ? 5 : null
        );
        $this->rebuildManagement();

        $quote = $this->newPayableQuote(100.0, 'VND');
        $this->rate->method('getVndAmountByCurrency')->willReturn(100000.0);
        $this->repository->method('getActiveByQuoteId')->willReturn(null);
        $this->repository->method('getListByQuoteId')->willReturn([]);
        $this->stubSave();
        $this->stubGetPayUrlCommand('https://pay.zalopay.vn/order/abc');

        $attempt = $this->management->initiate($quote);
        $expectedWindow = [time() + 290, time() + 311];
        $this->assertGreaterThanOrEqual($expectedWindow[0], strtotime((string)$attempt->getExpiresAt()));
        $this->assertLessThanOrEqual($expectedWindow[1], strtotime((string)$attempt->getExpiresAt()));
    }

    /**
     * @return void
     */
    private function rebuildManagement(): void
    {
        $method = $this->createMock(MethodInterface::class);
        $method->method('getCode')->willReturn('zalopay');
        $this->management = new PaymentAttemptManagement(
            $this->commandPool,
            $this->repository,
            $this->attemptFactory,
            $this->cartRepository,
            $this->createMock(PaymentDataObjectFactory::class),
            $this->rate,
            $this->appTransIdBuilder,
            $this->fingerprint,
            $method,
            $this->config,
            $this->resourceConnection,
            $this->createMock(\Psr\Log\LoggerInterface::class)
        );
    }

    /**
     * @param string $payUrl
     * @return void
     */
    private function stubGetPayUrlCommand(string $payUrl): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('get')->willReturn(['order_url' => $payUrl]);
        $command = $this->createMock(\Magento\Payment\Gateway\CommandInterface::class);
        $command->expects($this->once())->method('execute')->willReturn($result);
        $this->commandPool->method('get')->willReturnCallback(
            function (string $name) use ($command) {
                $this->commandPoolCalls[] = $name;

                return $command;
            }
        );
    }

    /**
     * @return void
     */
    private function stubSave(): void
    {
        $this->savedAttempts = [];
        $this->repository->method('save')->willReturnCallback(
            function (PaymentAttemptInterface $attempt) {
                $this->savedAttempts[] = $attempt;

                return $attempt;
            }
        );
    }

    /**
     * @param float $grandTotal
     * @param string $currency
     * @param string $method
     * @param bool $isActive
     * @param int $itemsCount
     * @return Quote|MockObject
     */
    private function newPayableQuote(
        float $grandTotal,
        string $currency,
        string $method = 'zalopay',
        bool $isActive = true,
        int $itemsCount = 1
    ) {
        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getMethod')->willReturn($method);

        $quote = $this->createMock(QuoteStub::class);
        $quote->method('getId')->willReturn(42);
        $quote->method('getIsActive')->willReturn($isActive);
        $quote->method('getItemsCount')->willReturn($itemsCount);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getGrandTotal')->willReturn($grandTotal);
        $quote->method('getQuoteCurrencyCode')->willReturn($currency);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('collectTotals')->willReturnSelf();
        $reservedCalls = 0;
        $quote->method('getReservedOrderId')->willReturnCallback(
            // First call = the "is it empty?" check; every later call (attempt
        // field, app_trans_id build, fingerprint) sees the reserved id.
            function () use (&$reservedCalls) {
                return ++$reservedCalls === 1 ? '' : '000000123';
            }
        );
        $quote->method('reserveOrderId')->willReturnSelf();

        return $quote;
    }

    /**
     * @return PaymentAttempt
     */
    private function newAttempt(): PaymentAttempt
    {
        $attempt = $this->newAttemptModel();
        $attempt->setQuoteId(42);
        $attempt->setReservedOrderId('000000123');
        $attempt->setAmount(100000);
        $attempt->setCurrency(PaymentAttemptInterface::CURRENCY_VND);
        $attempt->setContractHash('CONTRACT_HASH');
        $attempt->setStoreId(1);
        $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_INITIATED);
        $attempt->setExpiresAt('2099-01-01 00:00:00');

        return $attempt;
    }

    /**
     * PaymentAttempt extends AbstractModel — constructor needs Context/Registry.
     *
     * @return PaymentAttempt
     */
    private function newAttemptModel(): PaymentAttempt
    {
        return new PaymentAttempt(
            $this->createMock(\Magento\Framework\Model\Context::class),
            $this->createMock(\Magento\Framework\Registry::class),
            $this->newResourceStub()
        );
    }

    /**
     * An injected resource keeps _init() away from the (unit-test absent)
     * ObjectManager while providing the entity id field name.
     *
     * @return \Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource|\PHPUnit\Framework\MockObject\MockObject
     */
    private function newResourceStub()
    {
        $resource = $this->getMockBuilder(\Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resource->method('getIdFieldName')->willReturn(PaymentAttemptInterface::ENTITY_ID);

        return $resource;
    }
}
