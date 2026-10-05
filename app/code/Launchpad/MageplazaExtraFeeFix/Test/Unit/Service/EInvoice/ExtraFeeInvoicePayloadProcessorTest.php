<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Test\Unit\Service\EInvoice;

use Launchpad\MageplazaExtraFeeFix\Service\EInvoice\ExtraFeeInvoicePayloadProcessor;
use Launchpad\MageplazaExtraFeeFix\Service\ExtraFeeOrderHelper;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceMisa\Model\Mapper\SalesInvoiceContext;

class ExtraFeeInvoicePayloadProcessorTest extends TestCase
{
    private ExtraFeeInvoicePayloadProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new ExtraFeeInvoicePayloadProcessor(
            new ExtraFeeOrderHelper(),
            new SalesInvoiceContext($this->createMock(CurrencyFactory::class))
        );
    }

    // -----------------------------------------------------------------------
    // Order flow
    // -----------------------------------------------------------------------

    public function testOrderFeeBecomesNamedLineAndRoundingDiffIsAbsorbed(): void
    {
        // Payload shape of issue log #131 (order 000000114): two lines, the
        // 20k insurance fee folded into the generic "Điều chỉnh" FeeInfo entry.
        $payload = $this->createOrderPayload();
        $request = $this->createRequestMock($payload);
        $order = $this->createSalesEntityMock(Order::class, $this->orderFeeJson());

        $this->processor->processForOrder($request, $order);
        $result = $request->getPayload();

        $this->assertCount(3, $result['InvoiceDetail']);
        $feeLine = $result['InvoiceDetail'][2];
        $this->assertSame('EXTRA_FEE_RULE_1', $feeLine['ItemCode']);
        $this->assertSame('Phí bảo hiểm hàng hóa', $feeLine['ItemName']);
        $this->assertSame(3, $feeLine['LineNumber']);
        $this->assertSame(20000.0, $feeLine['AmountOC']);
        $this->assertSame('KCT', $feeLine['VATRateName']);

        // Fee promoted out of FeeInfo; headers keep matching the order grand total.
        $this->assertSame([], $result['FeeInfo']);
        $this->assertSame(7305000.0, $result['TotalAmountOC']);
        $this->assertSame(7305000.0, $result['TotalAmountWithoutVATOC']);
        $this->assertSame(7305000.0, $result['TotalSaleAmountOC']);

        // TaxRateInfo follows the lines.
        $this->assertSame(7305000.0, $result['TaxRateInfo'][0]['AmountWithoutVATOC']);

        // OriginalInvoiceDetail is kept in sync.
        $this->assertSame($result['InvoiceDetail'], $result['OriginalInvoiceDetail']);

        // Secomm's InvoicePayloadTotalsValidator contract: lines - discount + fees == header.
        $lineExcl = 0.0;
        foreach ($result['InvoiceDetail'] as $line) {
            $lineExcl += (float) $line['AmountWithoutVATOC'];
        }
        $feeOc = 0.0;
        foreach ($result['FeeInfo'] as $fee) {
            $feeOc += (float) $fee['FeeAmountOC'];
        }
        $this->assertEqualsWithDelta(
            $result['TotalAmountWithoutVATOC'],
            $lineExcl - (float) $result['TotalDiscountAmountOC'] + $feeOc,
            0.01
        );
    }

    public function testOrderProcessingIsIdempotent(): void
    {
        $payload = $this->createOrderPayload();
        $request = $this->createRequestMock($payload);
        $order = $this->createSalesEntityMock(Order::class, $this->orderFeeJson());

        $this->processor->processForOrder($request, $order);
        $once = $request->getPayload();
        $this->processor->processForOrder($request, $order);
        $twice = $request->getPayload();

        $this->assertSame($once, $twice);
        $this->assertCount(3, $twice['InvoiceDetail']);
    }

    public function testOrderWithoutFeesIsUntouched(): void
    {
        $payload = $this->createOrderPayload();
        $original = $payload;
        $request = $this->createRequestMock($payload);
        $order = $this->createSalesEntityMock(Order::class, '');

        $this->processor->processForOrder($request, $order);

        $this->assertSame($original, $request->getPayload());
    }

    public function testRoundingDiffLargerThanFeeKeepsDriftInFeeInfo(): void
    {
        // Diff = fee (20000) + 5000 real drift: the fee is promoted to a line,
        // the 5000 drift stays as the remaining "Điều chỉnh" entry.
        $payload = $this->createOrderPayload();
        $payload['FeeInfo'][0]['FeeAmountOC'] = 25000.0;
        $payload['FeeInfo'][0]['FeeAmount'] = 25000.0;
        $request = $this->createRequestMock($payload);
        $order = $this->createSalesEntityMock(Order::class, $this->orderFeeJson());

        $this->processor->processForOrder($request, $order);
        $result = $request->getPayload();

        $this->assertCount(3, $result['InvoiceDetail']);
        $this->assertCount(1, $result['FeeInfo']);
        $this->assertSame(5000.0, (float) $result['FeeInfo'][0]['FeeAmountOC']);
        $this->assertSame(7305000.0, $result['TotalAmountWithoutVATOC']);
    }

    // -----------------------------------------------------------------------
    // Credit memo flow
    // -----------------------------------------------------------------------

    public function testCreditmemoFeeAddsLineAndBumpsHeaders(): void
    {
        $payload = $this->createOrderPayload();
        unset($payload['FeeInfo']); // credit memo mapper patches sub-cent drift only
        $payload['TotalSaleAmountOC'] = 7285000.0;
        $payload['TotalAmountWithoutVATOC'] = 7285000.0;
        $payload['TotalAmountOC'] = 7285000.0;
        $request = $this->createRequestMock($payload);
        $creditmemo = $this->createSalesEntityMock(Creditmemo::class, $this->orderFeeJson());

        $this->processor->processForCreditmemo($request, $creditmemo);
        $result = $request->getPayload();

        $this->assertCount(3, $result['InvoiceDetail']);
        $this->assertSame('EXTRA_FEE_RULE_1', $result['InvoiceDetail'][2]['ItemCode']);
        $this->assertSame(7305000.0, $result['TotalAmountWithoutVATOC']);
        $this->assertSame(7305000.0, $result['TotalAmountOC']);
        $this->assertSame(7305000.0, $result['TotalSaleAmountOC']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function createOrderPayload(): array
    {
        $lines = [
            [
                'ItemType' => 1, 'LineNumber' => 1, 'SortOrder' => 1, 'ItemCode' => 'throw-chunky-knit',
                'ItemName' => 'Norrland Throw', 'UnitName' => 'Cái', 'Quantity' => 2.0,
                'UnitPrice' => 3625000.0, 'AmountOC' => 7250000.0, 'Amount' => 7250000.0,
                'AmountWithoutVATOC' => 7250000.0, 'AmountWithoutVAT' => 7250000.0,
                'VATRateName' => 'KCT', 'VATAmountOC' => 0.0, 'VATAmount' => 0.0,
            ],
            [
                'ItemType' => 1, 'LineNumber' => 2, 'SortOrder' => 2, 'ItemCode' => 'SHIPPING',
                'ItemName' => 'Phí vận chuyển config', 'UnitName' => 'Lần', 'Quantity' => 1.0,
                'UnitPrice' => 35000.0, 'AmountOC' => 35000.0, 'Amount' => 35000.0,
                'AmountWithoutVATOC' => 35000.0, 'AmountWithoutVAT' => 35000.0,
                'VATRateName' => 'KCT', 'VATAmountOC' => 0.0, 'VATAmount' => 0.0,
            ],
        ];

        return [
            'RefID' => 'test-ref', 'ExchangeRate' => 1.0, 'CurrencyCode' => 'VND',
            'TotalSaleAmountOC' => 7305000.0, 'TotalSaleAmount' => 7305000.0,
            'TotalAmountWithoutVATOC' => 7305000.0, 'TotalAmountWithoutVAT' => 7305000.0,
            'TotalDiscountAmountOC' => 0.0, 'TotalDiscountAmount' => 0.0,
            'TotalVATAmountOC' => 0.0, 'TotalVATAmount' => 0.0,
            'TotalAmountOC' => 7305000.0, 'TotalAmount' => 7305000.0,
            'TaxRateInfo' => [
                ['VATRateName' => 'KCT', 'AmountWithoutVATOC' => 7285000.0, 'VATAmountOC' => 0.0,
                 'AmountWithoutVAT' => 7285000.0, 'VATAmount' => 0.0],
            ],
            'InvoiceDetail' => $lines,
            'OriginalInvoiceDetail' => $lines,
            'FeeInfo' => [
                ['FeeName' => 'Điều chỉnh', 'FeeAmountOC' => 20000.0, 'FeeAmount' => 20000.0],
            ],
        ];
    }

    private function orderFeeJson(): string
    {
        return json_encode([
            'totals' => [
                ['code' => 'mp_extra_fee_rule_1_auto', 'title' => 'Phí bảo hiểm hàng hóa',
                 'value_excl_tax' => 20000, 'value_incl_tax' => 20000, 'rf' => '0'],
            ],
        ]);
    }

    private function createRequestMock(array &$payload): IssueRequestInterface&MockObject
    {
        $request = $this->createMock(IssueRequestInterface::class);
        $request->method('getPayload')->willReturnCallback(
            function () use (&$payload): array {
                return $payload;
            }
        );
        $request->method('setPayload')->willReturnCallback(
            function (array $new) use (&$payload, &$request): IssueRequestInterface {
                $payload = $new;

                return $request;
            }
        );

        return $request;
    }

    /**
     * @return Order&MockObject|Creditmemo&MockObject
     */
    private function createSalesEntityMock(string $entityClass, mixed $mpExtraFee): MockObject
    {
        $entity = $this->createMock($entityClass);
        $entity->method('getData')->with('mp_extra_fee')->willReturn($mpExtraFee);

        return $entity;
    }
}
