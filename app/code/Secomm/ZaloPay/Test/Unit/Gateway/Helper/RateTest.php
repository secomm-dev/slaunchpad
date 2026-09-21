<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Gateway\Helper;

use Magento\Directory\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Gateway\Helper\Rate;

class RateTest extends TestCase
{
    private Data|MockObject $helperDataMock;
    private Rate $rate;

    protected function setUp(): void
    {
        $this->helperDataMock = $this->createMock(Data::class);
        $this->rate = new Rate($this->helperDataMock);
    }

    public function testGetVndAmountByCurrencyWithVndReturnsRoundedFloat(): void
    {
        $this->assertSame(50000.0, $this->rate->getVndAmountByCurrency('VND', 50000));
        $this->assertSame(50000.0, $this->rate->getVndAmountByCurrency('VND', '50000'));
        $this->assertSame(1685000.0, $this->rate->getVndAmountByCurrency('VND', '1685000.0000'));
        $this->assertSame(10000.0, $this->rate->getVndAmountByCurrency('VND', '10,000'));
    }

    public function testGetVndAmountByCurrencyWithForeignCurrency(): void
    {
        $this->helperDataMock->expects($this->once())
            ->method('currencyConvert')
            ->with(100.0, 'USD', 'VND')
            ->willReturn(2480000.0);

        $result = $this->rate->getVndAmountByCurrency('USD', '100');
        $this->assertSame(2480000.0, $result);
    }

    public function testGetVndAmountWithOrderVnd(): void
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getOrderCurrencyCode')->willReturn('VND');

        $this->assertSame(150000.0, $this->rate->getVndAmount($orderMock, '150000.0000'));
        $this->assertSame(150000.0, $this->rate->getVndAmount($orderMock, '150,000'));
    }

    /**
     * RATE 8-12 (TASK-CG6BM7): every well-formed amount representation is
     * accepted: int, float, plain and decimal strings, thousands-grouped
     * decimal strings. Currency conversion path is untouched.
     */
    public function testWellFormedAmountsAreAccepted(): void
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getOrderCurrencyCode')->willReturn('VND');

        // int / float / plain decimal / grouped decimal — direct VND path.
        $this->assertSame(1685000.0, $this->rate->getVndAmountByCurrency('VND', 1685000));
        $this->assertSame(1685000.0, $this->rate->getVndAmountByCurrency('VND', 1685000.0));
        $this->assertSame(1685000.0, $this->rate->getVndAmountByCurrency('VND', '1685000'));
        $this->assertSame(1685000.0, $this->rate->getVndAmountByCurrency('VND', '1685000.00'));
        $this->assertSame(1685000.0, $this->rate->getVndAmountByCurrency('VND', '1685000.0000'));
        $this->assertSame(1685000.0, $this->rate->getVndAmountByCurrency('VND', '1,685,000.00'));
        $this->assertSame(-1235.0, $this->rate->getVndAmountByCurrency('VND', '-1,234.56'));

        // Same acceptance on the Order-based path (VND order, no conversion).
        $this->assertSame(1685000.0, $this->rate->getVndAmount($orderMock, 1685000));
        $this->assertSame(1685000.0, $this->rate->getVndAmount($orderMock, '1685000.00'));
        $this->assertSame(1685000.0, $this->rate->getVndAmount($orderMock, '1,685,000.00'));

        // Conversion path still works and uses the PARSED numeric value.
        $this->helperDataMock->expects($this->once())
            ->method('currencyConvert')
            ->with(1685000.0, 'EUR', 'VND')
            ->willReturn(45000000.0);
        $this->assertSame(45000000.0, $this->rate->getVndAmountByCurrency('EUR', '1,685,000.00'));
    }

    /**
     * RATE 13-15 (TASK-CG6BM7): malformed money is never silently coerced
     * to zero — every malformed representation throws LocalizedException
     * for both public entry points.
     */
    public function testMalformedAmountsAreRejected(): void
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getOrderCurrencyCode')->willReturn('VND');

        $malformed = ['abc', '1,2,3', '10foo', '', ' 12,3 ', '--1', '1.2.3', ',123', '123,'];
        foreach ($malformed as $amount) {
            try {
                $this->rate->getVndAmountByCurrency('VND', $amount);
                $this->fail("Expected LocalizedException for input " . var_export($amount, true));
            } catch (LocalizedException $e) {
                $this->assertStringContainsString('well-formed numeric amount', $e->getMessage());
            }
            try {
                $this->rate->getVndAmount($orderMock, $amount);
                $this->fail("Expected LocalizedException for input " . var_export($amount, true));
            } catch (LocalizedException $e) {
                $this->assertStringContainsString('well-formed numeric amount', $e->getMessage());
            }
        }

        // Non-scalar inputs: bool, null, array, object.
        foreach ([true, false, null, [1000], new \stdClass()] as $amount) {
            try {
                $this->rate->getVndAmountByCurrency('VND', $amount);
                $this->fail("Expected LocalizedException for non-scalar amount");
            } catch (LocalizedException $e) {
                $this->assertStringContainsString('well-formed numeric amount', $e->getMessage());
            }
        }
    }
}
