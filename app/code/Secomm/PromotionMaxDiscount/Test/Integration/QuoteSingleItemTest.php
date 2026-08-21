<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Test\Integration;

/**
 * Group A — single eligible item (ticket matrix A / spec §16):
 * discount < cap → identical native; discount > cap → final == cap.
 */
class QuoteSingleItemTest extends AbstractCapTestCase
{
    public function testNoOpWhenDiscountBelowCap(): void
    {
        $product = $this->makeProduct('A1', 2000000.0);
        $this->makeRule('A1 percent', 'by_percent', 20.0, 500000.0);
        $quote = $this->makeQuote([$product]);

        $this->recalc($quote);
        $precision = $this->precision($quote);

        $item = $this->visibleItems($quote)[$product->getSku()];
        $this->assertAmountEquals(400000.0, (float)$item->getBaseDiscountAmount(), $precision, 'native 20% kept');
        $this->assertAmountEquals(-400000.0, (float)$quote->getShippingAddress()->getBaseDiscountAmount(), $precision);
    }

    public function testCapLandsWhenDiscountExceedsCap(): void
    {
        $product = $this->makeProduct('A2', 3000000.0);
        $rule = $this->makeRule('A2 percent', 'by_percent', 20.0, 250000.0);
        $quote = $this->makeQuote([$product]);

        $this->recalc($quote);
        $precision = $this->precision($quote);

        $item = $this->visibleItems($quote)[$product->getSku()];
        $this->assertAmountEquals(250000.0, (float)$item->getBaseDiscountAmount(), $precision, 'base capped');
        $this->assertAmountEquals(250000.0, (float)$item->getDiscountAmount(), $precision, 'display capped');
        $address = $quote->getShippingAddress();
        $this->assertAmountEquals(-250000.0, (float)$address->getBaseDiscountAmount(), $precision);
        $this->assertAmountEquals(-250000.0, (float)$address->getDiscountAmount(), $precision);

        $breakdown = $this->addressBreakdown($quote);
        $this->assertArrayHasKey((int)$rule->getId(), $breakdown);
        $this->assertAmountEquals(250000.0, $breakdown[(int)$rule->getId()]['base'], $precision);
        $this->assertAmountEquals(250000.0, $breakdown[(int)$rule->getId()]['display'], $precision);
    }

    public function testCapNullMeansUnlimited(): void
    {
        $product = $this->makeProduct('A3', 3000000.0);
        $this->makeRule('A3 percent', 'by_percent', 20.0, null);
        $quote = $this->makeQuote([$product]);

        $this->recalc($quote);
        $precision = $this->precision($quote);

        $item = $this->visibleItems($quote)[$product->getSku()];
        $this->assertAmountEquals(600000.0, (float)$item->getBaseDiscountAmount(), $precision, 'NULL cap = native');
    }
}
