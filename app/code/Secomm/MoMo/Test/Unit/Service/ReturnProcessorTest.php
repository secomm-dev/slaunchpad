<?php
/**
 * Unit test for the MoMo return processor.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Model\Ui\ConfigProvider;
use Secomm\MoMo\Service\ReturnProcessor;

/**
 * Verifies the return processor rebuilds the success session from the order
 * MoMo reports (not from a possibly-lost checkout session), refuses orders
 * that are not MoMo orders, and keeps the lenient cart path for non-zero
 * resultCodes without touching session or order lookup.
 */
class ReturnProcessorTest extends TestCase
{
    /**
     * @var SessionStub
     */
    private SessionStub $checkoutSession;

    /**
     * @var OrderRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var SearchCriteriaBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private LoggerInterface $logger;

    /**
     * @var ReturnProcessor
     */
    private ReturnProcessor $processor;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->checkoutSession = new SessionStub();
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')
            ->willReturn($this->createMock(SearchCriteriaInterface::class));

        $this->processor = new ReturnProcessor(
            $this->checkoutSession,
            $this->orderRepository,
            $this->searchCriteriaBuilder,
            $this->logger
        );
    }

    /**
     * Build an order mock paid with the given method.
     *
     * @param string $method
     * @return OrderInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeOrder(string $method = ConfigProvider::CODE)
    {
        $payment = $this->createMock(OrderPaymentInterface::class);
        $payment->method('getMethod')->willReturn($method);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(12);
        $order->method('getIncrementId')->willReturn('000000012');
        $order->method('getQuoteId')->willReturn(34);
        $order->method('getState')->willReturn('processing');
        $order->method('getPayment')->willReturn($payment);

        return $order;
    }

    /**
     * Route the order repository to the given orders.
     *
     * @param OrderInterface ...$orders
     * @return void
     */
    private function stubSearchResults(OrderInterface ...$orders): void
    {
        $results = $this->createMock(OrderSearchResultInterface::class);
        $results->method('getItems')->willReturn($orders);
        $this->orderRepository->method('getList')->willReturn($results);
    }

    /**
     * resultCode 0 with an orderId parameter: the order is loaded by MoMo's
     * orderId and ALL success-session keys are rebuilt from it (fresh-session
     * duplicate return lands on a valid success page, not the cart).
     *
     * @return void
     */
    public function testSuccessRebuildsAllSessionKeysFromOrderParam(): void
    {
        $this->stubSearchResults($this->makeOrder());

        $path = $this->processor->process(['resultCode' => 0, 'orderId' => '000000012']);

        $this->assertSame(ReturnProcessor::PATH_SUCCESS, $path);
        $this->assertTrue($this->checkoutSession->calls['clearHelperData'] ?? false);
        $this->assertSame(34, $this->checkoutSession->calls['last_quote_id']);
        $this->assertSame(34, $this->checkoutSession->calls['last_success_quote_id']);
        $this->assertSame(12, $this->checkoutSession->calls['last_order_id']);
        $this->assertSame('000000012', $this->checkoutSession->calls['last_real_order_id']);
        $this->assertSame('processing', $this->checkoutSession->calls['last_order_status']);
        $this->assertSame(0, $this->checkoutSession->lastRealOrderIdReads);
    }

    /**
     * resultCode 0 without an orderId parameter: fall back to the checkout
     * session's last real order id (the original same-session behaviour).
     *
     * @return void
     */
    public function testSuccessFallsBackToSessionOrderWhenParamMissing(): void
    {
        $this->stubSearchResults($this->makeOrder());
        $this->checkoutSession->lastRealOrderId = '000000012';

        $path = $this->processor->process(['resultCode' => 0]);

        $this->assertSame(ReturnProcessor::PATH_SUCCESS, $path);
        $this->assertSame(12, $this->checkoutSession->calls['last_order_id']);
        $this->assertSame('000000012', $this->checkoutSession->calls['last_real_order_id']);
    }

    /**
     * resultCode 0 but the increment id matches no order: customer-safe
     * exception, and nothing is written to the session.
     *
     * @return void
     */
    public function testOrderNotFoundThrowsWithoutSessionWrites(): void
    {
        $this->stubSearchResults();

        try {
            $this->processor->process(['resultCode' => 0, 'orderId' => 'NOPE']);
            $this->fail('Expected LocalizedException was not thrown.');
        } catch (LocalizedException $e) {
            $this->assertSame([], $this->checkoutSession->calls);
        }
    }

    /**
     * resultCode 0 but the resolved order was paid by another method: refuse —
     * a forged or mismatched return must never hijack a foreign success page.
     *
     * @return void
     */
    public function testNonMoMoOrderIsRefused(): void
    {
        $this->stubSearchResults($this->makeOrder('checkmo'));

        try {
            $this->processor->process(['resultCode' => 0, 'orderId' => '000000012']);
            $this->fail('Expected LocalizedException was not thrown.');
        } catch (LocalizedException $e) {
            $this->assertSame([], $this->checkoutSession->calls);
        }
    }

    /**
     * No orderId parameter and an empty session: nothing to resolve.
     *
     * @return void
     */
    public function testMissingOrderEverywhereThrows(): void
    {
        $this->orderRepository->expects($this->never())->method('getList');

        $this->expectException(LocalizedException::class);
        $this->processor->process(['resultCode' => 0]);
    }

    /**
     * Non-zero resultCode keeps the lenient historical behaviour: cart path,
     * no order lookup, no session access at all.
     *
     * @return void
     */
    public function testFailureResultCodeSkipsLookupAndSession(): void
    {
        $this->orderRepository->expects($this->never())->method('getList');

        $path = $this->processor->process(['resultCode' => 7000, 'orderId' => '000000012']);

        $this->assertSame(ReturnProcessor::PATH_CART, $path);
        $this->assertSame([], $this->checkoutSession->calls);
        $this->assertSame(0, $this->checkoutSession->lastRealOrderIdReads);
    }
}
