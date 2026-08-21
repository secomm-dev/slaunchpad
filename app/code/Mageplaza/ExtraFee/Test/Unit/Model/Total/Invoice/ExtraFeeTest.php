<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

declare(strict_types=1);

namespace Mageplaza\ExtraFee\Test\Unit\Model\Total\Invoice;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Mageplaza\ExtraFee\Helper\Data as HelperData;
use Mageplaza\ExtraFee\Model\Total\Invoice\ExtraFee;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the invoice extra-fee total collector. It must add each fee to the
 * invoice grand totals, selecting incl-tax values when apply_type == 2.
 *
 * @covers \Mageplaza\ExtraFee\Model\Total\Invoice\ExtraFee
 */
class ExtraFeeTest extends TestCase
{
    /** @var HelperData|MockObject */
    private $helper;

    /** @var ExtraFee */
    private $model;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(HelperData::class);
        $this->model  = new ExtraFee($this->helper);
    }

    /**
     * Build an invoice mock whose grand totals are stateful, so accumulation across
     * multiple fees can be asserted (getGrandTotal()/setGrandTotal() are read+written
     * inside the collect() loop).
     *
     * @param float $grand
     * @param float $baseGrand
     * @param Order $order
     *
     * @return Invoice|MockObject
     */
    private function createStatefulInvoice(float $grand, float $baseGrand, Order $order)
    {
        $state   = ['grand' => $grand, 'base' => $baseGrand];
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getOrder')->willReturn($order);
        $invoice->method('getGrandTotal')->willReturnCallback(function () use (&$state) {
            return $state['grand'];
        });
        $invoice->method('getBaseGrandTotal')->willReturnCallback(function () use (&$state) {
            return $state['base'];
        });
        $invoice->method('setGrandTotal')->willReturnCallback(
            static function ($value) use (&$state, $invoice) {
                $state['grand'] = $value;

                return $invoice;
            }
        );
        $invoice->method('setBaseGrandTotal')->willReturnCallback(
            static function ($value) use (&$state, $invoice) {
                $state['base'] = $value;

                return $invoice;
            }
        );

        return $invoice;
    }

    /**
     * Happy path: apply_type 1 uses excl-tax value, apply_type 2 uses incl-tax value;
     * both accumulate onto the existing grand totals.
     */
    public function testCollectAddsExclAndInclTaxFeesToGrandTotals(): void
    {
        $order   = $this->createMock(Order::class);
        $invoice = $this->createStatefulInvoice(100.0, 90.0, $order);

        $this->helper->expects($this->once())
            ->method('getObjectExtraFeeTotals')
            ->with($invoice, $order)
            ->willReturn([
                ['apply_type' => 1, 'value' => 5.0, 'base_value' => 4.0,
                 'value_incl_tax' => 6.0, 'base_value_incl_tax' => 5.0],
                ['apply_type' => 2, 'value' => 10.0, 'base_value' => 8.0,
                 'value_incl_tax' => 11.0, 'base_value_incl_tax' => 9.0],
            ]);

        $this->assertSame($this->model, $this->model->collect($invoice));

        // grand: 100 + 5 (excl) + 11 (incl) = 116 ; base: 90 + 4 + 9 = 103
        $this->assertSame(116.0, $invoice->getGrandTotal());
        $this->assertSame(103.0, $invoice->getBaseGrandTotal());
    }

    /**
     * Edge case: no extra fees on the invoice leaves the grand totals unchanged.
     */
    public function testCollectLeavesTotalsUnchangedWhenNoFees(): void
    {
        $order   = $this->createMock(Order::class);
        $invoice = $this->createStatefulInvoice(100.0, 90.0, $order);

        $this->helper->method('getObjectExtraFeeTotals')->willReturn([]);

        $this->model->collect($invoice);

        $this->assertSame(100.0, $invoice->getGrandTotal());
        $this->assertSame(90.0, $invoice->getBaseGrandTotal());
    }
}
