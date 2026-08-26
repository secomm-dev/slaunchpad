<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Service\OrderFinalizer;
use Secomm\ZaloPay\Test\Unit\Service\SessionStub;

/**
 * ZALOPAY-PAYMENT-FIRST Phase 1: one provider transaction never creates two
 * Magento orders. Verified here for the in-process cases: duplicate
 * finalize, idempotent place + bind + capture, and recovery when the quote
 * was already submitted concurrently.
 */
class OrderFinalizerTest extends TestCase
{
    /**
     * @var PaymentAttemptRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var CartManagementInterface|MockObject
     */
    private $cartManagement;

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
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn(
            $this->createMock(\Magento\Framework\Api\SearchCriteriaInterface::class)
        );
        $this->config = $this->createMock(ConfigInterface::class);
        $this->config->method('getValue')->willReturn(MethodInterface::ACTION_AUTHORIZE_CAPTURE);

        $connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);

        $this->finalizer = new OrderFinalizer(
            $this->repository,
            $this->cartManagement,
            $this->orderRepository,
            $this->searchCriteriaBuilder,
            $this->config,
            $resourceConnection,
            new SessionStub(),
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * Duplicate finalize on a FINALIZED attempt: the existing order is
     * returned, placeOrder is NEVER called again.
     *
     * @return void
     */
    public function testDuplicateFinalizeReturnsExistingOrderWithoutPlacing(): void
    {
        $attempt = $this->newPaidAttempt()->markFinalized(77);
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $existing = $this->newOrder(77, 'processing');
        $this->orderRepository->method('get')->with(77)->willReturn($existing);
        $this->cartManagement->expects($this->never())->method('placeOrder');

        $order = $this->finalizer->finalize($attempt, '240801000001');

        $this->assertSame($existing, $order);
        $this->assertSame(77, $attempt->getOrderId());
    }

    /**
     * Happy path: PAID attempt with no order -> place from the quote, bind
     * order_id, transition to FINALIZED, capture the PENDING_PAYMENT order
     * with the provider transaction id.
     *
     * @return void
     */
    public function testFinalizePlacesBindsAndCapturesTheOrder(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();

        $payment = $this->createMock(OrderPayment::class);
        $payment->expects($this->once())->method('setTransactionId')->with('240801000001');
        $payment->expects($this->once())->method('capture');
        $payment->method('prependMessage');

        $order = $this->newOrder(88, Order::STATE_PENDING_PAYMENT);
        $order->method('getPayment')->willReturn($payment);
        $order->method('addCommentToStatusHistory')->willReturnSelf();
        $this->cartManagement->expects($this->once())->method('placeOrder')->with(42)->willReturn(88);
        $this->orderRepository->method('get')->with(88)->willReturn($order);

        $result = $this->finalizer->finalize($attempt, '240801000001');

        $this->assertSame($order, $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $this->saved->getPaymentStatus());
        $this->assertSame(88, $this->saved->getOrderId());
        $this->assertSame('240801000001', $this->saved->getProviderTransactionId());
    }

    /**
     * The quote was already submitted by a concurrent request (CartManagement
     * getActive raises NoSuchEntity): recover the EXISTING order through the
     * reserved increment id instead of creating a second one.
     *
     * @return void
     */
    public function testConcurrentPlacementIsRecoveredByReservedOrderId(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();

        $this->cartManagement->method('placeOrder')
            ->willThrowException(new NoSuchEntityException(__('No such entity.')));

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('prependMessage');
        $existing = $this->newOrder(99, Order::STATE_PROCESSING, '000000123');
        $existing->method('getPayment')->willReturn($payment);
        $existing->method('addCommentToStatusHistory')->willReturnSelf();

        $results = $this->createMock(SearchResultsInterface::class);
        $results->method('getItems')->willReturn([$existing]);
        $this->orderRepository->method('getList')->willReturn($results);

        $result = $this->finalizer->finalize($attempt, '240801000001');

        $this->assertSame($existing, $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $this->saved->getPaymentStatus());
        $this->assertSame(99, $this->saved->getOrderId());
        // No PENDING_PAYMENT capture path for the already-processing order.
        $payment->expects($this->never())->method('capture');
    }

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
        $this->finalizer->finalize($attempt);
    }

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
     * @param int $entityId
     * @param string $state
     * @param string $incrementId
     * @return Order|MockObject
     */
    private function newOrder(int $entityId, string $state, string $incrementId = '000000088'): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getEntityId')->willReturn($entityId);
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getState')->willReturn($state);

        return $order;
    }

    /**
     * @return PaymentAttempt
     */
    private function newPaidAttempt(): PaymentAttempt
    {
        return $this->newActiveAttempt()->markPaid('240801000001');
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
     * @return \Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt|\PHPUnit\Framework\MockObject\MockObject
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
