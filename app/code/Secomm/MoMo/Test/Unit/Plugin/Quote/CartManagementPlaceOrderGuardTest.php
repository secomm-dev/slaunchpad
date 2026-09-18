<?php
/**
 * Unit test for the QuoteManagement placeOrder guard (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Plugin\Quote;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Model\PaymentAttempt;
use Secomm\MoMo\Plugin\Quote\CartManagementPlaceOrderGuard;
use Secomm\MoMo\Service\OrderPlacementAuthorization;

/**
 * Verifies the server-side guard: generic placeOrder calls on a MoMo quote
 * are refused unless the exact internal grant — validated against the
 * PERSISTED attempt triple — is consumable (AC2/AC7).
 */
class CartManagementPlaceOrderGuardTest extends TestCase
{
    private CartRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $quoteRepository;

    private PaymentAttemptRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $attemptRepository;

    private MethodInterface&\PHPUnit\Framework\MockObject\MockObject $method;

    private OrderPlacementAuthorization $authorization;

    private CartManagementPlaceOrderGuard $guard;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->attemptRepository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->method = $this->createMock(MethodInterface::class);
        $this->method->method('getCode')->willReturn('momo_payment');
        $this->authorization = new OrderPlacementAuthorization();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $this->guard = new CartManagementPlaceOrderGuard(
            $this->quoteRepository,
            $this->attemptRepository,
            $this->method,
            $this->authorization,
            $logger
        );
    }

    /**
     * Non-MoMo quotes pass through untouched.
     *
     * @return void
     */
    public function testNonMoMoQuotePassesThrough(): void
    {
        $quote = $this->quote('checkmo');
        $this->quoteRepository->method('get')->willReturn($quote);

        $this->assertNull($this->guard->beforePlaceOrder(
            $this->createMock(CartManagementInterface::class),
            42
        ));
    }

    /**
     * A MoMo quote with no open grant is refused — the generic checkout
     * placeOrder path can never create the order.
     *
     * @return void
     */
    public function testMoMoQuoteWithoutGrantIsBlocked(): void
    {
        $this->quoteRepository->method('get')->willReturn($this->quote('momo_payment'));

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('The MoMo order can only be created after the payment is verified');

        $this->guard->beforePlaceOrder($this->createMock(CartManagementInterface::class), 42);
    }

    /**
     * A grant whose attempt row no longer exists is refused — a grant is
     * only ever backed by the persisted attempt.
     *
     * @return void
     */
    public function testGrantWithoutPersistedAttemptIsBlocked(): void
    {
        $this->quoteRepository->method('get')->willReturn($this->quote('momo_payment'));
        $this->attemptRepository->method('getById')->willThrowException(
            new NoSuchEntityException(__('No such entity.'))
        );
        $this->authorization->grant(42, 999, 'MOMOREF');

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);

        $this->guard->beforePlaceOrder($this->createMock(CartManagementInterface::class), 42);
    }

    /**
     * A grant whose persisted attempt does not bind the same order_ref is
     * refused (attempt-id smuggling).
     *
     * @return void
     */
    public function testGrantWithMismatchedOrderRefIsBlocked(): void
    {
        $this->quoteRepository->method('get')->willReturn($this->quote('momo_payment'));
        $this->attemptRepository->method('getById')->willReturn($this->attempt(42, 'MOMOACTUAL'));
        $this->authorization->grant(42, 7, 'MOMOFORGED');

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);

        $this->guard->beforePlaceOrder($this->createMock(CartManagementInterface::class), 42);
    }

    /**
     * The full valid triple — grant + persisted attempt with the same
     * quote_id and order_ref — consumes the grant exactly once.
     *
     * @return void
     */
    public function testValidGrantIsConsumedOnce(): void
    {
        $this->quoteRepository->method('get')->willReturn($this->quote('momo_payment'));
        $this->attemptRepository->method('getById')->willReturn($this->attempt(42, 'MOMOREF'));
        $this->authorization->grant(42, 7, 'MOMOREF');

        $subject = $this->createMock(CartManagementInterface::class);
        $this->assertNull($this->guard->beforePlaceOrder($subject, 42));
        $this->assertFalse($this->authorization->isOpen());

        // The consumed grant cannot authorize a second placement.
        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->guard->beforePlaceOrder($subject, 42);
    }

    /**
     * A persisted attempt bound to a DIFFERENT quote never backs a grant.
     *
     * @return void
     */
    public function testGrantBoundToAnotherQuoteIsBlocked(): void
    {
        $this->quoteRepository->method('get')->willReturn($this->quote('momo_payment'));
        $this->attemptRepository->method('getById')->willReturn($this->attempt(43, 'MOMOREF'));
        $this->authorization->grant(42, 7, 'MOMOREF');

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);

        $this->guard->beforePlaceOrder($this->createMock(CartManagementInterface::class), 42);
    }

    /**
     * Quote mock with the given payment method.
     *
     * @param string $methodCode
     * @return Quote&\PHPUnit\Framework\MockObject\MockObject
     */
    private function quote(string $methodCode): Quote
    {
        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getMethod')->willReturn($methodCode);
        $quote = $this->createMock(Quote::class);
        $quote->method('getPayment')->willReturn($payment);

        return $quote;
    }

    /**
     * A real persisted-shaped attempt.
     *
     * @param int $quoteId
     * @param string $orderRef
     * @return PaymentAttempt
     */
    private function attempt(int $quoteId, string $orderRef): PaymentAttempt
    {
        $attempt = new PaymentAttempt($this->createMock(Context::class), $this->createMock(Registry::class));
        $attempt->setEntityId(7);
        $attempt->setQuoteId($quoteId);
        $attempt->setOrderRef($orderRef);

        return $attempt;
    }
}
