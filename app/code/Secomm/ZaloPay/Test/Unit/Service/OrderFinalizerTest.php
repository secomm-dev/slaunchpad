<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Exception\ContractMismatchException;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Model\QuoteContractFingerprint;
use Secomm\ZaloPay\Service\OrderFinalizer;
use Secomm\ZaloPay\Test\Unit\Model\QuoteStub;

/**
 * ZALOPAY-PAYMENT-FIRST review fixes:
 *
 * BLOCKER 1 — the CURRENT quote must still match the paid payment contract
 * (method, amount, fingerprint) before any automatic placeOrder; mismatch
 * keeps the attempt PAID (money is real), records last_error and creates NO
 * order. BLOCKER 2 — a duplicate FINALIZED return recovers the bound order
 * (binding validated) and rebuilds the success session here, never in the
 * controller. Plus the idempotent place + bind + capture contract and the
 * capture-failure rollback behaviour.
 */
class OrderFinalizerTest extends TestCase
{
    private const PERSISTED_HASH = '5f4dcc3b5aa765d61d8327deb882cf99';

    /**
     * @var PaymentAttemptRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var CartManagementInterface|MockObject
     */
    private $cartManagement;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepository;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private $searchCriteriaBuilder;

    /**
     * @var ConfigInterface|MockObject
     */
    private $config;

    /**
     * @var MethodInterface|MockObject
     */
    private $method;

    /**
     * @var Rate|MockObject
     */
    private $rate;

    /**
     * @var QuoteContractFingerprint|MockObject
     */
    private $fingerprint;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var SessionStub
     */
    private SessionStub $session;

    /**
     * @var OrderFinalizer
     */
    private $finalizer;

    /**
     * @var PaymentAttempt|null
     */
    private ?PaymentAttempt $saved = null;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->cartManagement = $this->createMock(CartManagementInterface::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn(
            $this->createMock(\Magento\Framework\Api\SearchCriteriaInterface::class)
        );
        $this->config = $this->createMock(ConfigInterface::class);
        $this->config->method('getValue')->willReturn(MethodInterface::ACTION_AUTHORIZE_CAPTURE);
        $this->method = $this->createMock(MethodInterface::class);
        $this->method->method('getCode')->willReturn('zalopay');
        $this->rate = $this->createMock(Rate::class);
        $this->fingerprint = $this->createMock(QuoteContractFingerprint::class);

        $this->connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->session = new SessionStub();

        $this->finalizer = new OrderFinalizer(
            $this->repository,
            $this->cartManagement,
            $this->cartRepository,
            $this->orderRepository,
            $this->searchCriteriaBuilder,
            $this->config,
            $this->method,
            $this->rate,
            $this->fingerprint,
            $resourceConnection,
            $this->session,
            $this->createMock(LoggerInterface::class)
        );
    }

    // ---- BLOCKER 1: quote-contract validation before automatic placement ----

    /**
     * Review case 1: unchanged quote -> place, bind, capture (happy path).
     *
     * @return void
     */
    public function testFinalizePlacesBindsAndCapturesTheOrder(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote();

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->expects($this->once())->method('setTransactionId')->with('240801000001');
        $payment->expects($this->once())->method('capture');
        $payment->method('prependMessage');

        $order = $this->newOrder(88, Order::STATE_PENDING_PAYMENT, '000000123', $payment);
        $this->cartManagement->expects($this->once())->method('placeOrder')->with(42)->willReturn(88);
        $this->orderRepository->method('get')->with(88)->willReturn($order);

        $result = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($order, $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $this->saved->getPaymentStatus());
        $this->assertSame(88, $this->saved->getOrderId());
        $this->assertSame('240801000001', $this->saved->getProviderTransactionId());
        $this->assertSame(88, $this->session->calls['last_order_id']);
    }

    /**
     * Review case 2: quote total changed since payment -> NO order, attempt
     * stays PAID with the reason in last_error.
     *
     * @return void
     */
    public function testQuoteTotalChangedRefusesPlacement(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote(currentVndAmount: 700000);

        $this->cartManagement->expects($this->never())->method('placeOrder');

        try {
            $this->finalizer->finalizeOrRecover($attempt, '240801000001');
            $this->fail('ContractMismatchException was not thrown.');
        } catch (ContractMismatchException $e) {
            $this->assertStringContainsString('total changed', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertNull($this->saved->getOrderId());
        $this->assertStringContainsString('total changed', (string)$this->saved->getLastError());
        $this->assertSame([], $this->session->calls);
    }

    /**
     * Review cases 3/4/5: qty/items/shipping changed while the total stayed
     * the same -> fingerprint mismatch -> NO order, stays PAID.
     *
     * @return void
     */
    public function testContractFingerprintMismatchRefusesPlacement(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote(hashMatches: false);

        $this->cartManagement->expects($this->never())->method('placeOrder');

        try {
            $this->finalizer->finalizeOrRecover($attempt, '240801000001');
            $this->fail('ContractMismatchException was not thrown.');
        } catch (ContractMismatchException $e) {
            $this->assertStringContainsString('fingerprint mismatch', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertStringContainsString('fingerprint mismatch', (string)$this->saved->getLastError());
        $this->assertSame([], $this->session->calls);
    }

    /**
     * Review case 6: payment method switched away from ZaloPay -> NO order.
     *
     * @return void
     */
    public function testQuotePaymentMethodChangedRefusesPlacement(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote(quoteMethod: 'checkmo');

        $this->cartManagement->expects($this->never())->method('placeOrder');

        $this->expectException(ContractMismatchException::class);
        $this->expectExceptionMessage('payment method changed');
        $this->finalizer->finalizeOrRecover($attempt, '240801000001');
    }

    /**
     * Review case 7: quote inactive and no matching order -> safe recovery
     * state: stays PAID, last_error recorded, no order created.
     *
     * @return void
     */
    public function testInactiveQuoteWithoutOrderKeepsPaidAttemptForReconciliation(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote(active: false);
        $this->stubOrderSearch([]);

        $this->cartManagement->expects($this->never())->method('placeOrder');

        try {
            $this->finalizer->finalizeOrRecover($attempt, '240801000001');
            $this->fail('ContractMismatchException was not thrown.');
        } catch (ContractMismatchException $e) {
            $this->assertStringContainsString('inactive', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertNull($this->saved->getOrderId());
        $this->assertSame([], $this->session->calls);
    }

    /**
     * Review case 8 (variant A): quote already submitted (inactive) but the
     * order exists under the reserved increment id -> bind that order.
     *
     * @return void
     */
    public function testInactiveQuoteWithMatchingOrderBindsExistingOrder(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote(active: false);

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $existing = $this->newOrder(99, Order::STATE_PROCESSING, '000000123', $payment);
        $this->stubOrderSearch([$existing]);

        $result = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($existing, $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $this->saved->getPaymentStatus());
        $this->assertSame(99, $this->saved->getOrderId());
    }

    /**
     * Case 8 (variant B): the quote validated ACTIVE but was submitted
     * between validation and placeOrder (NoSuchEntity) -> recover the
     * existing order, never a second one.
     *
     * @return void
     */
    public function testConcurrentPlacementIsRecoveredByReservedOrderId(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote();

        $this->cartManagement->method('placeOrder')
            ->willThrowException(new NoSuchEntityException(__('No such entity.')));

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->method('prependMessage');
        $existing = $this->newOrder(99, Order::STATE_PROCESSING, '000000123', $payment);
        $existing->method('addCommentToStatusHistory')->willReturnSelf();
        $this->stubOrderSearch([$existing]);

        $result = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($existing, $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $this->saved->getPaymentStatus());
        $this->assertSame(99, $this->saved->getOrderId());
        // No PENDING_PAYMENT capture path for the already-processing order.
        $payment->expects($this->never())->method('capture');
    }

    // ---- BLOCKER 2: FINALIZED duplicate returns recover session state ----

    /**
     * Review case 1/5: duplicate return on FINALIZED -> existing order, no
     * second placement, binding validated.
     *
     * @return void
     */
    public function testDuplicateFinalizeReturnsExistingOrderWithoutPlacing(): void
    {
        $attempt = $this->newPaidAttempt()->markFinalized(77);
        $this->repository->method('lockByAppTransId')->willReturn($attempt);

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $existing = $this->newOrder(77, Order::STATE_PROCESSING, '000000123', $payment);
        $this->orderRepository->method('get')->with(77)->willReturn($existing);
        $this->cartManagement->expects($this->never())->method('placeOrder');

        $order = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($existing, $order);
        $this->assertSame(77, $attempt->getOrderId());
    }

    /**
     * Review case 2: FINALIZED duplicate with a NEW/LOST checkout session ->
     * the finalizer rebuilds the whole Last* success session.
     *
     * @return void
     */
    public function testDuplicateFinalizeRebuildsEmptySuccessSession(): void
    {
        $attempt = $this->newPaidAttempt()->markFinalized(77);
        $this->repository->method('lockByAppTransId')->willReturn($attempt);

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $existing = $this->newOrder(77, Order::STATE_PROCESSING, '000000123', $payment);
        $this->orderRepository->method('get')->with(77)->willReturn($existing);

        $this->assertSame([], $this->session->calls); // "lost" session state.
        $this->finalizer->finalizeOrRecover($attempt);

        $this->assertSame(42, $this->session->calls['last_quote_id']);
        $this->assertSame(42, $this->session->calls['last_success_quote_id']);
        $this->assertSame(77, $this->session->calls['last_order_id']);
        $this->assertSame('000000123', $this->session->calls['last_real_order_id']);
        $this->assertSame(Order::STATE_PROCESSING, $this->session->calls['last_order_status']);
    }

    /**
     * Review case 3: FINALIZED without a bound order -> reconciliation, no
     * session, customer-safe failure.
     *
     * @return void
     */
    public function testFinalizedWithoutBoundOrderIsRefused(): void
    {
        $attempt = $this->newPaidAttempt();
        $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_FINALIZED); // no order bound
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();

        $this->expectException(ContractMismatchException::class);
        $this->expectExceptionMessage('without a bound order');
        try {
            $this->finalizer->finalizeOrRecover($attempt);
        } finally {
            $this->assertSame([], $this->session->calls);
            $this->assertStringContainsString('without a bound order', (string)$this->saved->getLastError());
        }
    }

    /**
     * Review case 4: bound order does not match the attempt contract
     * (different increment id) -> rejected, no session rebuild.
     *
     * @return void
     */
    public function testFinalizedWithWrongOrderBindingIsRefused(): void
    {
        $attempt = $this->newPaidAttempt()->markFinalized(77);
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $foreign = $this->newOrder(77, Order::STATE_PROCESSING, '000000999', $payment); // not our reserved id
        $this->orderRepository->method('get')->with(77)->willReturn($foreign);

        $this->expectException(ContractMismatchException::class);
        $this->expectExceptionMessage('does not match');
        try {
            $this->finalizer->finalizeOrRecover($attempt);
        } finally {
            $this->assertSame([], $this->session->calls);
        }
    }

    // ---- Lifecycle + transaction behaviour ----

    /**
     * A terminal-but-not-paid attempt cannot be finalized — explicit error,
     * nothing placed.
     *
     * @return void
     */
    public function testNonPayableAttemptRefusesFinalization(): void
    {
        $attempt = $this->newActiveAttempt();
        $attempt->markStale(); // ACTIVE -> STALE (superseded): legal terminal state.
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->cartManagement->expects($this->never())->method('placeOrder');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cannot be finalized');
        $this->finalizer->finalizeOrRecover($attempt);
    }

    /**
     * Capture fails (order save error): the whole unit rolls back — no
     * half-committed FINALIZED state, no success session.
     *
     * @return void
     */
    public function testCaptureFailureRollsBackTransaction(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubMatchingQuote();

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->method('capture')->willThrowException(new LocalizedException(__('Capture failed.')));
        $order = $this->newOrder(88, Order::STATE_PENDING_PAYMENT, '000000123', $payment);
        $this->cartManagement->method('placeOrder')->willReturn(88);
        $this->orderRepository->method('get')->with(88)->willReturn($order);

        $this->connection->expects($this->once())->method('rollBack');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Capture failed');
        try {
            $this->finalizer->finalizeOrRecover($attempt, '240801000001');
        } finally {
            $this->assertSame([], $this->session->calls);
        }
    }

    // ---- helpers ----

    /**
     * @return void
     */
    private function stubSave(): void
    {
        $this->saved = null;
        $this->repository->method('save')->willReturnCallback(
            function (PaymentAttemptInterface $attempt) {
                $this->saved = $attempt;

                return $attempt;
            }
        );
    }

    /**
     * Current quote for the attempt: active, ZaloPay, matching amount and
     * fingerprint unless a flag says otherwise.
     *
     * @param bool $active
     * @param string $quoteMethod
     * @param int $currentVndAmount
     * @param bool $hashMatches
     * @return void
     */
    private function stubMatchingQuote(
        bool $active = true,
        string $quoteMethod = 'zalopay',
        int $currentVndAmount = 100000,
        bool $hashMatches = true
    ): void {
        $payment = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);
        $payment->method('getMethod')->willReturn($quoteMethod);

        $quote = $this->createMock(QuoteStub::class);
        $quote->method('getIsActive')->willReturn($active);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('getQuoteCurrencyCode')->willReturn('VND');
        $quote->method('getGrandTotal')->willReturn(100.0);

        $this->cartRepository->method('get')->with(42)->willReturn($quote);
        $this->rate->method('getVndAmountByCurrency')->willReturn((float)$currentVndAmount);
        $this->fingerprint->method('calculate')->willReturn(self::PERSISTED_HASH);
        $this->fingerprint->method('matches')->willReturn($hashMatches);
    }

    /**
     * @param array $orders
     * @return void
     */
    private function stubOrderSearch(array $orders): void
    {
        $results = $this->createMock(SearchResultsInterface::class);
        $results->method('getItems')->willReturn($orders);
        $this->orderRepository->method('getList')->willReturn($results);
    }

    /**
     * @param int $entityId
     * @param string $state
     * @param string $incrementId
     * @param OrderPayment|MockObject|null $payment
     * @return Order|MockObject
     */
    private function newOrder(int $entityId, string $state, string $incrementId = '000000123', ?OrderPayment $payment = null): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getEntityId')->willReturn($entityId);
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getState')->willReturn($state);
        $order->method('getQuoteId')->willReturn(null);
        if ($payment !== null) {
            $order->method('getPayment')->willReturn($payment);
        }

        return $order;
    }

    /**
     * @return PaymentAttempt
     */
    private function newPaidAttempt(): PaymentAttempt
    {
        $attempt = $this->newActiveAttempt()->markPaid('240801000001');
        $attempt->setContractHash(self::PERSISTED_HASH);

        return $attempt;
    }

    /**
     * @return PaymentAttempt
     */
    private function newActiveAttempt(): PaymentAttempt
    {
        $attempt = $this->newAttemptModel();
        $attempt->setEntityId(9);
        $attempt->setQuoteId(42);
        $attempt->setReservedOrderId('000000123');
        $attempt->setAppTransId('260826_1000_000000123');
        $attempt->setAmount(100000);
        $attempt->setCurrency(PaymentAttemptInterface::CURRENCY_VND);
        $attempt->setExpiresAt('2099-01-01 00:00:00');
        $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_INITIATED);
        $attempt->markActive('https://pay.zalopay.vn/order/abc');

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
