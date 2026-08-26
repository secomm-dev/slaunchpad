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
