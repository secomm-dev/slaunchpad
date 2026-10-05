<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Mapper;

use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceMisa\Model\Mapper\SalesInvoiceContext;

/**
 * Unit tests for MeInvoice currency / exchange rate resolution.
 */
class SalesInvoiceContextTest extends TestCase
{
    /**
     * @return void
     */
    public function testGetExchangeRateReturnsOneForVndOrder(): void
    {
        $order = $this->createOrder('VND', 'VND');
        $context = new SalesInvoiceContext($this->createCurrencyFactory());

        self::assertSame(1.0, $context->getExchangeRate($order));
    }

    /**
     * @return void
     */
    public function testGetExchangeRateReturnsDirectoryRateForUsdOrder(): void
    {
        $order = $this->createOrder('USD', 'USD');
        $context = new SalesInvoiceContext($this->createCurrencyFactory(25400.0));

        self::assertSame(25400.0, $context->getExchangeRate($order));
    }

    /**
     * @return void
     */
    public function testGetExchangeRateThrowsWhenRateMissing(): void
    {
        $order = $this->createOrder('USD', 'USD');
        $context = new SalesInvoiceContext($this->createCurrencyFactory(0.0));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Missing currency rate');

        $context->getExchangeRate($order);
    }

    /**
     * @return void
     */
    public function testConvertOcToVndWithUnitRate(): void
    {
        $context = new SalesInvoiceContext($this->createCurrencyFactory());

        self::assertSame(220.0, $context->convertOcToVnd(220.0, 1.0));
        self::assertSame(9.88, $context->convertOcToVnd(9.88, 1.0));
    }

    /**
     * @return void
     */
    public function testConvertOcToVndMultipliesAndRounds(): void
    {
        $context = new SalesInvoiceContext($this->createCurrencyFactory());

        self::assertSame(5588000.0, $context->convertOcToVnd(220.0, 25400.0));
    }

    /**
     * @param string $orderCurrency
     * @param string $baseCurrency
     * @return Order&MockObject
     */
    private function createOrder(string $orderCurrency, string $baseCurrency): Order
    {
        /** @var Order&MockObject $order */
        $order = $this->createMock(Order::class);
        $order->method('getOrderCurrencyCode')->willReturn($orderCurrency);
        $order->method('getBaseCurrencyCode')->willReturn($baseCurrency);

        return $order;
    }

    /**
     * @param float $usdToVndRate
     * @return CurrencyFactory&MockObject
     */
    private function createCurrencyFactory(float $usdToVndRate = 1.0): CurrencyFactory
    {
        /** @var Currency&MockObject $currency */
        $currency = $this->createMock(Currency::class);
        $currency->method('load')->willReturnSelf();
        $currency->method('getAnyRate')->with('VND')->willReturn($usdToVndRate);

        /** @var CurrencyFactory&MockObject $factory */
        $factory = $this->createMock(CurrencyFactory::class);
        $factory->method('create')->willReturn($currency);

        return $factory;
    }
}
