<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Gateway\Helper;

use Magento\Directory\Helper\Data;
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
}
