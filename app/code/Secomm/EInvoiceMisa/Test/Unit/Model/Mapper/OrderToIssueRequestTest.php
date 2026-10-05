<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Mapper;

use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceCore\Model\Data\IssueRequest;
use Secomm\EInvoiceCore\Model\Data\IssueRequestFactory;
use Secomm\EInvoiceMisa\Model\Config\InvoiceAmountFormatConfig;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;
use Secomm\EInvoiceMisa\Model\Mapper\OrderInvoiceAdjustments;
use Secomm\EInvoiceMisa\Model\Mapper\OrderToIssueRequest;
use Secomm\EInvoiceMisa\Model\Mapper\SalesInvoiceContext;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplate;

/**
 * Unit tests for MISA order-to-request mapper.
 */
class OrderToIssueRequestTest extends TestCase
{
    private OrderToIssueRequest $mapper;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $factory = $this->createMock(IssueRequestFactory::class);
        $factory->method('create')->willReturn(new IssueRequest());
        $misaConfig = $this->createMisaConfigMock();
        $misaConfig->method('isSendEmailOnIssue')->willReturn(false);
        $salesContext = new SalesInvoiceContext($this->createCurrencyFactory());
        $store = $this->createMock(Store::class);
        $store->method('getCode')->willReturn('default');
        $store->method('getName')->willReturn('Main Website Store');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->mapper = new OrderToIssueRequest(
            $factory,
            $misaConfig,
            $salesContext,
            new OrderInvoiceAdjustments($salesContext, $misaConfig),
            $storeManager,
            $this->createAmountFormatConfigMock()
        );
    }

    /**
     * @return void
     */
    public function testMapBuildsInvoiceDataPayload(): void
    {
        $billing = $this->createMock(OrderAddressInterface::class);
        $billing->method('getEmail')->willReturn('buyer@example.com');
        $billing->method('getTelephone')->willReturn('0900000000');
        $billing->method('getStreet')->willReturn(['123 Street']);
        $billing->method('getCity')->willReturn('HCM');
        $billing->method('getRegion')->willReturn('South');

        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getSku')->willReturn('SKU-1');
        $item->method('getName')->willReturn('Product A');
        $item->method('getQtyOrdered')->willReturn(2.0);
        $item->method('getPrice')->willReturn(100.0);
        $item->method('getRowTotal')->willReturn(200.0);
        $item->method('getTaxAmount')->willReturn(20.0);
        $item->method('getTaxPercent')->willReturn(10.0);

        $order = $this->createOrderWithCurrency('VND', 'VND');
        $order->method('getEntityId')->willReturn('42');
        $order->method('getIncrementId')->willReturn('100000042');
        $order->method('getStoreId')->willReturn('1');
        $order->method('getGrandTotal')->willReturn(220.0);
        $order->method('getShippingAmount')->willReturn(0.0);
        $order->method('getShippingTaxAmount')->willReturn(0.0);
        $order->method('getDiscountAmount')->willReturn(0.0);
        $order->method('getCreatedAt')->willReturn('2026-05-22 10:00:00');
        $order->method('getCustomerEmail')->willReturn('buyer@example.com');
        $order->method('getBillingAddress')->willReturn($billing);
        $order->method('getAllVisibleItems')->willReturn([$item]);
        $order->method('getData')->willReturn(null);
        $order->method('getPayment')->willReturn($this->createOrderPayment('checkmo', 'Check / Money order'));

        $template = new InvoiceTemplate('0b8a317d-3404-461f-873c-0b50e1dda56e', '1C26THP', 'Mau 01', false);

        $request = $this->mapper->map($order, $template);

        self::assertSame(42, $request->getOrderId());
        self::assertSame('100000042', $request->getOrderIncrementId());
        self::assertSame('1C26THP', $request->getPayload()['InvSeries']);
        self::assertSame('0b8a317d-3404-461f-873c-0b50e1dda56e', $request->getPayload()['InvoiceTemplateID']);
        self::assertSame(220.0, $request->getPayload()['TotalAmountOC']);
        self::assertSame(220.0, $request->getPayload()['TotalAmount']);
        self::assertSame(0, $request->getPayload()['DiscountRate']);
        self::assertArrayHasKey('InvoiceDetail', $request->getPayload());
        self::assertCount(1, $request->getPayload()['OriginalInvoiceDetail']);
        self::assertCount(1, $request->getPayload()['InvoiceDetail']);
        self::assertSame('10%', $request->getPayload()['TaxRateInfo'][0]['VATRateName']);
        self::assertSame('VND', $request->getPayload()['CurrencyCode']);
        self::assertSame(1.0, $request->getPayload()['ExchangeRate']);
        self::assertSame('Check / Money order', $request->getPayload()['PaymentMethodName']);
        self::assertSame('VND', $request->getPayload()['OptionUserDefined']['MainCurrency']);
        self::assertIsArray($request->getPayload()['FeeInfo']);
        self::assertSame(
            $this->expectedRefId('1', '100000042'),
            $request->getPayload()['RefID']
        );
        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
            $request->getPayload()['RefID']
        );
        self::assertFalse($request->getPayload()['IsSendEmail']);
    }

    /**
     * @return void
     */
    public function testMapConvertsUsdOrderAmountsToVnd(): void
    {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getSku')->willReturn('SKU-1');
        $item->method('getName')->willReturn('Product A');
        $item->method('getQtyOrdered')->willReturn(2.0);
        $item->method('getRowTotal')->willReturn(200.0);
        $item->method('getTaxAmount')->willReturn(20.0);
        $item->method('getTaxPercent')->willReturn(10.0);

        $order = $this->createOrderWithCurrency('USD', 'USD');
        $order->method('getEntityId')->willReturn('99');
        $order->method('getIncrementId')->willReturn('100000099');
        $order->method('getStoreId')->willReturn('1');
        $order->method('getCreatedAt')->willReturn('2026-06-01 10:00:00');
        $order->method('getCustomerEmail')->willReturn('buyer@example.com');
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getAllVisibleItems')->willReturn([$item]);
        $order->method('getPayment')->willReturn(null);
        $order->method('getGrandTotal')->willReturn(220.0);
        $order->method('getShippingAmount')->willReturn(0.0);
        $order->method('getShippingTaxAmount')->willReturn(0.0);
        $order->method('getDiscountAmount')->willReturn(0.0);

        $misaConfig = $this->createMisaConfigMock();
        $salesContext = new SalesInvoiceContext($this->createCurrencyFactory(25400.0));
        $mapper = $this->createMapper($misaConfig, $salesContext);

        $request = $mapper->map($order, new InvoiceTemplate('tid', '1C26THP'));

        self::assertSame('USD', $request->getPayload()['CurrencyCode']);
        self::assertSame(25400.0, $request->getPayload()['ExchangeRate']);
        self::assertSame(220.0, $request->getPayload()['TotalAmountOC']);
        self::assertSame(5588000.0, $request->getPayload()['TotalAmount']);
        self::assertSame(100.0, $request->getPayload()['InvoiceDetail'][0]['UnitPrice']);
        self::assertSame(5080000.0, $request->getPayload()['InvoiceDetail'][0]['Amount']);
        self::assertSame(508000.0, $request->getPayload()['InvoiceDetail'][0]['VATAmount']);
        self::assertSame(200.0, $request->getPayload()['InvoiceDetail'][0]['AmountOC']);
    }

    /**
     * @return void
     */
    public function testMapSetsIsSendEmailWhenConfigEnabled(): void
    {
        $billing = $this->createMock(OrderAddressInterface::class);
        $billing->method('getEmail')->willReturn('buyer@example.com');
        $billing->method('getFirstname')->willReturn('Test');
        $billing->method('getLastname')->willReturn('User');
        $billing->method('getStreet')->willReturn([]);
        $billing->method('getCity')->willReturn('');
        $billing->method('getRegion')->willReturn('');

        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getSku')->willReturn('SKU-1');
        $item->method('getName')->willReturn('Product');
        $item->method('getQtyOrdered')->willReturn(1.0);
        $item->method('getRowTotal')->willReturn(100.0);
        $item->method('getTaxAmount')->willReturn(0.0);
        $item->method('getTaxPercent')->willReturn(0.0);

        $order = $this->createOrderWithCurrency('VND', 'VND');
        $order->method('getEntityId')->willReturn('1');
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getStoreId')->willReturn('1');
        $order->method('getCreatedAt')->willReturn('2026-06-01 10:00:00');
        $order->method('getCustomerEmail')->willReturn('buyer@example.com');
        $order->method('getBillingAddress')->willReturn($billing);
        $order->method('getAllVisibleItems')->willReturn([$item]);
        $order->method('getPayment')->willReturn($this->createOrderPayment('checkmo', 'Check / Money order'));
        $order->method('getGrandTotal')->willReturn(100.0);
        $order->method('getShippingAmount')->willReturn(0.0);
        $order->method('getShippingTaxAmount')->willReturn(0.0);
        $order->method('getDiscountAmount')->willReturn(0.0);

        $misaConfig = $this->createMisaConfigMock();
        $misaConfig->method('isSendEmailOnIssue')->with(1)->willReturn(true);

        $salesContext = new SalesInvoiceContext($this->createCurrencyFactory());
        $mapper = $this->createMapper($misaConfig, $salesContext);

        $request = $mapper->map($order, new InvoiceTemplate('tid', '1C26THP'));

        self::assertTrue($request->getPayload()['IsSendEmail']);
        self::assertSame('buyer@example.com', $request->getPayload()['ReceiverEmail']);
    }

    /**
     * @return void
     */
    public function testMapIncludesShippingDiscountAndMatchesGrandTotal(): void
    {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getSku')->willReturn('SKU-1');
        $item->method('getName')->willReturn('Product A');
        $item->method('getQtyOrdered')->willReturn(1.0);
        $item->method('getRowTotal')->willReturn(100.0);
        $item->method('getTaxAmount')->willReturn(10.0);
        $item->method('getTaxPercent')->willReturn(10.0);

        $order = $this->createOrderWithCurrency('VND', 'VND');
        $order->method('getEntityId')->willReturn('50');
        $order->method('getIncrementId')->willReturn('100000050');
        $order->method('getStoreId')->willReturn('1');
        $order->method('getGrandTotal')->willReturn(115.0);
        $order->method('getShippingAmount')->willReturn(10.0);
        $order->method('getShippingTaxAmount')->willReturn(1.0);
        $order->method('getDiscountAmount')->willReturn(-5.0);
        $order->method('getCreatedAt')->willReturn('2026-06-03 10:00:00');
        $order->method('getCustomerEmail')->willReturn('buyer@example.com');
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getAllVisibleItems')->willReturn([$item]);
        $order->method('getPayment')->willReturn(null);

        $request = $this->mapper->map($order, new InvoiceTemplate('tid', '1C26THP'));

        self::assertSame(115.0, $request->getPayload()['TotalAmountOC']);
        self::assertSame(5.0, $request->getPayload()['TotalDiscountAmountOC']);
        self::assertCount(2, $request->getPayload()['InvoiceDetail']);
        self::assertSame('SHIPPING', $request->getPayload()['InvoiceDetail'][1]['ItemCode']);
        self::assertSame('Phí vận chuyển', $request->getPayload()['InvoiceDetail'][1]['ItemName']);
    }

    /**
     * Non-tax shipping (KCT) appears on InvoiceDetail but not in TaxRateInfo.
     */
    public function testMapOmitsKctShippingFromTaxRateInfo(): void
    {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getSku')->willReturn('SKU-1');
        $item->method('getName')->willReturn('LOOK');
        $item->method('getQtyOrdered')->willReturn(1.0);
        $item->method('getRowTotal')->willReturn(12974000.0);
        $item->method('getTaxAmount')->willReturn(1297400.0);
        $item->method('getTaxPercent')->willReturn(10.0);

        $order = $this->createOrderWithCurrency('VND', 'VND');
        $order->method('getEntityId')->willReturn('51');
        $order->method('getIncrementId')->willReturn('100000051');
        $order->method('getStoreId')->willReturn('1');
        $order->method('getGrandTotal')->willReturn(14401400.0);
        $order->method('getShippingAmount')->willReturn(130000.0);
        $order->method('getShippingTaxAmount')->willReturn(0.0);
        $order->method('getDiscountAmount')->willReturn(0.0);
        $order->method('getCreatedAt')->willReturn('2026-06-09 10:00:00');
        $order->method('getCustomerEmail')->willReturn('buyer@example.com');
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getAllVisibleItems')->willReturn([$item]);
        $order->method('getPayment')->willReturn(null);

        $request = $this->mapper->map($order, new InvoiceTemplate('tid', '1C26THP'));
        $payload = $request->getPayload();

        self::assertCount(1, $payload['TaxRateInfo']);
        self::assertSame('10%', $payload['TaxRateInfo'][0]['VATRateName']);
        self::assertSame(12974000.0, $payload['TaxRateInfo'][0]['AmountWithoutVATOC']);
        self::assertCount(2, $payload['InvoiceDetail']);
        self::assertSame('SHIPPING', $payload['InvoiceDetail'][1]['ItemCode']);
        self::assertSame('KCT', $payload['InvoiceDetail'][1]['VATRateName']);
        self::assertSame(13104000.0, $payload['TotalAmountWithoutVATOC']);
        self::assertSame(14401400.0, $payload['TotalAmountOC']);
    }

    /**
     * @param string $orderCurrency
     * @param string $baseCurrency
     * @return Order&MockObject
     */
    private function createOrderWithCurrency(string $orderCurrency, string $baseCurrency): Order
    {
        /** @var Order&MockObject $order */
        $order = $this->createMock(Order::class);
        $order->method('getOrderCurrencyCode')->willReturn($orderCurrency);
        $order->method('getBaseCurrencyCode')->willReturn($baseCurrency);

        return $order;
    }

    /**
     * @param float $rateToVnd
     * @return CurrencyFactory&MockObject
     */
    private function createCurrencyFactory(float $rateToVnd = 1.0): CurrencyFactory
    {
        /** @var Currency&MockObject $currency */
        $currency = $this->createMock(Currency::class);
        $currency->method('load')->willReturnSelf();
        $currency->method('getAnyRate')->with('VND')->willReturn($rateToVnd);

        /** @var CurrencyFactory&MockObject $factory */
        $factory = $this->createMock(CurrencyFactory::class);
        $factory->method('create')->willReturn($currency);

        return $factory;
    }

    private function createMapper(MisaConfig $misaConfig, SalesInvoiceContext $salesContext): OrderToIssueRequest
    {
        $factory = $this->createMock(IssueRequestFactory::class);
        $factory->method('create')->willReturn(new IssueRequest());
        $store = $this->createMock(Store::class);
        $store->method('getCode')->willReturn('default');
        $store->method('getName')->willReturn('Main Website Store');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new OrderToIssueRequest(
            $factory,
            $misaConfig,
            $salesContext,
            new OrderInvoiceAdjustments($salesContext, $misaConfig),
            $storeManager,
            $this->createAmountFormatConfigMock()
        );
    }

    private function createAmountFormatConfigMock(): InvoiceAmountFormatConfig&MockObject
    {
        $config = $this->createMock(InvoiceAmountFormatConfig::class);
        $config->method('getOptionUserDefined')->willReturn([
            'MainCurrency' => 'VND',
            'AmountDecimalDigits' => '0',
            'AmountOCDecimalDigits' => '0',
            'UnitPriceOCDecimalDigits' => '2',
            'UnitPriceDecimalDigits' => '2',
            'QuantityDecimalDigits' => '2',
            'CoefficientDecimalDigits' => '2',
            'ExchangRateDecimalDigits' => '0',
            'ClockDecimalDigits' => '2',
        ]);

        return $config;
    }

    private function createMisaConfigMock(): MisaConfig&MockObject
    {
        $misaConfig = $this->createMock(MisaConfig::class);
        $misaConfig->method('getShippingLineName')->willReturn(MisaConfig::DEFAULT_SHIPPING_LINE_NAME);

        return $misaConfig;
    }

    /**
     * @param string $methodCode
     * @param string $title
     * @return Payment&MockObject
     */
    private function createOrderPayment(string $methodCode, string $title): Payment
    {
        $method = $this->createMock(AbstractMethod::class);
        $method->method('getTitle')->willReturn($title);

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($methodCode);
        $payment->method('getMethodInstance')->willReturn($method);

        return $payment;
    }

    /**
     * @param string $storeId
     * @param string $incrementId
     * @return string
     */
    private function expectedRefId(string $storeId, string $incrementId): string
    {
        $seed = sprintf('secomm-einvoice:%s:%s:%d', $storeId, $incrementId, 0);
        $hex = substr(hash('sha256', $seed), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
