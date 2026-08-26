<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Model\PaymentAttempt;

/**
 * ZALOPAY-PAYMENT-FIRST Phase 1: the attempt state machine must be explicit —
 * legal transitions change state, illegal transitions throw, terminal states
 * never move, and a PAID transaction is never reused as a new attempt.
 */
class PaymentAttemptTest extends TestCase
{
    /**
     * @return void
     */
    public function testLegalLifecycleTransitions(): void
    {
        $attempt = $this->newAttempt();
        $this->assertSame(PaymentAttemptInterface::STATUS_INITIATED, $attempt->getPaymentStatus());

        $attempt->markActive('https://pay.zalopay.vn/xyz');
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
        $this->assertSame('https://pay.zalopay.vn/xyz', $attempt->getPayUrl());
        $this->assertSame('created', $attempt->getProviderStatus());

        $attempt->markPaid('123456');
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertSame('123456', $attempt->getProviderTransactionId());

        $attempt->markFinalized(77);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $attempt->getPaymentStatus());
        $this->assertSame(77, $attempt->getOrderId());
        $this->assertTrue($attempt->isTerminal());
    }

    /**
     * @return void
     */
    public function testFailurePathFromInitiatedAndActive(): void
    {
        $initiated = $this->newAttempt();
        $initiated->markFailed('provider error');
        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $initiated->getPaymentStatus());
        $this->assertSame('provider error', $initiated->getLastError());

        $active = $this->newAttempt()->markActive('https://pay.zalopay.vn/xyz');
        $active->markFailed('declined', 'cancelled');
        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $active->getPaymentStatus());
        $this->assertSame('cancelled', $active->getProviderStatus());
        $this->assertTrue($active->isTerminal());
    }

    /**
     * @return void
     */
    public function testIllegalTransitionThrows(): void
    {
        $attempt = $this->newAttempt();
        try {
            $attempt->markPaid('1');
            $this->fail('INITIATED -> PAID must be illegal.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('Illegal ZaloPay payment attempt transition', $e->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testFinalizedIsTerminalAndCannotMove(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay.zalopay.vn/xyz')->markPaid()->markFinalized(9);
        $this->assertTrue($attempt->isTerminal());
        $this->assertFalse($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_PAID));

        $this->expectException(LocalizedException::class);
        $attempt->markFailed('late failure');
    }

    /**
     * A SUCCESS transaction is never reusable as a new payment attempt.
     *
     * @return void
     */
    public function testPaidAndFinalizedAreNeverReusable(): void
    {
        $paid = $this->newAttempt()->markActive('https://pay.zalopay.vn/xyz')->markPaid();
        $this->assertFalse($paid->isReusable());

        $finalized = $paid->markFinalized(5);
        $this->assertFalse($finalized->isReusable());
    }

    /**
     * @return void
     */
    public function testActiveAttemptIsReusableUntilTtlElapses(): void
    {
        $attempt = $this->newAttempt()->markActive('https://pay.zalopay.vn/xyz');
        $this->assertTrue($attempt->isReusable('2026-08-26 10:00:00'));

        $expired = $this->newAttempt();
        $expired->setExpiresAt('2026-08-26 10:00:00');
        $expired->markActive('https://pay.zalopay.vn/xyz');
        $this->assertFalse($expired->isReusable('2026-08-26 10:15:00'));
        $this->assertTrue($expired->isExpired('2026-08-26 10:15:00'));
    }

    /**
     * @return void
     */
    public function testActiveAttemptWithoutPayUrlIsNotReusable(): void
    {
        $attempt = $this->newAttempt();
        $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_ACTIVE);
        $this->assertFalse($attempt->isReusable());
    }

    /**
     * @return PaymentAttempt
     */
    private function newAttempt(): PaymentAttempt
    {
        $attempt = $this->newAttemptModel();
        $attempt->setQuoteId(42);
        $attempt->setReservedOrderId('000000123');
        $attempt->setAmount(50000);
        $attempt->setCurrency(PaymentAttemptInterface::CURRENCY_VND);
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
