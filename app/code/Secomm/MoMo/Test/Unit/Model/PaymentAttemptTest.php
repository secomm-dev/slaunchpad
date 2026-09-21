<?php
/**
 * Unit test for the MoMo payment attempt state machine (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Model\PaymentAttempt;

/**
 * Verifies the attempt transition map: the legal lifecycle, illegal moves
 * throwing, terminal states without outgoing edges, money-real state never
 * regressing to FAILED, and reuse/expiry semantics.
 */
class PaymentAttemptTest extends TestCase
{
    /**
     * A fresh attempt defaults to INITIATED and may become ACTIVE.
     *
     * @return void
     */
    public function testNewAttemptDefaultsToInitiated(): void
    {
        $attempt = $this->newAttempt();

        $this->assertSame(PaymentAttemptInterface::STATUS_INITIATED, $attempt->getPaymentStatus());
        $this->assertTrue($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_ACTIVE));
        $this->assertTrue($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_FAILED));
        $this->assertFalse($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_PAID));
        $this->assertFalse($attempt->isTerminal());
    }

    /**
     * The full legal lifecycle: INITIATED -> ACTIVE -> PAID -> FINALIZED.
     *
     * @return void
     */
    public function testLegalLifecycleToFinalized(): void
    {
        $attempt = $this->newAttempt();
        $attempt->markActive('https://payment.momo.vn/pay/abc');
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
        $this->assertSame('https://payment.momo.vn/pay/abc', $attempt->getPayUrl());

        $attempt->markPaid('987654321');
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertSame('987654321', $attempt->getProviderTransactionId());
        $this->assertTrue($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_FINALIZED));

        $attempt->markFinalized(5001);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $attempt->getPaymentStatus());
        $this->assertSame(5001, $attempt->getOrderId());
        $this->assertTrue($attempt->isTerminal());
    }

    /**
     * markPaid on a fresh INITIATED attempt throws — money is only real
     * after the provider transaction went ACTIVE.
     *
     * @return void
     */
    public function testMarkPaidFromInitiatedThrows(): void
    {
        $attempt = $this->newAttempt();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Illegal MoMo payment attempt transition');

        $attempt->markPaid('987654321');
    }

    /**
     * markFinalized directly from ACTIVE throws: exactly one PAID step is
     * mandatory (the authoritative verification boundary).
     *
     * @return void
     */
    public function testMarkFinalizedFromActiveThrows(): void
    {
        $attempt = $this->newAttempt();
        $attempt->markActive('https://payment.momo.vn/pay/abc');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Illegal MoMo payment attempt transition');

        $attempt->markFinalized(5001);
    }

    /**
     * Money-real PAID never regresses to FAILED.
     *
     * @return void
     */
    public function testMarkFailedFromPaidThrows(): void
    {
        $attempt = $this->newAttempt();
        $attempt->markActive('https://payment.momo.vn/pay/abc');
        $attempt->markPaid('987654321');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Illegal MoMo payment attempt transition');

        $attempt->markFailed('late cancel');
    }

    /**
     * Terminal states have no outgoing edges.
     *
     * @return void
     */
    public function testFinalizedIsTerminal(): void
    {
        $attempt = $this->newAttempt();
        $attempt->markActive('https://payment.momo.vn/pay/abc');
        $attempt->markPaid('987654321');
        $attempt->markFinalized(5001);

        $this->assertFalse($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_PAID));
        $this->assertFalse($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_FAILED));
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Illegal MoMo payment attempt transition');

        $attempt->markPaid('000');
    }

    /**
     * markPaid without a provider id never invents one.
     *
     * @return void
     */
    public function testMarkPaidWithoutProviderIdLeavesIdentityEmpty(): void
    {
        $attempt = $this->newAttempt();
        $attempt->markActive('https://payment.momo.vn/pay/abc');
        $attempt->markPaid(null);

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertNull($attempt->getProviderTransactionId());
    }

    /**
     * markFailed records the customer-safe message and the provider status.
     *
     * @return void
     */
    public function testMarkFailedRecordsErrorAndProviderStatus(): void
    {
        $attempt = $this->newAttempt();
        $attempt->markFailed('User cancelled at MoMo.', 'user_cancelled');

        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $attempt->getPaymentStatus());
        $this->assertSame('User cancelled at MoMo.', $attempt->getLastError());
        $this->assertSame('user_cancelled', $attempt->getProviderStatus());
        $this->assertTrue($attempt->isTerminal());
    }

    /**
     * markStale is legal from INITIATED and ACTIVE, never from PAID.
     *
     * @return void
     */
    public function testMarkStaleFromPaidThrows(): void
    {
        $attempt = $this->newAttempt();
        $attempt->markActive('https://payment.momo.vn/pay/abc');
        $attempt->markPaid('987654321');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Illegal MoMo payment attempt transition');

        $attempt->markStale();
    }

    /**
     * Reuse requires ACTIVE with a pay URL.
     *
     * @return void
     */
    public function testIsReusableRequiresActiveWithPayUrl(): void
    {
        $initiated = $this->newAttempt();
        $this->assertFalse($initiated->isReusable());

        $activeNoUrl = $this->newAttempt();
        $activeNoUrl->markActive('');
        $this->assertFalse($activeNoUrl->isReusable());

        $active = $this->newAttempt();
        $active->markActive('https://payment.momo.vn/pay/abc');
        $this->assertTrue($active->isReusable());
    }

    /**
     * An expired ACTIVE attempt is not reusable (TTL respected).
     *
     * @return void
     */
    public function testIsReusableRespectsExpiry(): void
    {
        $active = $this->newAttempt();
        $active->markActive('https://payment.momo.vn/pay/abc');
        $active->setExpiresAt('2026-09-18 10:00:00');

        $this->assertTrue($active->isReusable('2026-09-18 09:59:59'));
        $this->assertTrue($active->isReusable('2026-09-18 10:00:00'));
        $this->assertFalse($active->isReusable('2026-09-18 10:00:01'));
    }

    /**
     * No expires_at: the attempt never expires (open-ended reuse).
     *
     * @return void
     */
    public function testIsExpiredWithNullExpiryIsNeverExpired(): void
    {
        $attempt = $this->newAttempt();

        $this->assertFalse($attempt->isExpired());
        $this->assertFalse($attempt->isExpired('2999-01-01 00:00:00'));
    }

    /**
     * Concrete attempt factory without ObjectManager.
     *
     * @return PaymentAttempt
     */
    private function newAttempt(): PaymentAttempt
    {
        return new PaymentAttempt($this->createMock(Context::class), $this->createMock(Registry::class));
    }
}
