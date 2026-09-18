<?php
/**
 * Unit test for the quote payment-contract fingerprint (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Model;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Model\QuoteContractFingerprint;

/**
 * Verifies the fingerprint: 64-hex, deterministic for an unchanged quote,
 * sensitive to amount/item changes, insensitive to cart display order, and
 * the match() null/empty semantics.
 */
class QuoteContractFingerprintTest extends TestCase
{
    /**
     * The fingerprint is 64-char hex and deterministic.
     *
     * @return void
     */
    public function testCalculateIsDeterministicHex(): void
    {
        $fingerprint = new QuoteContractFingerprint();
        $hash1 = $fingerprint->calculate($this->quote(), 150000);
        $hash2 = $fingerprint->calculate($this->quote(), 150000);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash1);
        $this->assertSame($hash1, $hash2);
    }

    /**
     * A different VND amount produces a different contract.
     *
     * @return void
     */
    public function testAmountChangeChangesFingerprint(): void
    {
        $fingerprint = new QuoteContractFingerprint();

        $this->assertNotSame(
            $fingerprint->calculate($this->quote(), 150000),
            $fingerprint->calculate($this->quote(), 160000)
        );
    }

    /**
     * An item qty change produces a different contract even at the same
     * total amount — the amount snapshot alone cannot prove the contract.
     *
     * @return void
     */
    public function testItemChangeChangesFingerprintAtSameAmount(): void
    {
        $fingerprint = new QuoteContractFingerprint();

        $this->assertNotSame(
            $fingerprint->calculate($this->quote([['SKU-A', 10, 1.0]]), 150000),
            $fingerprint->calculate($this->quote([['SKU-A', 10, 2.0]]), 150000)
        );
    }

    /**
     * Cart display order is irrelevant: the same item set hashes equal.
     *
     * @return void
     */
    public function testItemOrderDoesNotChangeFingerprint(): void
    {
        $fingerprint = new QuoteContractFingerprint();
        $items = [['SKU-A', 10, 1.0], ['SKU-B', 20, 3.0]];

        $this->assertSame(
            $fingerprint->calculate($this->quote($items), 150000),
            $fingerprint->calculate($this->quote(array_reverse($items)), 150000)
        );
    }

    /**
     * matches(): null/empty stored hashes never match (legacy rows), equal
     * hashes match, and mismatched hashes do not.
     *
     * @return void
     */
    public function testMatchesSemantics(): void
    {
        $fingerprint = new QuoteContractFingerprint();
        $hash = $fingerprint->calculate($this->quote(), 150000);

        $this->assertFalse($fingerprint->matches(null, $hash));
        $this->assertFalse($fingerprint->matches('', $hash));
        $this->assertTrue($fingerprint->matches($hash, $hash));
        $this->assertFalse($fingerprint->matches(str_repeat('a', 64), $hash));
    }

    /**
     * Quote mock: minimal contract surface (id, reserved id, store,
     * currency, items, addresses, payment, coupon, rules).
     *
     * @param array $itemDefinitions List of [sku, productId, qty].
     * @return Quote&\PHPUnit\Framework\MockObject\MockObject
     */
    private function quote(array $itemDefinitions = [['SKU-A', 10, 1.0]]): Quote
    {
        $items = [];
        foreach ($itemDefinitions as [$sku, $productId, $qty]) {
            $item = $this->getMockBuilder(Item::class)
                ->onlyMethods(['getSku', 'getQty', 'getChildren'])
                ->addMethods(['getProductId'])
                ->disableOriginalConstructor()
                ->getMock();
            $item->method('getSku')->willReturn($sku);
            $item->method('getProductId')->willReturn($productId);
            $item->method('getQty')->willReturn($qty);
            $item->method('getChildren')->willReturn([]);
            $items[] = $item;
        }

        $address = $this->getMockBuilder(Address::class)
            ->onlyMethods(['getId', 'getData', 'getShippingMethod'])
            ->addMethods(['getAppliedRuleIds'])
            ->disableOriginalConstructor()
            ->getMock();
        $address->method('getId')->willReturn(null);
        $address->method('getData')->willReturn([]);
        $address->method('getShippingMethod')->willReturn('flatrate_flatrate');
        $address->method('getAppliedRuleIds')->willReturn('');

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('momo_payment');

        $quote = $this->getMockBuilder(Quote::class)
            ->onlyMethods([
                'getAllItems',
                'getBillingAddress',
                'getId',
                'getPayment',
                'getReservedOrderId',
                'getShippingAddress',
                'getStoreId',
            ])
            ->addMethods(['getQuoteCurrencyCode', 'getCouponCode', 'getAppliedRuleIds'])
            ->disableOriginalConstructor()
            ->getMock();
        $quote->method('getId')->willReturn(42);
        $quote->method('getReservedOrderId')->willReturn('200000001');
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getQuoteCurrencyCode')->willReturn('VND');
        $quote->method('getAllItems')->willReturn($items);
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('getBillingAddress')->willReturn($address);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getCouponCode')->willReturn(null);
        $quote->method('getAppliedRuleIds')->willReturn('');

        return $quote;
    }
}
