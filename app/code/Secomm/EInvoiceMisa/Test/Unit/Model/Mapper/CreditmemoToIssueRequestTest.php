<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Mapper;

use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Creditmemo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceCore\Model\Data\IssueRequest;
use Secomm\EInvoiceCore\Model\Data\IssueRequestFactory;
use Secomm\EInvoiceMisa\Model\Config\InvoiceAmountFormatConfig;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;
use Secomm\EInvoiceMisa\Model\Mapper\CreditmemoToIssueRequest;
use Secomm\EInvoiceMisa\Model\Mapper\OrderInvoiceAdjustments;
use Secomm\EInvoiceMisa\Model\Mapper\SalesInvoiceContext;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplate;

/**
 * Unit tests for credit memo to MeInvoice adjustment mapper.
 */
class CreditmemoToIssueRequestTest extends TestCase
{
    /**
     * @return void
     */
    public function testMapBuildsAdjustmentPayloadWithOriginReference(): void
    {
        $item = $this->createMock(CreditmemoItemInterface::class);
        $item->method('getQty')->willReturn(1.0);
        $item->method('getSku')->willReturn('SKU-1');
        $item->method('getName')->willReturn('Product');
        $item->method('getRowTotal')->willReturn(50.0);
        $item->method('getTaxAmount')->willReturn(5.0);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn('10');
        $order->method('getIncrementId')->willReturn('100000010');
        $order->method('getOrderCurrencyCode')->willReturn('VND');
        $order->method('getBillingAddress')->willReturn(null);

        /** @var Creditmemo&MockObject $creditmemo */
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getItems')->willReturn([$item]);
        $creditmemo->method('getAllItems')->willReturn([$item]);
        $creditmemo->method('getOrder')->willReturn($order);
        $creditmemo->method('getStoreId')->willReturn('1');
        $creditmemo->method('getIncrementId')->willReturn('200000010');
        $creditmemo->method('getEntityId')->willReturn('5');
        $creditmemo->method('getCreatedAt')->willReturn('2026-06-03 12:00:00');
        $creditmemo->method('getOrderCurrencyCode')->willReturn('VND');
        $creditmemo->method('getShippingAmount')->willReturn(0.0);
        $creditmemo->method('getShippingTaxAmount')->willReturn(0.0);
        $creditmemo->method('getDiscountAmount')->willReturn(0.0);
        $creditmemo->method('getAdjustmentNegative')->willReturn(0.0);
        $creditmemo->method('getAdjustmentPositive')->willReturn(0.0);
        $creditmemo->method('getSubtotal')->willReturn(50.0);
        $creditmemo->method('getTaxAmount')->willReturn(5.0);
        $creditmemo->method('getGrandTotal')->willReturn(55.0);

        $factory = $this->createMock(IssueRequestFactory::class);
        $factory->method('create')->willReturn(new IssueRequest());
        $misaConfig = $this->createMock(MisaConfig::class);
        $misaConfig->method('isSendEmailOnIssue')->willReturn(false);
        $misaConfig->method('getShippingLineName')->willReturn(MisaConfig::DEFAULT_SHIPPING_LINE_NAME);
        $salesContext = new SalesInvoiceContext($this->createCurrencyFactory());
        $amountFormatConfig = $this->createMock(InvoiceAmountFormatConfig::class);
        $amountFormatConfig->method('getOptionUserDefined')->willReturn([
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
        $mapper = new CreditmemoToIssueRequest(
            $factory,
            $misaConfig,
            $salesContext,
            new OrderInvoiceAdjustments($salesContext, $misaConfig),
            $amountFormatConfig
        );

        $request = $mapper->map($creditmemo, new InvoiceTemplate('tid', '1C26THP'), [
            'transaction_id' => 'TX-ORIG',
            'ref_id' => 'ref-orig',
            'inv_series' => '1C26THP',
            'inv_date' => '2026-06-01',
        ]);

        self::assertSame(10, $request->getOrderId());
        self::assertSame(2, $request->getPayload()['ReferenceType']);
        self::assertSame('TX-ORIG', $request->getPayload()['OrgInvoiceTransactionID']);
        self::assertSame(55.0, $request->getPayload()['TotalAmountOC']);
        self::assertSame('10%', $request->getPayload()['TaxRateInfo'][0]['VATRateName']);
    }

    /**
     * Mirrors order 000000004: subtotal 34 + shipping 5 - adjustment fee 10 = grand 29.
     */
    public function testMapCreditMemoBreakdownMatchesGrandTotalWithoutFeeInfoPatch(): void
    {
        $item = $this->createMock(CreditmemoItemInterface::class);
        $item->method('getQty')->willReturn(1.0);
        $item->method('getSku')->willReturn('SKU-REFUND');
        $item->method('getName')->willReturn('Refunded product');
        $item->method('getRowTotal')->willReturn(34.0);
        $item->method('getTaxAmount')->willReturn(0.0);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn('4');
        $order->method('getIncrementId')->willReturn('000000004');
        $order->method('getOrderCurrencyCode')->willReturn('USD');
        $order->method('getBillingAddress')->willReturn(null);

        /** @var Creditmemo&MockObject $creditmemo */
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getAllItems')->willReturn([$item]);
        $creditmemo->method('getItems')->willReturn([$item]);
        $creditmemo->method('getOrder')->willReturn($order);
        $creditmemo->method('getStoreId')->willReturn('1');
        $creditmemo->method('getIncrementId')->willReturn('200000004');
        $creditmemo->method('getEntityId')->willReturn('9');
        $creditmemo->method('getCreatedAt')->willReturn('2026-06-10 12:00:00');
        $creditmemo->method('getOrderCurrencyCode')->willReturn('USD');
        $creditmemo->method('getShippingAmount')->willReturn(5.0);
        $creditmemo->method('getShippingTaxAmount')->willReturn(0.0);
        $creditmemo->method('getDiscountAmount')->willReturn(0.0);
        $creditmemo->method('getAdjustmentNegative')->willReturn(10.0);
        $creditmemo->method('getAdjustmentPositive')->willReturn(0.0);
        $creditmemo->method('getSubtotal')->willReturn(34.0);
        $creditmemo->method('getTaxAmount')->willReturn(0.0);
        $creditmemo->method('getGrandTotal')->willReturn(29.0);

        $factory = $this->createMock(IssueRequestFactory::class);
        $factory->method('create')->willReturn(new IssueRequest());
        $misaConfig = $this->createMock(MisaConfig::class);
        $misaConfig->method('isSendEmailOnIssue')->willReturn(false);
        $misaConfig->method('getShippingLineName')->willReturn('Phí vận chuyển');
        $salesContext = new SalesInvoiceContext($this->createCurrencyFactoryUsd());
        $amountFormatConfig = $this->createMock(InvoiceAmountFormatConfig::class);
        $amountFormatConfig->method('getOptionUserDefined')->willReturn([]);
        $mapper = new CreditmemoToIssueRequest(
            $factory,
            $misaConfig,
            $salesContext,
            new OrderInvoiceAdjustments($salesContext, $misaConfig),
            $amountFormatConfig
        );

        $request = $mapper->map($creditmemo, new InvoiceTemplate('tid', '2C26TYV'), [
            'transaction_id' => 'TX-ORIG',
            'ref_id' => 'ref-orig',
            'inv_series' => '2C26TYV',
            'inv_date' => '2026-06-10',
        ]);

        $payload = $request->getPayload();
        self::assertSame(29.0, $payload['TotalAmountOC']);
        self::assertSame([], $payload['FeeInfo']);
        self::assertCount(3, $payload['InvoiceDetail']);
        self::assertSame('SKU-REFUND', $payload['InvoiceDetail'][0]['ItemCode']);
        self::assertSame('SHIPPING', $payload['InvoiceDetail'][1]['ItemCode']);
        self::assertSame('ADJUSTMENT_FEE', $payload['InvoiceDetail'][2]['ItemCode']);
        self::assertSame(-10.0, $payload['InvoiceDetail'][2]['AmountOC']);

        $detailSum = 0.0;
        foreach ($payload['InvoiceDetail'] as $line) {
            $detailSum += (float) $line['AmountOC'] + (float) $line['VATAmountOC'];
        }
        self::assertEqualsWithDelta(29.0, $detailSum, 0.01);
    }

    /**
     * @return CurrencyFactory&MockObject
     */
    private function createCurrencyFactory(): CurrencyFactory
    {
        $currency = $this->createMock(Currency::class);
        $currency->method('load')->willReturnSelf();
        $currency->method('getAnyRate')->with('VND')->willReturn(1.0);

        $factory = $this->createMock(CurrencyFactory::class);
        $factory->method('create')->willReturn($currency);

        return $factory;
    }

    /**
     * @return CurrencyFactory&MockObject
     */
    private function createCurrencyFactoryUsd(): CurrencyFactory
    {
        $currency = $this->createMock(Currency::class);
        $currency->method('load')->willReturnSelf();
        $currency->method('getAnyRate')->with('USD')->willReturn(25400.0);

        $factory = $this->createMock(CurrencyFactory::class);
        $factory->method('create')->willReturn($currency);

        return $factory;
    }
}
