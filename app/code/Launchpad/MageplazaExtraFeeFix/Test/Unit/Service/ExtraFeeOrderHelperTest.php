<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Test\Unit\Service;

use Launchpad\MageplazaExtraFeeFix\Service\ExtraFeeOrderHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExtraFeeOrderHelperTest extends TestCase
{
    private ExtraFeeOrderHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new ExtraFeeOrderHelper();
    }

    // -----------------------------------------------------------------------
    // hasNonRefundableExtraFee
    // -----------------------------------------------------------------------

    public function testHasNonRefundableExtraFeeReturnsFalseWhenEmpty(): void
    {
        $order = $this->createOrderMock('');
        $this->assertFalse($this->helper->hasNonRefundableExtraFee($order));
    }

    public function testHasNonRefundableExtraFeeReturnsFalseWhenNull(): void
    {
        $order = $this->createOrderMock(null);
        $this->assertFalse($this->helper->hasNonRefundableExtraFee($order));
    }

    public function testHasNonRefundableExtraFeeReturnsFalseWhenInvalidJson(): void
    {
        $order = $this->createOrderMock('not-valid-json');
        $this->assertFalse($this->helper->hasNonRefundableExtraFee($order));
    }

    public function testHasNonRefundableExtraFeeReturnsFalseWhenNoTotals(): void
    {
        $order = $this->createOrderMock(json_encode(['totals' => []]));
        $this->assertFalse($this->helper->hasNonRefundableExtraFee($order));
    }

    public function testHasNonRefundableExtraFeeReturnsTrueForRfZero(): void
    {
        $json = json_encode([
            'totals' => [
                ['code' => 'mp_extra_fee_rule_1_auto', 'rf' => 0, 'value' => 10000],
            ]
        ]);
        $order = $this->createOrderMock($json);
        $this->assertTrue($this->helper->hasNonRefundableExtraFee($order));
    }

    public function testHasNonRefundableExtraFeeReturnsFalseWhenAllRefundable(): void
    {
        $json = json_encode([
            'totals' => [
                ['code' => 'mp_extra_fee_rule_1_auto', 'rf' => 1, 'value' => 10000],
                ['code' => 'mp_extra_fee_rule_2_auto', 'rf' => 1, 'value' => 5000],
            ]
        ]);
        $order = $this->createOrderMock($json);
        $this->assertFalse($this->helper->hasNonRefundableExtraFee($order));
    }

    public function testHasNonRefundableExtraFeeReturnsTrueWhenMixed(): void
    {
        // One refundable (rf=1) + one non-refundable (rf=0)
        $json = json_encode([
            'totals' => [
                ['code' => 'mp_extra_fee_rule_1_auto', 'rf' => 1, 'value' => 5000],
                ['code' => 'mp_extra_fee_rule_2_auto', 'rf' => 0, 'value' => 10000],
            ]
        ]);
        $order = $this->createOrderMock($json);
        $this->assertTrue($this->helper->hasNonRefundableExtraFee($order));
    }

    public function testHasNonRefundableExtraFeeAcceptsAlreadyDecodedArray(): void
    {
        $decoded = [
            'totals' => [
                ['code' => 'mp_extra_fee_rule_1_auto', 'rf' => 0, 'value' => 10000],
            ]
        ];
        $order = $this->createOrderMock($decoded);
        $this->assertTrue($this->helper->hasNonRefundableExtraFee($order));
    }

    // -----------------------------------------------------------------------
    // areAllItemsRefundedOrCanceled
    // -----------------------------------------------------------------------

    public function testAreAllItemsReturnsFalseWhenNoItems(): void
    {
        $order = $this->createOrderWithItems([]);
        $this->assertFalse($this->helper->areAllItemsRefundedOrCanceled($order));
    }

    public function testAreAllItemsReturnsTrueWhenAllRefunded(): void
    {
        $order = $this->createOrderWithItems([
            $this->createItem(false, 2.0, 2.0, 0.0),
        ]);
        $this->assertTrue($this->helper->areAllItemsRefundedOrCanceled($order));
    }

    public function testAreAllItemsReturnsTrueWhenAllCanceled(): void
    {
        $order = $this->createOrderWithItems([
            $this->createItem(false, 3.0, 0.0, 3.0),
        ]);
        $this->assertTrue($this->helper->areAllItemsRefundedOrCanceled($order));
    }

    public function testAreAllItemsReturnsTrueWhenMixRefundedAndCanceled(): void
    {
        $order = $this->createOrderWithItems([
            $this->createItem(false, 2.0, 1.0, 1.0),
        ]);
        $this->assertTrue($this->helper->areAllItemsRefundedOrCanceled($order));
    }

    public function testAreAllItemsReturnsFalseWhenPartiallyRefunded(): void
    {
        $order = $this->createOrderWithItems([
            $this->createItem(false, 3.0, 1.0, 0.0),
        ]);
        $this->assertFalse($this->helper->areAllItemsRefundedOrCanceled($order));
    }

    public function testAreAllItemsSkipsDummyItems(): void
    {
        // Dummy (configurable parent) is skipped; real child is fully refunded
        $order = $this->createOrderWithItems([
            $this->createItem(true,  1.0, 0.0, 0.0),
            $this->createItem(false, 1.0, 1.0, 0.0),
        ]);
        $this->assertTrue($this->helper->areAllItemsRefundedOrCanceled($order));
    }

    public function testAreAllItemsReturnsTrueWhenOnlyDummyItems(): void
    {
        // No real items to fail on → loop completes → returns true
        $order = $this->createOrderWithItems([
            $this->createItem(true, 1.0, 0.0, 0.0),
        ]);
        $this->assertTrue($this->helper->areAllItemsRefundedOrCanceled($order));
    }

    public function testAreAllItemsHandlesFloatEpsilon(): void
    {
        // Gap of ~0.0000001 is below epsilon 0.0001 → treated as fully refunded
        $order = $this->createOrderWithItems([
            $this->createItem(false, 1.0000001, 1.0, 0.0),
        ]);
        $this->assertTrue($this->helper->areAllItemsRefundedOrCanceled($order));
    }

    // -----------------------------------------------------------------------
    // getFeeTotals
    // -----------------------------------------------------------------------

    public function testGetFeeTotalsParsesRealOrderFixture(): void
    {
        // Real sales_order.mp_extra_fee JSON of order #131 (rule 1, insurance fee)
        $json = '{"summary":"","totals":[{"code":"mp_extra_fee_rule_1_auto","title":"Phí bảo hiểm hàng hóa",'
            . '"label":"Phí bảo hiểm hàng hóa","value":20000,"value_excl_tax":20000,"value_incl_tax":20000,'
            . '"rf":"0","display_area":"3","apply_type":"1","percent":0}],"is_invoiced":"29"}';
        $order = $this->createOrderMock($json);

        $this->assertSame([
            [
                'code' => 'mp_extra_fee_rule_1_auto',
                'title' => 'Phí bảo hiểm hàng hóa',
                'amount_excl_tax' => 20000.0,
                'tax_amount' => 0.0,
                'refundable' => false,
            ],
        ], $this->helper->getFeeTotals($order));
    }

    public function testGetFeeTotalsComputesTaxAndRefundable(): void
    {
        $json = json_encode([
            'totals' => [
                ['code' => 'mp_extra_fee_rule_2_auto', 'title' => 'Gift wrap', 'value_excl_tax' => 9000,
                 'value_incl_tax' => 9900, 'rf' => 1],
            ]
        ]);
        $order = $this->createOrderMock($json);

        $fees = $this->helper->getFeeTotals($order);
        $this->assertCount(1, $fees);
        $this->assertSame(9000.0, $fees[0]['amount_excl_tax']);
        $this->assertSame(900.0, $fees[0]['tax_amount']);
        $this->assertTrue($fees[0]['refundable']);
    }

    public function testGetFeeTotalsSkipsZeroAndNonArrayEntries(): void
    {
        $json = json_encode([
            'totals' => [
                'garbage-string',
                ['code' => 'mp_extra_fee_rule_3_auto', 'title' => 'Zero', 'value' => 0, 'rf' => 1],
            ]
        ]);
        $order = $this->createOrderMock($json);

        $this->assertSame([], $this->helper->getFeeTotals($order));
    }

    public function testGetFeeTotalsReturnsEmptyWhenNoColumnData(): void
    {
        $this->assertSame([], $this->helper->getFeeTotals($this->createOrderMock('')));
        $this->assertSame([], $this->helper->getFeeTotals($this->createOrderMock('not-valid-json')));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createOrderMock(mixed $mpExtraFee): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getData')->with('mp_extra_fee')->willReturn($mpExtraFee);
        return $order;
    }

    private function createOrderWithItems(array $items): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getAllItems')->willReturn($items);
        return $order;
    }

    private function createItem(bool $isDummy, float $ordered, float $refunded, float $canceled): Item&MockObject
    {
        $item = $this->createMock(Item::class);
        $item->method('isDummy')->willReturn($isDummy);
        $item->method('getQtyOrdered')->willReturn($ordered);
        $item->method('getQtyRefunded')->willReturn($refunded);
        $item->method('getQtyCanceled')->willReturn($canceled);
        return $item;
    }
}
