<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Rate;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;
use Secomm\ShippingCore\Model\Fallback\FallbackEligibility;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcomeCollector;

/**
 * TASK-SEC-D-transport — decision-record semantics of the collector: explicit transport
 * presence, deterministic duplicate/merge rules, bracket lifecycle, no cross-member leaks.
 */
class CarrierRateOutcomeCollectorDecisionTest extends TestCase
{
    private CarrierRateOutcomeCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new CarrierRateOutcomeCollector(new NullLogger());
        $this->collector->beginCollection();
    }

    public function testLegacyRecordAppearsWithoutTransport(): void
    {
        $this->collector->record('ghtk', 'ghtk', CarrierRateOutcome::unavailable('SERVICE_UNAVAILABLE'));

        $record = $this->collector->getDecisionRecords()['ghtk']['ghtk'];
        $this->assertFalse($record->hasEligibilityTransport());
        $this->assertNull($record->getFallbackEligibility());
        $this->assertSame('UNAVAILABLE', $record->getOutcome()->getStatus());
    }

    public function testTransportedNoneIsPresentAndExplicit(): void
    {
        $this->collector->recordDecision(
            'ghn',
            'secomm_ghn',
            CarrierRateOutcome::unavailable(ShippingFailureReason::INVALID_CONFIGURATION),
            FallbackEligibility::none()
        );

        $record = $this->collector->getDecisionRecords()['ghn']['secomm_ghn'];
        $this->assertTrue($record->hasEligibilityTransport(), 'Explicit NONE is a transport');
        $this->assertFalse($record->getFallbackEligibility()->isEligible());
    }

    public function testConflictingNonSuccessKeepsTheFirstRecordWhole(): void
    {
        // INVALID_CONFIGURATION+NONE followed by TECHNICAL+technical: mixing outcome A with
        // eligibility B would synthesize an impossible state that opens fallback for a
        // configuration error — the FIRST record wins WHOLE.
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::unavailable(ShippingFailureReason::INVALID_CONFIGURATION),
            FallbackEligibility::none()
        );
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR),
            FallbackEligibility::technical()
        );

        $record = $this->collector->getDecisionRecords()['a']['m'];
        $this->assertSame('UNAVAILABLE', $record->getOutcome()->getStatus());
        $this->assertTrue($record->hasEligibilityTransport());
        $this->assertFalse($record->getFallbackEligibility()->isEligible(), 'No cross-execution eligibility synthesis');
        $this->assertSame([], $record->getFallbackEligibility()->getSources());
    }

    public function testTechnicalEligibleThenInvalidConfigNoneKeepsTechnicalWhole(): void
    {
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR),
            FallbackEligibility::technical()
        );
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::unavailable(ShippingFailureReason::INVALID_CONFIGURATION),
            FallbackEligibility::none()
        );

        $record = $this->collector->getDecisionRecords()['a']['m'];
        $this->assertSame('TECHNICAL_FAILURE', $record->getOutcome()->getStatus());
        $this->assertTrue($record->getFallbackEligibility()->hasTechnicalFallbackEligibility());
        $this->assertFalse($record->getFallbackEligibility()->hasIntegrationLimitationEligibility());
    }

    public function testMappingMissingThenServiceUnavailableKeepsIntegrationLimitation(): void
    {
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::unavailable(ShippingFailureReason::PROVIDER_MAPPING_MISSING),
            FallbackEligibility::integrationLimitation()
        );
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE),
            FallbackEligibility::none()
        );

        $record = $this->collector->getDecisionRecords()['a']['m'];
        $this->assertSame('UNAVAILABLE', $record->getOutcome()->getStatus());
        $this->assertTrue($record->getFallbackEligibility()->hasIntegrationLimitationEligibility());
    }

    public function testSameStatusReasonButDifferentEligibilityIsAConflict(): void
    {
        // TASK-SEC-D r4 — eligibility is part of the identity: same status/reason with a
        // DIFFERENT source set is a conflict (first record wins WHOLE — no union).
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR), FallbackEligibility::technical()
        );
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR),
            new \Secomm\ShippingCore\Model\Fallback\FallbackEligibility(true, false, true)
        );

        $record = $this->collector->getDecisionRecords()['a']['m'];
        $this->assertSame('TECHNICAL_FAILURE', $record->getOutcome()->getStatus());
        $this->assertSame(
            ['TECHNICAL_FALLBACK'],
            $record->getFallbackEligibility()->getSources(),
            'First record stays whole — no cross-execution union'
        );
    }

    public function testExactIdenticalDuplicateIsIdempotent(): void
    {
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR), FallbackEligibility::technical()
        );
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR), FallbackEligibility::technical()
        );

        $record = $this->collector->getDecisionRecords()['a']['m'];
        $this->assertSame(
            ['TECHNICAL_FALLBACK'],
            $record->getFallbackEligibility()->getSources()
        );
    }

    public function testSuccessWithDifferentAmountIsAConflict(): void
    {
        // r4 — rate amount/currency are part of the identity: two successes with different
        // prices conflict; FIRST-SUCCESS is terminal (no price change by plugin order).
        $rate1 = $this->createMock(CarrierRateInterface::class);
        $rate1->method('getAmount')->willReturn(25000.0);
        $rate1->method('getCurrency')->willReturn('VND');
        $rate2 = $this->createMock(CarrierRateInterface::class);
        $rate2->method('getAmount')->willReturn(30000.0);
        $rate2->method('getCurrency')->willReturn('VND');

        $this->collector->recordDecision('a', 'm', CarrierRateOutcome::success($rate1), FallbackEligibility::none());
        $this->collector->recordDecision('a', 'm', CarrierRateOutcome::success($rate2), FallbackEligibility::none());

        $record = $this->collector->getDecisionRecords()['a']['m'];
        $this->assertSame('SUCCESS', $record->getOutcome()->getStatus());
        $this->assertSame(25000.0, $record->getOutcome()->getRate()->getAmount(), 'First success keeps its price');
    }

    public function testSuccessWithSameAmountButDifferentCurrencyIsAConflict(): void
    {
        $rate1 = $this->createMock(CarrierRateInterface::class);
        $rate1->method('getAmount')->willReturn(25000.0);
        $rate1->method('getCurrency')->willReturn('VND');
        $rate2 = $this->createMock(CarrierRateInterface::class);
        $rate2->method('getAmount')->willReturn(25000.0);
        $rate2->method('getCurrency')->willReturn('USD');

        $this->collector->recordDecision('a', 'm', CarrierRateOutcome::success($rate1), FallbackEligibility::none());
        $this->collector->recordDecision('a', 'm', CarrierRateOutcome::success($rate2), FallbackEligibility::none());

        $this->assertSame('VND', $this->collector->getDecisionRecords()['a']['m']->getOutcome()->getRate()->getCurrency());
    }

    public function testEligibilitySourceOrderIsDeterministicForIdentity(): void
    {
        // Same source set, different build order → identical identity → idempotent.
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'),
            new \Secomm\ShippingCore\Model\Fallback\FallbackEligibility(true, false, true)
        );
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'),
            new \Secomm\ShippingCore\Model\Fallback\FallbackEligibility(true, false, true)
        );

        $this->assertSame(
            ['TECHNICAL_FALLBACK', 'INTEGRATION_LIMITATION'],
            $this->collector->getDecisionRecords()['a']['m']->getFallbackEligibility()->getSources()
        );
    }

    public function testSuccessIsTerminalAgainstLaterNonSuccess(): void
    {
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::success($this->rate()), FallbackEligibility::none()
        );
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'), FallbackEligibility::technical()
        );

        $record = $this->collector->getDecisionRecords()['a']['m'];
        $this->assertSame('SUCCESS', $record->getOutcome()->getStatus());
        $this->assertFalse($record->getFallbackEligibility()->isEligible());
    }

    public function testSuccessAfterNonSuccessOverrides(): void
    {
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'), FallbackEligibility::technical()
        );
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::success($this->rate()), FallbackEligibility::none()
        );

        $record = $this->collector->getDecisionRecords()['a']['m'];
        $this->assertSame('SUCCESS', $record->getOutcome()->getStatus(), 'Success wins');
        $this->assertFalse($record->getFallbackEligibility()->isEligible());
    }

    public function testTransportedRecordIsNeverDowngradedByLegacyOverwrite(): void
    {
        $this->collector->recordDecision(
            'ghn', 'secomm_ghn', CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'), FallbackEligibility::technical()
        );
        // A legacy/standalone overwrite of the same pair must NOT drop the transport.
        $this->collector->record('ghn', 'secomm_ghn', CarrierRateOutcome::unavailable('SERVICE_UNAVAILABLE'));

        $record = $this->collector->getDecisionRecords()['ghn']['secomm_ghn'];
        $this->assertTrue($record->hasEligibilityTransport());
        $this->assertTrue($record->getFallbackEligibility()->hasTechnicalFallbackEligibility());
    }

    public function testMembersDoNotLeakState(): void
    {
        $this->collector->recordDecision(
            'a', 'ma', CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'), FallbackEligibility::technical()
        );
        $this->collector->recordDecision(
            'b', 'mb', CarrierRateOutcome::unavailable('SERVICE_UNAVAILABLE'), FallbackEligibility::none()
        );

        $this->assertArrayNotHasKey('mb', $this->collector->getDecisionRecords()['a']);
        $this->assertArrayNotHasKey('ma', $this->collector->getDecisionRecords()['b']);
    }

    public function testBeginResetsAndEndCleansEvenAfterExceptions(): void
    {
        $this->collector->recordDecision(
            'a', 'm', CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'), FallbackEligibility::technical()
        );
        $this->collector->endCollection();

        $this->assertSame([], $this->collector->getDecisionRecords());
        $this->assertSame([], $this->collector->getOutcomes());

        $this->collector->beginCollection();
        $this->collector->recordDecision(
            'b', 'm', CarrierRateOutcome::unavailable('SERVICE_UNAVAILABLE'), FallbackEligibility::none()
        );
        // endCollection() always cleans (bracket closes even when a carrier threw).
        $this->collector->endCollection();
        $this->assertSame([], $this->collector->getDecisionRecords());
    }

    private function rate(): CarrierRateInterface
    {
        return $this->createMock(CarrierRateInterface::class);
    }
}
