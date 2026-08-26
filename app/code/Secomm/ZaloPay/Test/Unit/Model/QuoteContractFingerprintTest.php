<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Model;

use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Model\QuoteContractFingerprint;

/**
 * BLOCKER 1: the quote payment-contract fingerprint must change for every
 * contract-relevant edit — even when the paid total stays the same — and
 * must stay stable for an unchanged quote.
 */
class QuoteContractFingerprintTest extends TestCase
{
    /**
     * @return void
     */
    public function testUnchangedQuoteProducesStableFingerprint(): void
    {
        $calculator = new QuoteContractFingerprint();

        $first = $calculator->calculate($this->newQuote(), 100000);
        $second = $calculator->calculate($this->newQuote(), 100000);

        $this->assertSame($first, $second);
        $this->assertTrue($calculator->matches($first, $second));
        $this->assertSame(64, strlen($first));
    }

    /**
     * Review case 3: qty changed, total coincidentally the same.
     *
     * @return void
     */
    public function testQuantityChangeChangesFingerprint(): void
    {
        $calculator = new QuoteContractFingerprint();

        $one = $calculator->calculate($this->newQuote(['sku' => 'SKU-1', 'product_id' => 11, 'qty' => 1.0]), 100000);
        $two = $calculator->calculate($this->newQuote(['sku' => 'SKU-1', 'product_id' => 11, 'qty' => 2.0]), 100000);

        $this->assertNotSame($one, $two);
        $this->assertFalse($calculator->matches($one, $two));
    }

    /**
     * Review case 4: items/products swapped, same total.
     *
     * @return void
     */
    public function testItemSwapWithSameTotalChangesFingerprint(): void
    {
        $calculator = new QuoteContractFingerprint();

        $a = $calculator->calculate($this->newQuote(['sku' => 'SKU-A', 'product_id' => 11, 'qty' => 1.0]), 100000);
        $b = $calculator->calculate($this->newQuote(['sku' => 'SKU-B', 'product_id' => 22, 'qty' => 1.0]), 100000);

        $this->assertNotSame($a, $b);
    }

    /**
     * Review case 5: shipping method changed with the same total.
     *
     * @return void
     */
    public function testShippingMethodChangeChangesFingerprint(): void
    {
        $calculator = new QuoteContractFingerprint();

        $flat = $calculator->calculate($this->newQuote(item: null, shippingMethod: 'flatrate_flatrate'), 100000);
        $free = $calculator->calculate(
            $this->newQuote(item: null, shippingMethod: 'freeshipping_freeshipping'),
            100000
        );

        $this->assertNotSame($flat, $free);
    }

    /**
     * Shipping address content is part of the contract (hash, not raw PII).
     *
     * @return void
     */
    public function testShippingAddressChangeChangesFingerprint(): void
    {
        $calculator = new QuoteContractFingerprint();

        $hanoi = $calculator->calculate($this->newQuote(item: null, city: 'Ha Noi'), 100000);
        $hcmc = $calculator->calculate($this->newQuote(item: null, city: 'Ho Chi Minh'), 100000);

        $this->assertNotSame($hanoi, $hcmc);
    }

    /**
     * Coupon/discount state is part of the contract.
     *
     * @return void
     */
    public function testCouponChangeChangesFingerprint(): void
    {
        $calculator = new QuoteContractFingerprint();

        $withCoupon = $calculator->calculate($this->newQuote(item: null, couponCode: 'SAVE10'), 100000);
        $without = $calculator->calculate($this->newQuote(item: null), 100000);

        $this->assertNotSame($withCoupon, $without);
    }

    /**
     * Item display order must not matter (deterministic normalization).
     *
     * @return void
     */
    public function testItemOrderDoesNotChangeFingerprint(): void
    {
        $calculator = new QuoteContractFingerprint();
        $a = $this->newItem('SKU-A', 11, 1.0);
        $b = $this->newItem('SKU-B', 22, 2.0);

        $forward = $calculator->calculate($this->newQuote(items: [$a, $b]), 100000);
        $reverse = $calculator->calculate($this->newQuote(items: [$b, $a]), 100000);

        $this->assertSame($forward, $reverse);
    }

    /**
     * The payable VND amount is part of the contract.
     *
     * @return void
     */
    public function testAmountChangeChangesFingerprint(): void
    {
        $calculator = new QuoteContractFingerprint();

        $this->assertNotSame(
            $calculator->calculate($this->newQuote(), 100000),
            $calculator->calculate($this->newQuote(), 200000)
        );
    }

    /**
     * Virtual quote (no addresses yet): both address hashes null, stable.
     *
     * @return void
     */
    public function testVirtualQuoteWithoutAddressesIsStable(): void
    {
        $calculator = new QuoteContractFingerprint();

        $first = $calculator->calculate($this->newQuote(item: null, virtual: true), 100000);
        $second = $calculator->calculate($this->newQuote(item: null, virtual: true), 100000);

        $this->assertSame($first, $second);
    }

    /**
     * matches() is null/empty-safe (legacy attempts without a hash never
     * compare equal).
     *
     * @return void
     */
    public function testMatchesRejectsMissingPersistedHash(): void
    {
        $calculator = new QuoteContractFingerprint();
        $current = $calculator->calculate($this->newQuote(), 100000);

        $this->assertFalse($calculator->matches(null, $current));
        $this->assertFalse($calculator->matches('', $current));
        $this->assertTrue($calculator->matches($current, $current));
    }

    /**
     * @param array|null $item Override for the single default item.
     * @param array|null $items Explicit item list (wins over $item).
     * @param string|null $shippingMethod
     * @param string|null $city
     * @param string|null $couponCode
     * @param bool $virtual No addresses at all.
     * @return \Secomm\ZaloPay\Test\Unit\Model\QuoteStub|\PHPUnit\Framework\MockObject\MockObject
     */
    private function newQuote(
        ?array $item = ['sku' => 'SKU-DEFAULT', 'product_id' => 1, 'qty' => 1.0],
        ?array $items = null,
        ?string $shippingMethod = 'flatrate_flatrate',
        ?string $city = 'Ha Noi',
        ?string $couponCode = null,
        bool $virtual = false
    ) {
        $quote = $this->createMock(QuoteStub::class);
        $item ??= ['sku' => 'SKU-DEFAULT', 'product_id' => 1, 'qty' => 1.0];
        $quote->method('getId')->willReturn(42);
        $quote->method('getReservedOrderId')->willReturn('000000123');
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getQuoteCurrencyCode')->willReturn('VND');
        $quote->method('getGrandTotal')->willReturn(100.0);
        $quote->method('getBaseGrandTotal')->willReturn(100.0);
        $quote->method('getCouponCode')->willReturn($couponCode);
        $quote->method('getAppliedRuleIds')->willReturn(null);
        $quote->method('getAllItems')->willReturn(
            $items ?? [$this->newItem($item['sku'], $item['product_id'], $item['qty'])]
        );
        $quote->method('getShippingAddress')->willReturn($virtual ? null : $this->newAddress($city, $shippingMethod));
        $quote->method('getBillingAddress')->willReturn($virtual ? null : $this->newAddress($city, null));

        return $quote;
    }

    /**
     * @param string $sku
     * @param int $productId
     * @param float $qty
     * @return \Secomm\ZaloPay\Test\Unit\Model\ItemStub|\PHPUnit\Framework\MockObject\MockObject
     */
    private function newItem(string $sku, int $productId, float $qty)
    {
        $item = $this->createMock(ItemStub::class);
        $item->method('getSku')->willReturn($sku);
        $item->method('getProductId')->willReturn($productId);
        $item->method('getQty')->willReturn($qty);

        return $item;
    }

    /**
     * @param string $city
     * @param string|null $shippingMethod
     * @return Address|\PHPUnit\Framework\MockObject\MockObject
     */
    private function newAddress(string $city, ?string $shippingMethod): Address
    {
        $address = $this->createMock(Address::class);
        $address->method('getId')->willReturn(1);
        $address->method('getCountryId')->willReturn('VN');
        $address->method('getShippingMethod')->willReturn($shippingMethod);
        $address->method('getData')->willReturnCallback(
            function ($field) use ($city) {
                return $field === 'city' ? $city : ($field === 'country_id' ? 'VN' : null);
            }
        );

        return $address;
    }
}
