<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Rate;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Model\Fallback\FallbackEligibility;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRateExecutionDecision;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;

/**
 * TASK-8MQHJX (Phase C) — execution decision VO: composition of existing domain values and
 * the realtime/outcome consistency guard.
 */
class CarrierRateExecutionDecisionTest extends TestCase
{
    public function testDefaultsCarryNoRealtimeAndNoFallback(): void
    {
        $decision = new CarrierRateExecutionDecision(false);

        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertNull($decision->getRealtimeOutcome());
        $this->assertNull($decision->getCarrierFacingHandoff());
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertNull($decision->getReason());
    }

    public function testRealtimePathRequiresItsOutcome(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must carry its outcome');

        new CarrierRateExecutionDecision(true);
    }

    public function testOutcomeWithoutRealtimePathIsImpossible(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must not');

        new CarrierRateExecutionDecision(false, CarrierRateOutcome::unavailable('X'));
    }

    public function testFullRealtimeDecisionPreservesAllFields(): void
    {
        $outcome = CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR);
        $handoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $eligibility = FallbackEligibility::technical();

        $decision = new CarrierRateExecutionDecision(
            shouldInvokeRealtime: true,
            realtimeOutcome: $outcome,
            carrierFacingHandoff: $handoff,
            fallbackEligibility: $eligibility,
            reason: CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE
        );

        $this->assertTrue($decision->shouldInvokeRealtime());
        $this->assertSame($outcome, $decision->getRealtimeOutcome());
        $this->assertSame($handoff, $decision->getCarrierFacingHandoff());
        $this->assertSame($eligibility, $decision->getFallbackEligibility());
        $this->assertSame(CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE, $decision->getReason());
    }
}
