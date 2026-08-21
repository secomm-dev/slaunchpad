<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Test\Integration;

/**
 * Group B — multiple items (ticket matrix B): proportional contribution
 * (not row_total), mixed eligibility, qty variation.
 */
class QuoteMultiItemTest extends AbstractCapTestCase
{
    public function testProportionalByContributionNotRowTotal(): void
    {
        // spec AC-004: natives 400k + 600k, cap 500k -> 200k/300k
        $p1 = $this->makeProduct('B1a', 2000000.0);
        $p2 = $this->makeProduct('B1b', 3000000.0);
        $this->makeRule('B1 percent', 'by_percent', 20.0, 500000.0);
        $quote = $this->makeQuote([$p1, $p2]);

        $this->recalc($quote);
        $precision = $this->precision($quote);
        $items = $this->visibleItems($quote);

        $this->assertAmountEquals(200000.0, (float)$items[$p1->getSku()]->getBaseDiscountAmount(), $precision);
        $this->assertAmountEquals(300000.0, (float)$items[$p2->getSku()]->getBaseDiscountAmount(), $precision);
        $this->assertAmountEquals(-500000.0, (float)$quote->getShippingAddress()->getBaseDiscountAmount(), $precision);
    }

    public function testIneligibleItemIsNotScaled(): void
    {
        // rule restricted to items 1+2; item 3 carries no breakdown entry —
        // it must stay at zero discount while the cap scales only 1+2
        $p1 = $this->makeProduct('B2a', 2000000.0);
        $p2 = $this->makeProduct('B2b', 3000000.0);
        $p3 = $this->makeProduct('B2c', 400000.0);
        $this->makeRule('B2 percent', 'by_percent', 20.0, 300000.0, [$p1->getSku(), $p2->getSku()]);
        $quote = $this->makeQuote([$p1, $p2, $p3]);

        $this->recalc($quote);
        $precision = $this->precision($quote);
        $items = $this->visibleItems($quote);

        $this->assertAmountEquals(120000.0, (float)$items[$p1->getSku()]->getBaseDiscountAmount(), $precision, '400k x factor');
        $this->assertAmountEquals(180000.0, (float)$items[$p2->getSku()]->getBaseDiscountAmount(), $precision, '600k x factor');
        $this->assertAmountEquals(0.0, (float)$items[$p3->getSku()]->getBaseDiscountAmount(), $precision, 'ineligible untouched');
    }

    public function testQtyVariationKeepsInvariant(): void
    {
        // qty 2 on item 1: native 800k + 600k = 1400k, cap 700k -> factor 0.5
        $p1 = $this->makeProduct('B3a', 2000000.0);
        $p2 = $this->makeProduct('B3b', 3000000.0);
        $this->makeRule('B3 percent', 'by_percent', 20.0, 700000.0);
        $quote = $this->makeQuote([$p1, $p2], [$p1->getSku() => 2]);

        $this->recalc($quote);
        $precision = $this->precision($quote);
        $items = $this->visibleItems($quote);

        $this->assertAmountEquals(400000.0, (float)$items[$p1->getSku()]->getBaseDiscountAmount(), $precision);
        $this->assertAmountEquals(300000.0, (float)$items[$p2->getSku()]->getBaseDiscountAmount(), $precision);
        $this->assertAmountEquals(
            700000.0,
            (float)$items[$p1->getSku()]->getBaseDiscountAmount() + (float)$items[$p2->getSku()]->getBaseDiscountAmount(),
            $precision,
            'Σ final eligible == cap'
        );
    }

    /**
     * Configurable (children-calculated) — the riskiest native path per the
     * ticket: the per-rule breakdown lives on the parent item while the item
     * discount amounts are distributed to the children.
     *
     * TASK-4HYX6Y COVERAGE GAP (AC-6, escalated to TL — not silently skipped):
     * programmatic composite fixtures stay NOT salable under MSI despite
     * child source item + reindexList + full reindexAll + repository reload
     * (sample-data configurables carry cataloginventory_stock_status rows;
     * our programmatic one does not, and IsSalableWithReservationsCondition
     * returns false for the parent). Composite fixture-infra needs its own
     * investigation (MSI composite indexing path for created-at-runtime
     * products); the collector logic for parent items is covered by unit
     * tests (getParentItem filter + parent breakdown reads). Bundle
     * dynamic-price shares the same gap.
     */
    public function testConfigurableChildrenCalculatedPath(): void
    {
        $this->markTestSkipped(
            'TASK-4HYX6Y gap: composite (configurable/bundle) MSI salability fixture not achievable '
            . 'engine-level so far (source item + reindexList + reindexAll tried) — needs dedicated '
            . 'fixture investigation; unit-level parent-item logic is covered. See ticket + evidence.'
        );
    }

}
