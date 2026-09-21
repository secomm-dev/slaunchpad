<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\VietQr\Test\Unit\Model;

use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use Secomm\VietQr\Model\VndAmount;

/**
 * BUG-4BX0CK / SLP-234: the VietQR amount must always be VND — an order
 * placed in another display currency (e.g. EN store view) must be converted,
 * never passed to the bank transfer in its foreign amount.
 */
class VndAmountTest extends TestCase
{
    /**
     * @return void
     */
    public function testOrderCurrencyVndUsesGrandTotal(): void
    {
        $order = $this->createOrder('VND', 'VND', 1250000.0, 1250000.0);

        $this->assertSame(1250000, $this->createResolver()->get($order));
    }

    /**
     * VND base short-circuits to base grand total — no rate lookup needed.
     *
     * @return void
     */
    public function testForeignOrderCurrencyWithVndBaseUsesBaseGrandTotal(): void
    {
        $order = $this->createOrder('USD', 'VND', 50.0, 1250000.0);

        $this->assertSame(1250000, $this->createResolver()->get($order));
    }

    /**
     * Foreign order and foreign base: convert via the directory rate.
     *
     * @return void
     */
    public function testForeignOrderWithoutVndBaseConvertsViaRate(): void
    {
        $usd = $this->createMock(Currency::class);
        $usd->method('load')->willReturnSelf();
        $usd->method('convert')->with(50.0, 'VND')->willReturn(1250000.4);

        $factory = $this->createMock(CurrencyFactory::class);
        $factory->method('create')->willReturn($usd);

        $order = $this->createOrder('USD', 'AUD', 50.0, 75.0);

        $this->assertSame(1250000, (new VndAmount($factory))->get($order));
    }

    /**
     * @return void
     */
    public function testFormat(): void
    {
        $this->assertSame('1.250.000 VND', $this->createResolver()->format(1250000));
    }

    /**
     * @return VndAmount
     */
    private function createResolver(): VndAmount
    {
        $factory = $this->createMock(CurrencyFactory::class);
        $factory->method('create')->willReturn($this->createMock(Currency::class));

        return new VndAmount($factory);
    }

    /**
     * @param string $orderCurrency
     * @param string $baseCurrency
     * @param float $grandTotal
     * @param float $baseGrandTotal
     * @return OrderInterface
     */
    private function createOrder(
        string $orderCurrency,
        string $baseCurrency,
        float $grandTotal,
        float $baseGrandTotal
    ): OrderInterface {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getOrderCurrencyCode')->willReturn($orderCurrency);
        $order->method('getBaseCurrencyCode')->willReturn($baseCurrency);
        $order->method('getGrandTotal')->willReturn($grandTotal);
        $order->method('getBaseGrandTotal')->willReturn($baseGrandTotal);

        return $order;
    }
}
