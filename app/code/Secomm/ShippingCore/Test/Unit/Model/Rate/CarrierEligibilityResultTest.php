<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Rate;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityResultInterface;
use Secomm\ShippingCore\Model\Rate\CarrierEligibilityResult;

/**
 * TASK-8MQHJX (Phase B) — eligibility result VO: factory semantics + getter pass-through.
 */
class CarrierEligibilityResultTest extends TestCase
{
    public function testEligibleFactoryDefaults(): void
    {
        $result = CarrierEligibilityResult::eligible();

        $this->assertTrue($result->isEligible());
        $this->assertSame(CarrierEligibilityResultInterface::REASON_ELIGIBLE, $result->getReasonCode());
        $this->assertNull($result->getMatchedZoneCode());
    }

    public function testEligibleFactoryWithMatchedZone(): void
    {
        $result = CarrierEligibilityResult::eligible('HCM_INNER');

        $this->assertTrue($result->isEligible());
        $this->assertSame(CarrierEligibilityResultInterface::REASON_ELIGIBLE, $result->getReasonCode());
        $this->assertSame('HCM_INNER', $result->getMatchedZoneCode());
    }

    public function testIneligibleFactoryCarriesReasonCode(): void
    {
        $result = CarrierEligibilityResult::ineligible(
            CarrierEligibilityResultInterface::REASON_DESTINATION_NOT_IN_SCOPE
        );

        $this->assertFalse($result->isEligible());
        $this->assertSame(
            CarrierEligibilityResultInterface::REASON_DESTINATION_NOT_IN_SCOPE,
            $result->getReasonCode()
        );
        $this->assertNull($result->getMatchedZoneCode());
    }

    public function testExplicitConstructorPreservesAllFields(): void
    {
        $result = new CarrierEligibilityResult(false, 'CUSTOM_REASON', 'ZONE_X');

        $this->assertFalse($result->isEligible());
        $this->assertSame('CUSTOM_REASON', $result->getReasonCode());
        $this->assertSame('ZONE_X', $result->getMatchedZoneCode());
    }
}
