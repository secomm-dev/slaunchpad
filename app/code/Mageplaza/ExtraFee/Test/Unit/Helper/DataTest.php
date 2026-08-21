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

namespace Mageplaza\ExtraFee\Test\Unit\Helper;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\ObjectManagerInterface;
use Mageplaza\ExtraFee\Helper\Data;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the extra-fee Helper read logic: simple-product detection and the
 * order/invoice fee aggregation (getObjectExtraFeeTotals) that totals fees by code.
 *
 * @covers \Mageplaza\ExtraFee\Helper\Data
 */
class DataTest extends TestCase
{
    /** @var Data|MockObject */
    private $helper;

    protected function setUp(): void
    {
        // Helper extends Mageplaza\Core AbstractData (heavy constructor); disable it and
        // keep every real method under test.
        $this->helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        // Data::jsonDecode() is static and resolves a Json helper from the global ObjectManager.
        $jsonHelper = $this->createMock(JsonHelper::class);
        $jsonHelper->method('jsonDecode')->willReturnCallback(
            static fn($value) => json_decode((string) $value, true)
        );
        $globalOm = $this->createMock(ObjectManagerInterface::class);
        $globalOm->method('get')->with(JsonHelper::class)->willReturn($jsonHelper);
        ObjectManager::setInstance($globalOm);
    }

    /* ---------------------------------------------------------------------
     * isBundleOrConfig()
     * ------------------------------------------------------------------- */

    /**
     * Happy path: configurable and bundle parent items are flagged so their fee is not
     * double counted on top of the child simple products.
     */
    public function testIsBundleOrConfigTrueForConfigurableAndBundle(): void
    {
        $this->assertTrue($this->helper->isBundleOrConfig(new DataObject(['product_type' => 'configurable'])));
        $this->assertTrue($this->helper->isBundleOrConfig(new DataObject(['product_type' => 'bundle'])));
    }

    /**
     * Edge case: a simple (or any non-composite) product is not treated as bundle/config.
     */
    public function testIsBundleOrConfigFalseForSimpleProduct(): void
    {
        $this->assertFalse($this->helper->isBundleOrConfig(new DataObject(['product_type' => 'simple'])));
        $this->assertFalse($this->helper->isBundleOrConfig(new DataObject()));
    }

    /* ---------------------------------------------------------------------
     * getObjectExtraFeeTotals()
     * ------------------------------------------------------------------- */

    /**
     * Happy path: a fee decoded from a matching order item is multiplied by the invoiced
     * qty and keyed by its code.
     */
    public function testGetObjectExtraFeeTotalsMultipliesFeeByQty(): void
    {
        $feeJson = json_encode([[
            'code' => 'fee1', 'title' => 'Fee 1', 'label' => 'L1',
            'value' => 5, 'base_value' => 4, 'value_incl_tax' => 6,
            'value_excl_tax' => 5, 'base_value_incl_tax' => 5,
            'rf' => 0, 'display_area' => 3, 'apply_type' => 1, 'rule_label' => 'R1',
        ]]);

        $orderItem = new DataObject([
            'product_type'  => 'simple',
            'product_id'    => '10',
            'mp_extra_fee'  => $feeJson,
        ]);
        $order = new DataObject(['items' => [$orderItem]]);

        // invoice item with the same product, qty = 2
        $invoiceItem = new DataObject(['product_id' => '10', 'qty' => 2]);
        $invoice     = new DataObject(['items' => [$invoiceItem]]);

        $result = $this->helper->getObjectExtraFeeTotals($invoice, $order);

        $this->assertArrayHasKey('fee1', $result);
        $this->assertSame(10, $result['fee1']['value']);          // 5 * 2
        $this->assertSame(8, $result['fee1']['base_value']);      // 4 * 2
        $this->assertSame(12, $result['fee1']['value_incl_tax']); // 6 * 2
        $this->assertSame(1, $result['fee1']['apply_type']);
    }

    /**
     * Branch: two order items carrying the same fee code are summed together.
     */
    public function testGetObjectExtraFeeTotalsAggregatesSameCodeAcrossItems(): void
    {
        $makeFeeJson = static fn($value) => json_encode([[
            'code' => 'feeX', 'title' => 'X', 'label' => 'LX',
            'value' => $value, 'base_value' => $value, 'value_incl_tax' => $value,
            'value_excl_tax' => $value, 'base_value_incl_tax' => $value,
            'rf' => 0, 'display_area' => 3, 'apply_type' => 1, 'rule_label' => 'RX',
        ]]);

        $order = new DataObject(['items' => [
            new DataObject(['product_type' => 'simple', 'product_id' => '1', 'mp_extra_fee' => $makeFeeJson(5)]),
            new DataObject(['product_type' => 'simple', 'product_id' => '2', 'mp_extra_fee' => $makeFeeJson(7)]),
        ]]);
        $invoice = new DataObject(['items' => [
            new DataObject(['product_id' => '1', 'qty' => 1]),
            new DataObject(['product_id' => '2', 'qty' => 1]),
        ]]);

        $result = $this->helper->getObjectExtraFeeTotals($invoice, $order);

        $this->assertCount(1, $result);
        $this->assertSame(12, $result['feeX']['value']); // 5 + 7
    }

    /**
     * Edge case: configurable parent items are skipped, so no fee is collected from them.
     */
    public function testGetObjectExtraFeeTotalsSkipsConfigurableParents(): void
    {
        $order = new DataObject(['items' => [
            new DataObject(['product_type' => 'configurable', 'product_id' => '1', 'mp_extra_fee' => json_encode([['code' => 'c']])]),
        ]]);
        $invoice = new DataObject(['items' => [new DataObject(['product_id' => '1', 'qty' => 1])]]);

        $this->assertSame([], $this->helper->getObjectExtraFeeTotals($invoice, $order));
    }

    /**
     * Edge case: when no invoice item matches the order item product, nothing is totalled.
     */
    public function testGetObjectExtraFeeTotalsReturnsEmptyWhenNoProductMatch(): void
    {
        $order = new DataObject(['items' => [
            new DataObject(['product_type' => 'simple', 'product_id' => '10', 'mp_extra_fee' => json_encode([['code' => 'c']])]),
        ]]);
        $invoice = new DataObject(['items' => [new DataObject(['product_id' => '99', 'qty' => 1])]]);

        $this->assertSame([], $this->helper->getObjectExtraFeeTotals($invoice, $order));
    }
}
