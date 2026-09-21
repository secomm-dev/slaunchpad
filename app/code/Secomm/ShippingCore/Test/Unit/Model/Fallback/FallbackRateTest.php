<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Fallback;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Fallback\FallbackRate;

/**
 * TASK-XXBN5X r2 — fallback rate VO invariants (amount >= 0: zero valid, negative rejected).
 */
class FallbackRateTest extends TestCase
{
    public function testTransportsAmountLabelAndEstimate(): void
    {
        $rate = new FallbackRate(40000.0, 'Standard Delivery', '2 - 4 day(s)');

        $this->assertSame(40000.0, $rate->getAmount());
        $this->assertSame('Standard Delivery', $rate->getLabel());
        $this->assertSame('2 - 4 day(s)', $rate->getDeliveryEstimate());
    }

    public function testZeroAmountIsValidExplicitRate(): void
    {
        // r2: zero is a valid explicit configured rate — "no fallback rate" is null, never 0.
        $rate = new FallbackRate(0.0, 'Free Fallback');

        $this->assertSame(0.0, $rate->getAmount());
        $this->assertSame('Free Fallback', $rate->getLabel());
    }

    public function testDeliveryEstimateIsOptional(): void
    {
        $rate = new FallbackRate(40000.0, 'Standard Delivery');

        $this->assertNull($rate->getDeliveryEstimate());
        $this->addToAssertionCount(1);
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must not be negative');
        new FallbackRate(-1.0, 'Standard Delivery');
    }

    public function testRejectsEmptyLabel(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('non-empty service-level label');
        new FallbackRate(40000.0, '   ');
    }
}
