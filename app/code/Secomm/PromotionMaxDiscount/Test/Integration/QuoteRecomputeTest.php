<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Test\Integration;

/**
 * Group D — config / recompute (ticket matrix D): idempotency ×3, item/qty/
 * coupon recompute, customer-group switch, two-precision chain.
 *
 * Coverage gaps (AC-6, not silently skipped):
 *  - tax incl/excl × catalog price incl/excl matrix: NOT covered engine-level
 *    (Option 2 runs on the shared dev DB — global tax config surgery is not
 *    repeat-safe); deferred to QC env. Unit-level precision behaviour is
 *    covered by LRM/collector unit suites.
 *  - real FX-rate display currency: the USD case below uses a synthetic rate-1
 *    parity quote currency to exercise the two-chain/two-precision path.
 */
class QuoteRecomputeTest extends AbstractCapTestCase
{
    public function testRepeatCollectIsIdempotent(): void
    {
        $p1 = $this->makeProduct('D1a', 2000000.0);
        $p2 = $this->makeProduct('D1b', 3000000.0);
        $this->makeRule('D1 percent', 'by_percent', 20.0, 500000.0);
        $quote = $this->makeQuote([$p1, $p2]);

        $snapshots = [];
        for ($i = 0; $i < 3; $i++) {
            $this->recalc($quote);
            $snapshots[] = json_encode([
                'items' => array_map(
                    static fn($item) => [$item->getBaseDiscountAmount(), $item->getDiscountAmount()],
                    $this->visibleItems($quote)
                ),
                'breakdown' => $this->addressBreakdown($quote),
                'total' => $quote->getShippingAddress()->getBaseDiscountAmount(),
            ]);
        }
        $this->assertCount(1, array_unique($snapshots), '3 consecutive collects identical');
        $this->assertAmountEquals(-500000.0, (float)$quote->getShippingAddress()->getBaseDiscountAmount(), $this->precision($quote));
    }

    public function testQtyChangeRecomputeKeepsInvariant(): void
    {
        $p1 = $this->makeProduct('D2a', 2000000.0);
        $this->makeRule('D2 percent', 'by_percent', 20.0, 300000.0);
        $quote = $this->makeQuote([$p1]);

        $this->recalc($quote);
        $item = $this->visibleItems($quote)[$p1->getSku()];
        $this->assertAmountEquals(300000.0, (float)$item->getBaseDiscountAmount(), 0, 'qty 1 capped');

        // qty 1 -> 3: native 1.2M, cap still 300k
        $item->setQty(3);
        $this->recalc($quote);
        $precision = $this->precision($quote);
        $item = $this->visibleItems($quote)[$p1->getSku()];
        $this->assertAmountEquals(300000.0, (float)$item->getBaseDiscountAmount(), $precision, 'qty 3 still == cap');
    }

    public function testRemoveItemRecomputeKeepsInvariant(): void
    {
        $p1 = $this->makeProduct('D3a', 2000000.0);
        $p2 = $this->makeProduct('D3b', 3000000.0);
        $this->makeRule('D3 percent', 'by_percent', 20.0, 400000.0);
        $quote = $this->makeQuote([$p1, $p2]);

        $this->recalc($quote);
        $this->assertAmountEquals(-400000.0, (float)$quote->getShippingAddress()->getBaseDiscountAmount(), 0);

        // remove item 2: native now 400k == cap -> no-op territory
        $quote->removeItem($this->visibleItems($quote)[$p2->getSku()]->getId());
        $this->recalc($quote);
        $precision = $this->precision($quote);
        $this->assertAmountEquals(-400000.0, (float)$quote->getShippingAddress()->getBaseDiscountAmount(), $precision, 'native == cap after removal');
    }

    public function testCouponRemoveRecompute(): void
    {
        $p1 = $this->makeProduct('D4a', 3000000.0);
        $code = 'IT-CAP-D4-' . uniqid();
        $this->makeRule('D4 coupon', 'by_percent', 20.0, 250000.0, [], false, $code);
        $quote = $this->makeQuote([$p1]);

        $quote->setCouponCode($code);
        $this->recalc($quote);
        $this->assertAmountEquals(250000.0, (float)$this->visibleItems($quote)[$p1->getSku()]->getBaseDiscountAmount(), 0, 'capped with coupon');

        $quote->setCouponCode(null);
        $this->recalc($quote);
        $this->assertAmountEquals(0.0, (float)$this->visibleItems($quote)[$p1->getSku()]->getBaseDiscountAmount(), 0, 'coupon removed -> native');
    }

    public function testCustomerGroupSwitchControlsRule(): void
    {
        $p1 = $this->makeProduct('D5a', 3000000.0);
        $store = $this->om(\Magento\Store\Model\StoreManagerInterface::class)->getStore();
        // rule visible to group 1 only (NOT logged in group 0)
        $rule = $this->om(\Magento\SalesRule\Model\RuleFactory::class)->create();
        $rule->setName('IT-CAP D5 group ' . uniqid())
            ->setIsActive(1)
            ->setCouponType(\Magento\SalesRule\Model\Rule::COUPON_TYPE_NO_COUPON)
            ->setWebsiteIds([$store->getWebsiteId()])
            ->setCustomerGroupIds([1])
            ->setSimpleAction('by_percent')
            ->setDiscountAmount(20.0)
            ->setMaximumDiscountAmount(250000.0);
        $rule->getResource()->save($rule);
        $this->rules[(int)$rule->getId()] = $rule;

        $quote = $this->makeQuote([$p1]);
        $this->recalc($quote);
        $this->assertSame([], $this->addressBreakdown($quote), 'group 0 not eligible');

        $quote->setCustomerGroupId(1);
        $this->recalc($quote);
        $this->assertAmountEquals(250000.0, (float)$this->visibleItems($quote)[$p1->getSku()]->getBaseDiscountAmount(), 0, 'group 1 capped');
    }

    public function testTwoPrecisionChainsVndBaseUsdDisplay(): void
    {
        // synthetic parity (rate 1): VND base grid 0, USD quote-currency grid 2 —
        // exercises the collector's per-chain currency-code precision (C2 fix)
        $p1 = $this->makeProduct('D6a', 2000000.0);
        $p2 = $this->makeProduct('D6b', 3000000.0);
        $this->makeRule('D6 percent', 'by_percent', 20.0, 500000.0);
        $quote = $this->makeQuote([$p1, $p2]);

        $quote->setData('quote_currency_code', 'USD'); // parity: base_to_quote_rate stays 1
        $this->recalc($quote);
        $items = $this->visibleItems($quote);

        // base chain: VND grid (integers), Σ == cap
        $baseSum = 0.0;
        foreach ($items as $item) {
            $v = (float)$item->getBaseDiscountAmount();
            $this->assertEqualsWithDelta(0.0, $v - round($v, 0), 1e-9, 'base on VND 0-grid');
            $baseSum += $v;
        }
        $this->assertAmountEquals(500000.0, $baseSum, 0, 'Σ base == cap');

        // display chain: USD grid (2 decimals), Σ == cap, each amount on grid
        $displaySum = 0.0;
        foreach ($items as $item) {
            $v = (float)$item->getDiscountAmount();
            $this->assertEqualsWithDelta(0.0, $v - round($v, 2), 1e-9, 'display on USD 2-grid');
            $displaySum += $v;
        }
        $this->assertAmountEquals(500000.0, $displaySum, 2, 'Σ display == cap (numeric space)');
    }
}
