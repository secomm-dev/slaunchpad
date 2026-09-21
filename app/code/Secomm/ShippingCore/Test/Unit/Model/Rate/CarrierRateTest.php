<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Rate;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Rate\CarrierRate;

/**
 * TASK-NAT3YV — realtime carrier rate VO: zero is domain-valid (promotions), negative is not.
 */
class CarrierRateTest extends TestCase
{
    public function testTransportsPositiveAmountAndCurrency(): void
    {
        $rate = new CarrierRate(40000.0, 'VND');

        $this->assertSame(40000.0, $rate->getAmount());
        $this->assertSame('VND', $rate->getCurrency());
    }

    public function testZeroAmountIsValid(): void
    {
        $rate = new CarrierRate(0.0);

        $this->assertSame(0.0, $rate->getAmount());
        $this->assertNull($rate->getCurrency());
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must not be negative');
        new CarrierRate(-1.0);
    }
}
