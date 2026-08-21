<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Test\Integration;

/**
 * Group C — multiple rules (ticket matrix C): two capped rules independent,
 * capped + uncapped, stop_rules_processing, coupon-based, automatic.
 */
class QuoteMultiRuleTest extends AbstractCapTestCase
{
    public function testTwoCappedRulesAreIndependent(): void
    {
        // R1 on items 1+2 (cap 300k, natives 400k+600k), R2 on item 2 only
        // (cap 100k, native 600k) -> each rule lands on its own cap
        $p1 = $this->makeProduct('C1a', 2000000.0);
        $p2 = $this->makeProduct('C1b', 3000000.0);
        $r1 = $this->makeRule('C1 r1', 'by_percent', 20.0, 300000.0, [$p1->getSku(), $p2->getSku()]);
        $r2 = $this->makeRule('C1 r2', 'by_percent', 20.0, 100000.0, [$p2->getSku()]);
        $quote = $this->makeQuote([$p1, $p2]);

        $this->recalc($quote);
        $precision = $this->precision($quote);
        $items = $this->visibleItems($quote);
        $breakdown = $this->addressBreakdown($quote);

        $this->assertAmountEquals(300000.0, $breakdown[(int)$r1->getId()]['base'], $precision, 'R1 == own cap');
        $this->assertAmountEquals(100000.0, $breakdown[(int)$r2->getId()]['base'], $precision, 'R2 == own cap');
        $this->assertAmountEquals(120000.0, (float)$items[$p1->getSku()]->getBaseDiscountAmount(), $precision);
        $this->assertAmountEquals(
            280000.0,
            (float)$items[$p2->getSku()]->getBaseDiscountAmount(),
            $precision,
            '180k (R1) + 100k (R2)'
        );
    }

    public function testCappedPlusUncappedRulesTogether(): void
    {
        $p1 = $this->makeProduct('C2a', 2000000.0);
        $p2 = $this->makeProduct('C2b', 3000000.0);
        $p3 = $this->makeProduct('C2c', 400000.0);
        $capped = $this->makeRule('C2 capped', 'by_percent', 20.0, 300000.0, [$p1->getSku(), $p2->getSku()]);
        $fixed = $this->makeRule('C2 fixed', 'by_fixed', 10000.0, null, [$p3->getSku()]);
        $quote = $this->makeQuote([$p1, $p2, $p3]);

        $this->recalc($quote);
        $precision = $this->precision($quote);
        $breakdown = $this->addressBreakdown($quote);

        $this->assertAmountEquals(300000.0, $breakdown[(int)$capped->getId()]['base'], $precision, 'capped == cap');
        $this->assertAmountEquals(10000.0, $breakdown[(int)$fixed->getId()]['base'], $precision, 'uncapped native kept');
        $this->assertAmountEquals(-310000.0, (float)$quote->getShippingAddress()->getBaseDiscountAmount(), $precision);
    }

    public function testStopRulesProcessingStopsLaterRules(): void
    {
        $p1 = $this->makeProduct('C3a', 2000000.0);
        // R1 stops further rules; created first (lower rule_id, same sort order)
        $r1 = $this->makeRule('C3 r1 stop', 'by_percent', 10.0, 100000.0, [], true);
        $r2 = $this->makeRule('C3 r2 later', 'by_percent', 10.0, null);
        $quote = $this->makeQuote([$p1]);

        $this->recalc($quote);
        $breakdown = $this->addressBreakdown($quote);

        $this->assertArrayHasKey((int)$r1->getId(), $breakdown);
        $this->assertArrayNotHasKey((int)$r2->getId(), $breakdown, 'stop_rules_processing blocks later rule');
    }

    public function testCouponRuleCapAppliesAfterCouponApplied(): void
    {
        $p1 = $this->makeProduct('C4a', 3000000.0);
        $code = 'IT-CAP-' . uniqid();
        $rule = $this->makeRule('C4 coupon', 'by_percent', 20.0, 250000.0, [], false, $code);
        $quote = $this->makeQuote([$p1]);

        // no coupon -> no discount
        $this->recalc($quote);
        $this->assertSame([], $this->addressBreakdown($quote), 'rule dormant without coupon');

        $quote->setCouponCode($code);
        $this->recalc($quote);
        $precision = $this->precision($quote);
        $items = $this->visibleItems($quote);

        $this->assertAmountEquals(250000.0, (float)$items[$p1->getSku()]->getBaseDiscountAmount(), $precision, 'cap after coupon');
        $this->assertAmountEquals(250000.0, $this->addressBreakdown($quote)[(int)$rule->getId()]['base'], $precision);
    }
}
