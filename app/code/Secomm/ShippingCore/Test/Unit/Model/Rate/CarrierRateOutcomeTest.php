<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Rate;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRate;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;

/**
 * TASK-NAT3YV — rate outcome VO: three-state semantics, impossible combinations unconstructible,
 * isSuccessful hard-guard, reason normalization.
 */
class CarrierRateOutcomeTest extends TestCase
{
    private CarrierRate $rate;

    protected function setUp(): void
    {
        $this->rate = new CarrierRate(40000.0, 'VND');
    }

    public function testSuccessFactoryCarriesRateWithoutReason(): void
    {
        $outcome = CarrierRateOutcome::success($this->rate);

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_SUCCESS, $outcome->getStatus());
        $this->assertSame($this->rate, $outcome->getRate());
        $this->assertNull($outcome->getFailureReason());
        $this->assertTrue($outcome->isSuccessful());
    }

    public function testUnavailableFactoryWithoutAndWithReason(): void
    {
        // unavailable(null) is structurally valid and still never contributes to fallback
        // eligibility — gating keys on STATUS, not on reason presence.
        $silent = CarrierRateOutcome::unavailable();
        $reasoned = CarrierRateOutcome::unavailable(ShippingFailureReason::PROVIDER_MAPPING_MISSING);

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $silent->getStatus());
        $this->assertNull($silent->getRate());
        $this->assertNull($silent->getFailureReason());
        $this->assertFalse($silent->isSuccessful());

        $this->assertSame(ShippingFailureReason::PROVIDER_MAPPING_MISSING, $reasoned->getFailureReason());
        $this->assertFalse($reasoned->isSuccessful());
    }

    public function testTechnicalFailureFactoryWithSharedReason(): void
    {
        $outcome = CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR);

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
        $this->assertNull($outcome->getRate());
        $this->assertSame(ShippingFailureReason::TECHNICAL_ERROR, $outcome->getFailureReason());
        $this->assertFalse($outcome->isSuccessful());
    }

    public function testEmptyReasonNormalizesToNull(): void
    {
        $outcome = CarrierRateOutcome::unavailable('   ');

        $this->assertNull($outcome->getFailureReason());
        $this->addToAssertionCount(1);
    }

    public function testConstructorKeepsParityWithFactories(): void
    {
        $outcome = new CarrierRateOutcome(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, null, 'GHTK_SERVICE_REJECTED');

        $this->assertSame('GHTK_SERVICE_REJECTED', $outcome->getFailureReason());
        $this->addToAssertionCount(1);
    }

    public function testRejectsSuccessWithoutRate(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must carry a rate');
        new CarrierRateOutcome(CarrierRateOutcomeInterface::STATUS_SUCCESS, null, null);
    }

    public function testRejectsSuccessWithReason(): void
    {
        $this->expectException(\LogicException::class);
        new CarrierRateOutcome(
            CarrierRateOutcomeInterface::STATUS_SUCCESS,
            $this->rate,
            ShippingFailureReason::TECHNICAL_ERROR
        );
    }

    public function testRejectsUnavailableWithRate(): void
    {
        $this->expectException(\LogicException::class);
        new CarrierRateOutcome(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $this->rate, null);
    }

    public function testRejectsTechnicalFailureWithRate(): void
    {
        $this->expectException(\LogicException::class);
        new CarrierRateOutcome(CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE, $this->rate, null);
    }

    public function testRejectsUnknownStatus(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown carrier rate outcome status');
        new CarrierRateOutcome('BUSINESS_REJECTION', null, null);
    }

    public function testStatusRemainsAuthoritativeOverDiagnosticReason(): void
    {
        // r1 orchestration rule: reasons are diagnostic; a caller may construct UNAVAILABLE with
        // any reason string — the outcome is still UNAVAILABLE (never fallback-triggering).
        $outcome = CarrierRateOutcome::unavailable(ShippingFailureReason::TECHNICAL_ERROR);

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertFalse($outcome->isSuccessful());
        $this->assertSame(ShippingFailureReason::TECHNICAL_ERROR, $outcome->getFailureReason());
    }
}
