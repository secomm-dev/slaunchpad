<?php
/**
 * Unit test for the payment-first initiation (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Model\PaymentAttempt;
use Secomm\MoMo\Model\PaymentAttemptFactory;
use Secomm\MoMo\Model\OrderRefBuilder;
use Secomm\MoMo\Model\QuoteContractFingerprint;
use Secomm\MoMo\Model\PaymentAttemptManagement;
use Magento\Payment\Gateway\ConfigInterface;

/**
 * Verifies initiation: initiable quote contract (active, MoMo, VND),
 * reuse of a matching ACTIVE attempt WITHOUT a second provider
 * transaction, and the failed-provider-call path (markFailed + rethrow).
 */
class PaymentAttemptManagementTest extends TestCase
{
    private CommandPoolInterface&\PHPUnit\Framework\MockObject\MockObject $commandPool;

    private PaymentAttemptRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;

    private AdapterInterface&\PHPUnit\Framework\MockObject\MockObject $connection;

    private QuoteContractFingerprint&\PHPUnit\Framework\MockObject\MockObject $fingerprint;

    private ConfigInterface&\PHPUnit\Framework\MockObject\MockObject $config;

    private PaymentAttemptManagement $management;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->commandPool = $this->createMock(CommandPoolInterface::class);
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $objectManager = $this->createMock(\Magento\Framework\ObjectManagerInterface::class);
        $objectManager->method('create')->willReturnCallback(function ($type) {
            return $type === PaymentAttempt::class
                ? new PaymentAttempt($this->createMock(Context::class), $this->createMock(Registry::class))
                : null;
        });
        $paymentAttemptFactory = new PaymentAttemptFactory($objectManager);
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $orderRefBuilder = $this->createMock(OrderRefBuilder::class);
        $orderRefBuilder->method('buildOrderRef')->willReturn('MOMOREF');
        $orderRefBuilder->method('buildRequestId')->willReturn('MOMOREF-R1111');
        $this->fingerprint = $this->createMock(QuoteContractFingerprint::class);
        $this->fingerprint->method('calculate')->willReturn('hash');
        $method = $this->createMock(MethodInterface::class);
        $method->method('getCode')->willReturn('momo_payment');
        $this->config = $this->createMock(ConfigInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getTableName')->willReturnCallback(
            fn (string $table): string => $table
        );
        $this->connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('forUpdate')->willReturnSelf();
        $this->connection->method('select')->willReturn($select);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $logger = $this->createMock(LoggerInterface::class);

        $this->management = new PaymentAttemptManagement(
            $this->commandPool,
            $this->repository,
            $paymentAttemptFactory,
            $cartRepository,
            $this->createMock(PaymentDataObjectFactory::class),
            $orderRefBuilder,
            $this->fingerprint,
            $method,
            $this->config,
            $resourceConnection,
            $logger
        );
    }

    /**
     * isInitiable: active + non-empty + MoMo + VND only.
     *
     * @return void
     */
    public function testIsInitiableRequiresActiveMoMoVndQuote(): void
    {
        $this->assertFalse($this->management->isInitiable($this->quote(['is_active' => false])));
        $this->assertFalse($this->management->isInitiable($this->quote(['items_count' => 0])));
        $this->assertFalse($this->management->isInitiable($this->quote(['method' => 'checkmo'])));
        $this->assertFalse($this->management->isInitiable($this->quote(['currency' => 'USD'])));
        $this->assertTrue($this->management->isInitiable($this->quote()));
    }

    /**
     * A reusable ACTIVE attempt with an UNCHANGED contract is reused — no
     * second provider transaction for the same cart.
     *
     * @return void
     */
    public function testInitiateReusesMatchingActiveAttempt(): void
    {
        $quote = $this->quote();
        $existing = $this->attempt('active');
        $existing->setPayUrl('https://payment.momo.vn/pay/abc');
        $this->repository->method('getBlockingAttemptByQuoteId')->willReturn(null);
        $this->repository->method('getActiveByQuoteId')->willReturn($existing);
        $this->fingerprint->method('matches')->with('hash', 'hash')->willReturn(true);
        $this->commandPool->expects($this->never())->method('get');
        $this->repository->expects($this->never())->method('save');

        $reused = $this->management->initiate($quote);

        $this->assertSame($existing, $reused);
        $this->assertSame(
            PaymentAttemptInterface::STATUS_ACTIVE,
            $reused->getPaymentStatus()
        );
    }

    /**
     * No reusable attempt: a new INITIATED attempt is created with its
     * frozen amount + contract, the provider is called, and the pay URL
     * activates it.
     *
     * @return void
     */
    public function testInitiateCreatesAttemptAndActivatesWithPayUrl(): void
    {
        $this->repository->method('getBlockingAttemptByQuoteId')->willReturn(null);
        $this->repository->method('getActiveByQuoteId')->willReturn(null);
        $this->repository->method('getListByQuoteId')->willReturn([]);
        $this->repository->method('save')->willReturnArgument(0);
        $this->config->method('getValue')->with('attempt_ttl')->willReturn(15);
        $command = $this->createMock(CommandInterface::class);
        $this->commandPool->method('get')->with('get_pay_url')->willReturn($command);
        $result = $this->createMock(ResultInterface::class);
        $result->method('get')->willReturn(['payUrl' => 'https://payment.momo.vn/pay/abc']);
        $command->method('execute')->willReturn($result);
        $this->connection->expects($this->once())->method('commit');

        $attempt = $this->management->initiate($this->quote());

        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
        $this->assertSame('https://payment.momo.vn/pay/abc', $attempt->getPayUrl());
        $this->assertSame('MOMOREF', $attempt->getOrderRef());
        $this->assertSame(150000, $attempt->getAmount());
        $this->assertSame('hash', $attempt->getContractHash());
        $this->assertSame(PaymentAttemptInterface::CURRENCY_VND, $attempt->getCurrency());
    }

    /**
     * A provider rejection fails the attempt (persisted) and rethrows the
     * original error — no half-open provider transaction.
     *
     * @return void
     */
    public function testInitiateMarksFailedAndRethrowsOnProviderError(): void
    {
        $this->repository->method('getBlockingAttemptByQuoteId')->willReturn(null);
        $this->repository->method('getActiveByQuoteId')->willReturn(null);
        $this->repository->method('getListByQuoteId')->willReturn([]);
        $this->config->method('getValue')->willReturn(15);
        $command = $this->createMock(CommandInterface::class);
        $this->commandPool->method('get')->willReturn($command);
        $command->method('execute')->willThrowException(new \RuntimeException('MoMo rejected the transaction'));

        $failedAttempt = null;
        $this->repository->method('save')->willReturnCallback(
            function ($attempt) use (&$failedAttempt) {
                $failedAttempt = $attempt;

                return $attempt;
            }
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MoMo rejected the transaction');

        try {
            $this->management->initiate($this->quote());
        } finally {
            $this->assertNotNull($failedAttempt);
            $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $failedAttempt->getPaymentStatus());
            $this->assertStringContainsString(
                'MoMo rejected the transaction',
                (string)$failedAttempt->getLastError()
            );
        }
    }

    /**
     * Quote mock with the payment-first surface; defaults are an initiable
     * MoMo/VND cart.
     *
     * @param array $overrides
     * @return Quote&\PHPUnit\Framework\MockObject\MockObject
     */
    private function quote(array $overrides = []): Quote
    {
        $defaults = [
            'id' => 42,
            'is_active' => true,
            'items_count' => 1,
            'method' => 'momo_payment',
            'currency' => 'VND',
            'reserved_order_id' => '200000001',
            'grand_total' => 150000.0,
        ];
        $options = array_merge($defaults, $overrides);

        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getMethod')->willReturn($options['method']);
        $quote = $this->getMockBuilder(Quote::class)
            ->onlyMethods([
                'collectTotals',
                'getId',
                'getIsActive',
                'getItemsCount',
                'getPayment',
                'getReservedOrderId',
                'getStoreId',
                'reserveOrderId',
            ])
            ->addMethods(['getQuoteCurrencyCode', 'getGrandTotal'])
            ->disableOriginalConstructor()
            ->getMock();
        $quote->method('getId')->willReturn($options['id']);
        $quote->method('getIsActive')->willReturn($options['is_active']);
        $quote->method('getItemsCount')->willReturn($options['items_count']);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getQuoteCurrencyCode')->willReturn($options['currency']);
        $quote->method('getReservedOrderId')->willReturn($options['reserved_order_id']);
        $quote->method('getGrandTotal')->willReturn($options['grand_total']);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('reserveOrderId')->willReturnSelf();

        return $quote;
    }

    /**
     * A real attempt for the given status (amount/hash matching the quote).
     *
     * @param string $status
     * @param array $extra
     * @return PaymentAttempt
     */
    private function attempt(string $status, array $extra = []): PaymentAttempt
    {
        $attempt = new PaymentAttempt($this->createMock(Context::class), $this->createMock(Registry::class));
        $attempt->setEntityId(7);
        $attempt->setQuoteId(42);
        $attempt->setOrderRef('MOMOREF');
        $attempt->setRequestId('MOMOREF-R1111');
        $attempt->setAmount(150000);
        $attempt->setContractHash('hash');
        $attempt->setPaymentStatus($status);
        foreach ($extra as $field => $value) {
            $attempt->setData($field, $value);
        }

        return $attempt;
    }
}
