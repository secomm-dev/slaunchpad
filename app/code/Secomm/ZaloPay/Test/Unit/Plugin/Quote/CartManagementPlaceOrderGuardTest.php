<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Plugin\Quote;

use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Plugin\Quote\CartManagementPlaceOrderGuard;
use Secomm\ZaloPay\Service\OrderPlacementAuthorization;
use Secomm\ZaloPay\Test\Unit\Model\QuoteStub;

/**
 * placeOrder guard contract (corrective TASK-EDS9T5 Blocker 3): the guard
 * validates the grant against the PERSISTED attempt — the attempt row must
 * exist, carry the granted app_trans_id AND the quote id being placed
 * (exact triple). A quote id, a PAID status, history or browser input
 * never authorizes; the pass-through consumes the grant exactly once.
 *
 * The tests exercise the ACTUAL production beforePlaceOrder() method.
 */
class CartManagementPlaceOrderGuardTest extends TestCase
{
    private const QUOTE_ID = 42;
    private const ATTEMPT_ID = 9;
    private const APP_TRANS_ID = '260826_1000_000000123';

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $quoteRepository;

    /**
     * @var PaymentAttemptRepositoryInterface|MockObject
     */
    private $attemptRepository;

    /**
     * @var OrderPlacementAuthorization
     */
    private OrderPlacementAuthorization $authorization;

    /**
     * @var CartManagementPlaceOrderGuard
     */
    private CartManagementPlaceOrderGuard $guard;

    protected function setUp(): void
    {
        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->attemptRepository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->authorization = new OrderPlacementAuthorization(); // REAL grant state machine
        $method = $this->createMock(\Magento\Payment\Model\MethodInterface::class);
        $method->method('getCode')->willReturn('zalopay');

        $this->guard = new CartManagementPlaceOrderGuard(
            $this->quoteRepository,
            $this->attemptRepository,
            $method,
            $this->authorization,
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * Non-ZaloPay quote: untouched, no grant interaction at all.
     *
     * @return void
     */
    public function testNonZaloPayQuotePassesThrough(): void
    {
        $this->stubQuote('checkmo');

        $this->assertNull($this->guard->beforePlaceOrder(
            $this->createMock(CartManagementInterface::class),
            self::QUOTE_ID
        ));
        $this->assertFalse($this->authorization->isOpen());
    }

    /**
     * Unknown quote: pass-through (the core call raises its own error).
     *
     * @return void
     */
    public function testUnknownQuotePassesThrough(): void
    {
        $this->quoteRepository->method('get')
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(__('No such entity.')));

        $this->assertNull($this->guard->beforePlaceOrder(
            $this->createMock(CartManagementInterface::class),
            self::QUOTE_ID
        ));
    }

    /**
     * ZaloPay quote without any grant: blocked — even when a historical
     * PAID attempt exists for the quote (case 15: PAID status alone never
     * authorizes).
     *
     * @return void
     */
    public function testZaloPayQuoteWithPaidHistoryButNoGrantIsBlocked(): void
    {
        $this->stubQuote('zalopay');
        $this->stubPersistedAttempt($this->newAttemptMock(PaymentAttemptInterface::STATUS_PAID));

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('can only be created after the payment is verified');
        $this->guard->beforePlaceOrder($this->createMock(CartManagementInterface::class), self::QUOTE_ID);
    }

    /**
     * Case 16: a grant binding only the quote (no attempt backing) is
     * insufficient — the guard requires the persisted attempt triple.
     *
     * @return void
     */
    public function testQuoteOnlyGrantIsInsufficient(): void
    {
        $this->stubQuote('zalopay');
        // The attempt repo has no row that backs the grant: whatever the
        // grant says, a missing row blocks. Simulate an attempt-id pointing
        // nowhere while a grant for the quote is open.
        $this->attemptRepository->method('get')
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(__('No such entity.')));

        // Forge the "quote-only" shape: open a grant, then prove the guard
        // cannot pass on quote match alone — the persisted attempt lookup
        // decides.
        $this->authorization->grant(self::QUOTE_ID, 999999, self::APP_TRANS_ID);

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('can only be created after the payment is verified');
        $this->guard->beforePlaceOrder($this->createMock(CartManagementInterface::class), self::QUOTE_ID);
    }

    /**
     * Case 17: a grant whose attempt_id points to an attempt belonging to
     * ANOTHER quote is blocked.
     *
     * @return void
     */
    public function testGrantWithWrongAttemptQuoteIdIsBlocked(): void
    {
        $this->stubQuote('zalopay');
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);
        $this->stubPersistedAttempt($this->newAttemptMock(PaymentAttemptInterface::STATUS_PAID, 43)); // other quote

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('can only be created after the payment is verified');
        $this->guard->beforePlaceOrder($this->createMock(CartManagementInterface::class), self::QUOTE_ID);
    }

    /**
     * Case 18: a grant whose app_trans_id does not match the PERSISTED
     * attempt's app_trans_id is blocked.
     *
     * @return void
     */
    public function testGrantWithWrongAppTransIdIsBlocked(): void
    {
        $this->stubQuote('zalopay');
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, '260826_1000_999999999');
        $this->stubPersistedAttempt($this->newAttemptMock(PaymentAttemptInterface::STATUS_PAID));

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('can only be created after the payment is verified');
        $this->guard->beforePlaceOrder($this->createMock(CartManagementInterface::class), self::QUOTE_ID);
    }

    /**
     * Case 19: the EXACT persisted triple (placed quote id = persisted
     * attempt quote id, granted app_trans_id = persisted app_trans_id,
     * granted attempt_id = the loaded row) allows exactly ONE placement.
     *
     * @return void
     */
    public function testExactPersistedTripleAllowsOnePlacement(): void
    {
        $this->stubQuote('zalopay');
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);
        $this->stubPersistedAttempt($this->newAttemptMock(PaymentAttemptInterface::STATUS_PAID));

        $this->assertNull($this->guard->beforePlaceOrder(
            $this->createMock(CartManagementInterface::class),
            self::QUOTE_ID
        ));
        $this->assertFalse($this->authorization->isOpen(), 'The grant is consumed by the pass-through.');
    }

    /**
     * Case 20: the grant is single-use — a second generic placeOrder in the
     * same request is blocked even though the first consumed the exact
     * triple.
     *
     * @return void
     */
    public function testSecondPlaceOrderAfterConsumptionIsBlocked(): void
    {
        $this->stubQuote('zalopay');
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);
        $this->stubPersistedAttempt($this->newAttemptMock(PaymentAttemptInterface::STATUS_PAID));

        $this->assertNull($this->guard->beforePlaceOrder($this->createMock(CartManagementInterface::class), self::QUOTE_ID));

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('can only be created after the payment is verified');
        $this->guard->beforePlaceOrder($this->createMock(CartManagementInterface::class), self::QUOTE_ID);
    }

    /**
     * A grant for a DIFFERENT quote does not authorize this quote.
     *
     * @return void
     */
    public function testGrantForDifferentQuoteDoesNotAuthorize(): void
    {
        $this->stubQuote('zalopay');
        $this->authorization->grant(43, self::ATTEMPT_ID, self::APP_TRANS_ID);
        $this->stubPersistedAttempt($this->newAttemptMock(PaymentAttemptInterface::STATUS_PAID));

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('can only be created after the payment is verified');
        $this->guard->beforePlaceOrder($this->createMock(CartManagementInterface::class), self::QUOTE_ID);
    }

    /**
     * String cart ids (guest carts / webapi) are cast to int consistently.
     *
     * @return void
     */
    public function testStringCartIdIsHandled(): void
    {
        $this->stubQuote('zalopay');
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);
        $this->stubPersistedAttempt($this->newAttemptMock(PaymentAttemptInterface::STATUS_PAID));

        $this->assertNull($this->guard->beforePlaceOrder(
            $this->createMock(CartManagementInterface::class),
            (string)self::QUOTE_ID
        ));
    }

    // ---- helpers ----

    /**
     * @param string $method
     * @return void
     */
    private function stubQuote(string $method): void
    {
        $payment = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);
        $payment->method('getMethod')->willReturn($method);
        $quote = $this->createMock(QuoteStub::class);
        $quote->method('getPayment')->willReturn($payment);
        $this->quoteRepository->method('get')->willReturn($quote);
    }

    /**
     * @param PaymentAttemptInterface|MockObject $attempt
     * @return void
     */
    private function stubPersistedAttempt($attempt): void
    {
        $this->attemptRepository->method('get')->with(self::ATTEMPT_ID)->willReturn($attempt);
    }

    /**
     * @param string $status
     * @param int $quoteId
     * @return PaymentAttemptInterface|MockObject
     */
    private function newAttemptMock(string $status, int $quoteId = self::QUOTE_ID)
    {
        $attempt = $this->createMock(PaymentAttemptInterface::class);
        $attempt->method('getQuoteId')->willReturn($quoteId);
        $attempt->method('getAppTransId')->willReturn(self::APP_TRANS_ID);
        $attempt->method('getPaymentStatus')->willReturn($status);

        return $attempt;
    }
}
