<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Secomm\ZaloPay\Service\CreditmemoRefundPreflight;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * TASK-CG6BM7 corrective round 2 - BLOCKER: Magento validation BEFORE the
 * provider call. Parity matrix for the mirror of the protected core method
 * CreditmemoService::validateForRefund() (vendor 2.4.8-p5, lines 189-219):
 * every core check is pinned 1:1 so a Magento core change surfaces in review
 * instead of drifting silently (see CreditmemoRefundPreflight docblock).
 */
class CreditmemoRefundPreflightTest extends TestCase
{
    private PriceCurrencyInterface|MockObject $priceCurrency;

    private CreditmemoRefundPreflight $preflight;

    /**
     * Mutable test state (PHPUnit stubs on the same method do not override
     * each other, so tests flip these instead of reconfiguring stubs).
     */
    private ?int $cmId = null;

    private ?int $cmState = Creditmemo::STATE_OPEN;

    private ?int $orderId = 77;

    private bool $unresolvableOrder = false;

    private float $baseTotalRefunded = 0.0;

    private float $baseTotalPaid = 100.0;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $this->priceCurrency->method('round')->willReturnCallback(
            fn (float $v): float => round($v, 2)
        );
        $this->preflight = new CreditmemoRefundPreflight($this->priceCurrency);
    }

    /**
     * Credit memo mock bound to the mutable test state (core-like defaults:
     * new credit memo, no id, STATE_OPEN, amount 25, order paid 100/refunded 0).
     */
    private function makeCreditmemo(float $grandTotal = 25.0): Creditmemo|MockObject
    {
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getId')->willReturnCallback(fn (): ?int => $this->cmId);
        $creditmemo->method('getState')->willReturnCallback(fn (): ?int => $this->cmState);
        $creditmemo->method('getOrderId')->willReturnCallback(fn (): ?int => $this->orderId);
        $creditmemo->method('getBaseGrandTotal')->willReturn($grandTotal);
        $creditmemo->method('getOrder')->willReturnCallback(function (): ?Order {
            if ($this->unresolvableOrder) {
                return null;
            }
            $order = $this->createMock(Order::class);
            $order->method('getBaseTotalRefunded')->willReturnCallback(fn (): float => $this->baseTotalRefunded);
            $order->method('getBaseTotalPaid')->willReturnCallback(fn (): float => $this->baseTotalPaid);
            $baseCurrency = $this->createMock(\Magento\Directory\Model\Currency::class);
            $baseCurrency->method('formatTxt')->willReturnCallback(fn (float $v): string => (string)$v);
            $order->method('getBaseCurrency')->willReturn($baseCurrency);

            return $order;
        });

        return $creditmemo;
    }

    /**
     * Core mirror check 1: an EXISTING non-open credit memo is refused.
     */
    public function testExistingNonOpenCreditmemoRefused(): void
    {
        $this->cmId = 33;
        $this->cmState = Creditmemo::STATE_REFUNDED;
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('We cannot register an existing credit memo.');
        $this->preflight->validateRefundable($this->makeCreditmemo());
    }

    /**
     * Core mirror check 1 parity: an existing OPEN credit memo passes.
     */
    public function testExistingOpenCreditmemoAllowed(): void
    {
        $this->cmId = 33;
        $this->cmState = Creditmemo::STATE_OPEN;
        $this->preflight->validateRefundable($this->makeCreditmemo());
        $this->addToAssertionCount(1);
    }

    /**
     * Core mirror check 2: a credit memo without an order id is refused
     * (same NoSuchEntityException contract as core).
     */
    public function testMissingOrderIdRefused(): void
    {
        $this->orderId = null;
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('We found an invalid order to refund.');
        $this->preflight->validateRefundable($this->makeCreditmemo());
    }

    /**
     * Core mirror check 2 (supplementary hardening): an order reference that
     * does not resolve to an order model is refused.
     */
    public function testUnresolvableOrderRefused(): void
    {
        $this->unresolvableOrder = true;
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('We found an invalid order to refund.');
        $this->preflight->validateRefundable($this->makeCreditmemo());
    }

    /**
     * Core mirror check 3: over-refund is refused (refunded + requested >
     * total paid) - the provider must NEVER be called for this refund.
     */
    public function testOverRefundRefused(): void
    {
        $this->baseTotalRefunded = 100.0;
        $this->baseTotalPaid = 100.0;
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The most money available to refund is');
        $this->preflight->validateRefundable($this->makeCreditmemo(25.0));
    }

    /**
     * Core mirror check 3 parity: refunding exactly the remaining balance
     * passes (boundary: rounded equal is allowed, as in core).
     */
    public function testRefundUpToRemainingBalanceAllowed(): void
    {
        $this->baseTotalRefunded = 25.0;
        $this->baseTotalPaid = 100.0;
        $this->preflight->validateRefundable($this->makeCreditmemo(75.0));
        $this->addToAssertionCount(1);
    }

    /**
     * Supplementary online check: a zero-amount online refund is refused.
     */
    public function testZeroOnlineAmountRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('must be greater than zero');
        $this->preflight->validateRefundable($this->makeCreditmemo(0.0));
    }

    /**
     * Supplementary online check: a negative-amount online refund is refused.
     */
    public function testNegativeOnlineAmountRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('must be greater than zero');
        $this->preflight->validateRefundable($this->makeCreditmemo(-5.0));
    }

    /**
     * A valid refund passes the whole preflight (provider may be asked).
     */
    public function testValidRefundPasses(): void
    {
        $this->preflight->validateRefundable($this->makeCreditmemo(25.0));
        $this->addToAssertionCount(1);
    }
}
