<?php
/**
 * Unit test for the canonical OrderFinalizer (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Framework\Api\SearchCriteriaBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Exception\ContractMismatchException;
use Secomm\MoMo\Model\PaymentAttempt;
use Secomm\MoMo\Model\QuoteContractFingerprint;
use Secomm\MoMo\Service\OrderFinalizer;
use Secomm\MoMo\Service\OrderPlacementAuthorization;
use Secomm\MoMo\Service\PaymentAttemptLifecycle;

/**
 * Verifies the single finalization boundary: the quarantined refusal, the
 * cannot-finalize refusal, duplicate recovery of the bound order (AC5),
 * the full placement path with capture + email claim (AC3) and the quote
 * contract gate (AC6).
 */
class OrderFinalizerTest extends TestCase
{
    private PaymentAttemptRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;

    private CartManagementInterface&\PHPUnit\Framework\MockObject\MockObject $cartManagement;

    private CartRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $cartRepository;

    private OrderRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $orderRepository;

    private ConfigInterface&\PHPUnit\Framework\MockObject\MockObject $config;

    private MethodInterface&\PHPUnit\Framework\MockObject\MockObject $method;

    private QuoteContractFingerprint&\PHPUnit\Framework\MockObject\MockObject $fingerprint;

    private AdapterInterface&\PHPUnit\Framework\MockObject\MockObject $connection;

    private OrderPlacementAuthorization $authorization;

    private PaymentAttemptLifecycle&\PHPUnit\Framework\MockObject\MockObject $lifecycle;

    private OrderSender&\PHPUnit\Framework\MockObject\MockObject $orderSender;

    private OrderFinalizer $finalizer;

    /**
     * Shared order-payment mock (order() and the capture expectations must
     * observe the SAME instance).
     *
     * @var OrderPayment&\PHPUnit\Framework\MockObject\MockObject|null
     */
    private ?OrderPayment $paymentMock = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->cartManagement = $this->createMock(CartManagementInterface::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->method = $this->createMock(MethodInterface::class);
        $this->method->method('getCode')->willReturn('momo_payment');
        $this->fingerprint = $this->createMock(QuoteContractFingerprint::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->authorization = new OrderPlacementAuthorization();
        $this->lifecycle = $this->createMock(PaymentAttemptLifecycle::class);
        $this->orderSender = $this->createMock(OrderSender::class);
        $logger = $this->createMock(LoggerInterface::class);
        $dateTime = $this->createMock(\Magento\Framework\Stdlib\DateTime\DateTime::class);
        $dateTime->method('timestamp')->willReturn(1787000000);

        $this->finalizer = new OrderFinalizer(
            $this->repository,
            $this->cartManagement,
            $this->cartRepository,
            $this->orderRepository,
            $this->createMock(SearchCriteriaBuilder::class),
            $this->config,
            $this->method,
            $this->fingerprint,
            $resourceConnection,
            $this->authorization,
            $this->lifecycle,
            $logger,
            $this->orderSender,
            $dateTime
        );
    }

    /**
     * A quarantined attempt is structurally refused: no order, rollback,
     * evidence persisted through the lifecycle.
     *
     * @return void
     */
    public function testQuarantinedAttemptCannotAutoFinalize(): void
    {
        $attempt = $this->attempt('paid', ['requires_reconciliation' => 1]);
        $this->repository->method('lockByOrderRef')->willReturn($attempt);
        $this->connection->expects($this->once())->method('rollBack');
        $this->cartManagement->expects($this->never())->method('placeOrder');
        $this->lifecycle->expects($this->once())->method('recordContractMismatch')
            ->with('MOMOREF', $this->stringContains('requires reconciliation'));

        $this->expectException(ContractMismatchException::class);

        $this->finalizer->finalizeOrRecover($attempt);
    }

    /**
     * A FAILED attempt can never be finalized: money must be verified PAID
     * first (the state machine refuses).
     *
     * @return void
     */
    public function testCannotFinalizeFromFailedState(): void
    {
        $attempt = $this->attempt('failed');
        $this->repository->method('lockByOrderRef')->willReturn($attempt);
        $this->connection->expects($this->once())->method('rollBack');
        $this->cartManagement->expects($this->never())->method('placeOrder');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cannot be finalized from state');

        $this->finalizer->finalizeOrRecover($attempt);
    }

    /**
     * A duplicate Return/IPN on a FINALIZED attempt recovers the BOUND
     * order — never a second placement (AC5).
     *
     * @return void
     */
    public function testFinalizedAttemptRecoversBoundOrder(): void
    {
        $attempt = $this->attempt('finalized', ['order_id' => 5001]);
        $this->repository->method('lockByOrderRef')->willReturn($attempt);
        $order = $this->order();
        $this->orderRepository->method('get')->with(5001)->willReturn($order);
        $this->cartManagement->expects($this->never())->method('placeOrder');
        $this->repository->method('claimEmailDispatch')->willReturn(true);
        $this->orderSender->expects($this->once())->method('send')->with($order);
        $this->connection->expects($this->once())->method('commit');

        $recovered = $this->finalizer->finalizeOrRecover($attempt);

        $this->assertSame($order, $recovered);
    }

    /**
     * The full placement path: contract verified, THE grant opened around
     * placeOrder, attempt finalized + captured, email claimed and sent.
     *
     * @return void
     */
    public function testPlacesOrderFromVerifiedQuote(): void
    {
        $attempt = $this->attempt('paid');
        $this->repository->method('lockByOrderRef')->willReturn($attempt);
        $this->repository->method('save')->willReturnArgument(0);
        $this->cartRepository->method('get')->with(42)->willReturn($this->quote());
        $this->fingerprint->method('calculate')->willReturn('hash');
        $this->fingerprint->method('matches')->with('hash', 'hash')->willReturn(true);
        $order = $this->order();
        $this->cartManagement->expects($this->once())->method('placeOrder')->with(42)->willReturn(5001);
        $this->orderRepository->method('get')->with(5001)->willReturn($order);
        $this->config->method('getValue')->with('payment_action')->willReturn('authorize_capture');
        $payment = $this->payment();
        $payment->expects($this->once())->method('capture');
        $payment->expects($this->once())->method('setTransactionId')->with('987654321');
        $this->repository->method('claimEmailDispatch')->willReturn(true);
        $this->orderSender->expects($this->once())->method('send')->with($order);
        $this->connection->expects($this->once())->method('commit');

        $placed = $this->finalizer->finalizeOrRecover($attempt, '987654321');

        $this->assertSame($order, $placed);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $attempt->getPaymentStatus());
        $this->assertSame(5001, $attempt->getOrderId());
        $this->assertSame('987654321', $attempt->getProviderTransactionId());
        $this->assertFalse($this->authorization->isOpen());
    }

    /**
     * The admin-configured Payment Action gates the local capture
     * (MOMO-05): any value other than `authorize_capture` finalizes the
     * order WITHOUT capturing — the verified money still finalizes, the
     * capture step is the only thing skipped.
     *
     * @return void
     */
    public function testNonCapturingPaymentActionFinalizesWithoutCapture(): void
    {
        $attempt = $this->attempt('paid');
        $this->repository->method('lockByOrderRef')->willReturn($attempt);
        $this->repository->method('save')->willReturnArgument(0);
        $this->cartRepository->method('get')->with(42)->willReturn($this->quote());
        $this->fingerprint->method('calculate')->willReturn('hash');
        $this->fingerprint->method('matches')->with('hash', 'hash')->willReturn(true);
        $order = $this->order();
        $this->cartManagement->expects($this->once())->method('placeOrder')->with(42)->willReturn(5001);
        $this->orderRepository->method('get')->with(5001)->willReturn($order);
        $this->config->method('getValue')->with('payment_action')->willReturn('not_authorize_capture');
        $payment = $this->payment();
        $payment->expects($this->never())->method('capture');
        $this->repository->method('claimEmailDispatch')->willReturn(true);
        $this->orderSender->expects($this->once())->method('send')->with($order);
        $this->connection->expects($this->once())->method('commit');

        $placed = $this->finalizer->finalizeOrRecover($attempt, '987654321');

        $this->assertSame($order, $placed);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $attempt->getPaymentStatus());
        $this->assertSame(5001, $attempt->getOrderId());
    }

    /**
     * A missing `payment_action` config value never captures (strict
     * comparison — only the explicit `authorize_capture` captures), while
     * finalization itself is unaffected (MOMO-05).
     *
     * @return void
     */
    public function testMissingPaymentActionDoesNotCapture(): void
    {
        $attempt = $this->attempt('paid');
        $this->repository->method('lockByOrderRef')->willReturn($attempt);
        $this->repository->method('save')->willReturnArgument(0);
        $this->cartRepository->method('get')->with(42)->willReturn($this->quote());
        $this->fingerprint->method('calculate')->willReturn('hash');
        $this->fingerprint->method('matches')->with('hash', 'hash')->willReturn(true);
        $order = $this->order();
        $this->cartManagement->expects($this->once())->method('placeOrder')->with(42)->willReturn(5001);
        $this->orderRepository->method('get')->with(5001)->willReturn($order);
        $this->config->method('getValue')->with('payment_action')->willReturn(null);
        $payment = $this->payment();
        $payment->expects($this->never())->method('capture');
        $this->repository->method('claimEmailDispatch')->willReturn(true);
        $this->orderSender->expects($this->once())->method('send')->with($order);
        $this->connection->expects($this->once())->method('commit');

        $placed = $this->finalizer->finalizeOrRecover($attempt, '987654321');

        $this->assertSame($order, $placed);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $attempt->getPaymentStatus());
    }

    /**
     * A quote whose contract no longer matches (same total, different
     * content) refuses finalization: ContractMismatchException, the
     * money-real state kept, evidence persisted (AC6).
     *
     * @return void
     */
    public function testContractFingerprintMismatchRefusesFinalization(): void
    {
        $attempt = $this->attempt('paid');
        $this->repository->method('lockByOrderRef')->willReturn($attempt);
        $this->cartRepository->method('get')->with(42)->willReturn($this->quote());
        $this->fingerprint->method('calculate')->willReturn('current-hash');
        $this->fingerprint->method('matches')->with('hash', 'current-hash')->willReturn(false);
        $this->cartManagement->expects($this->never())->method('placeOrder');
        $this->lifecycle->expects($this->once())->method('recordContractMismatch')
            ->with('MOMOREF', $this->stringContains('fingerprint mismatch'));

        $this->expectException(ContractMismatchException::class);

        $this->finalizer->finalizeOrRecover($attempt, '987654321');
    }

    /**
     * The first authoritative provider id owns the row: a later finalizer
     * carrying a different transId never overwrites the recorded identity.
     *
     * @return void
     */
    public function testProviderTransactionIdIsNeverOverwritten(): void
    {
        $attempt = $this->attempt('paid', ['provider_transaction_id' => '111']);
        $this->repository->method('lockByOrderRef')->willReturn($attempt);
        $this->repository->method('save')->willReturnArgument(0);
        $this->cartRepository->method('get')->with(42)->willReturn($this->quote());
        $this->fingerprint->method('calculate')->willReturn('hash');
        $this->fingerprint->method('matches')->willReturn(true);
        $order = $this->order();
        $this->cartManagement->method('placeOrder')->willReturn(5001);
        $this->orderRepository->method('get')->willReturn($order);
        $this->config->method('getValue')->willReturn('authorize_capture');
        $this->repository->method('claimEmailDispatch')->willReturn(false);
        $this->orderSender->expects($this->never())->method('send');

        $this->finalizer->finalizeOrRecover($attempt, '222');

        $this->assertSame('111', $attempt->getProviderTransactionId());
    }

    /**
     * A real attempt in the given status.
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
        $attempt->setReservedOrderId('200000001');
        $attempt->setAmount(150000);
        $attempt->setContractHash('hash');
        $attempt->setPaymentStatus($status);
        foreach ($extra as $field => $value) {
            $attempt->setData($field, $value);
        }

        return $attempt;
    }

    /**
     * Order mock bound to the attempt's identity (increment id, quote, MoMo
     * payment) with a pending_payment state.
     *
     * @return Order&\PHPUnit\Framework\MockObject\MockObject
     */
    private function order(): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getEntityId')->willReturn(5001);
        $order->method('getIncrementId')->willReturn('200000001');
        $order->method('getQuoteId')->willReturn(42);
        $order->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $order->method('getEmailSent')->willReturn(0);
        $order->method('getPayment')->willReturn($this->payment());
        $order->method('addCommentToStatusHistory')->willReturnSelf();

        return $order;
    }

    /**
     * Order payment mock (OrderPaymentInterface for ContextHelper).
     *
     * @return OrderPayment&\PHPUnit\Framework\MockObject\MockObject
     */
    private function payment(): OrderPayment
    {
        if ($this->paymentMock === null) {
            $this->paymentMock = $this->createMock(OrderPayment::class);
            $this->paymentMock->method('getMethod')->willReturn('momo_payment');
        }

        return $this->paymentMock;
    }

    /**
     * Active quote mock matching the attempt contract.
     *
     * @return Quote&\PHPUnit\Framework\MockObject\MockObject
     */
    private function quote(): Quote
    {
        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getMethod')->willReturn('momo_payment');
        $quote = $this->getMockBuilder(Quote::class)
            ->onlyMethods(['collectTotals', 'getId', 'getIsActive', 'getPayment'])
            ->addMethods(['getGrandTotal'])
            ->disableOriginalConstructor()
            ->getMock();
        $quote->method('getId')->willReturn(42);
        $quote->method('getIsActive')->willReturn(true);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('getGrandTotal')->willReturn(150000.0);

        return $quote;
    }
}
