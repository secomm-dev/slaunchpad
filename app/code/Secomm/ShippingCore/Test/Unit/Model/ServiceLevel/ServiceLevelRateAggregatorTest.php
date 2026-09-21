<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\ServiceLevel;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\ServiceLevelRateAggregateInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Model\Rate\CarrierRate;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevel;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevelRegistry;
use Secomm\ShippingCore\Model\Fallback\FallbackEligibility;
use Secomm\ShippingCore\Model\ServiceLevel\ServiceLevelRateAggregator;

/**
 * TASK-32ACTR — per-service-level realtime aggregation: exact outcome matrix, dynamic registry
 * validation, carrier-identity preservation, reason independence. Test-defined levels only
 * (LEVEL_A/LEVEL_B) — no Launchpad taxonomy anywhere (directive §18).
 */
class ServiceLevelRateAggregatorTest extends TestCase
{
    private const LEVEL = 'LEVEL_A';

    private ServiceLevelRateAggregator $aggregator;

    protected function setUp(): void
    {
        $registry = new ShippingServiceLevelRegistry([
            new ShippingServiceLevel(self::LEVEL, 'Test Level A'),
            new ShippingServiceLevel('LEVEL_B', 'Test Level B', enabled: false),
        ]);
        $this->aggregator = new ServiceLevelRateAggregator($registry);
    }

    public function testZeroOutcomesIsValidEmptyAggregate(): void
    {
        $aggregate = $this->aggregator->aggregate(self::LEVEL, []);

        $this->assertSame(self::LEVEL, $aggregate->getServiceLevelCode());
        $this->assertSame([], $aggregate->getSuccessfulRates());
        $this->assertFalse($aggregate->hasSuccessfulRate());
        $this->assertFalse($aggregate->hasTechnicalFailure());
        $this->assertSame(0, $aggregate->getOutcomeCount());
    }

    public function testAllUnavailableYieldsNoRatesAndNoTechnicalSignal(): void
    {
        $aggregate = $this->aggregator->aggregate(self::LEVEL, [
            'carrierX' => CarrierRateOutcome::unavailable(),
            'carrierY' => CarrierRateOutcome::unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE),
        ]);

        $this->assertSame([], $aggregate->getSuccessfulRates());
        $this->assertFalse($aggregate->hasSuccessfulRate());
        $this->assertFalse($aggregate->hasTechnicalFailure());
        $this->assertSame(2, $aggregate->getOutcomeCount());
    }

    public function testTechnicalFailureAloneSignalsWithoutRates(): void
    {
        $aggregate = $this->aggregator->aggregate(self::LEVEL, [
            'carrierX' => CarrierRateOutcome::technicalFailure(),
        ]);

        $this->assertSame([], $aggregate->getSuccessfulRates());
        $this->assertFalse($aggregate->hasSuccessfulRate());
        $this->assertTrue($aggregate->hasTechnicalFailure());
    }

    public function testSingleSuccessIsPreservedWithCarrierKey(): void
    {
        $rate = new CarrierRate(30.0);
        $aggregate = $this->aggregator->aggregate(self::LEVEL, [
            'carrierX' => CarrierRateOutcome::success($rate),
        ]);

        $this->assertSame(['carrierX' => $rate], $aggregate->getSuccessfulRates());
        $this->assertTrue($aggregate->hasSuccessfulRate());
        $this->assertFalse($aggregate->hasTechnicalFailure());
    }

    public function testSuccessPlusUnavailableKeepsRateWithoutTechnicalSignal(): void
    {
        $aggregate = $this->aggregator->aggregate(self::LEVEL, [
            'carrierX' => CarrierRateOutcome::success(new CarrierRate(30.0)),
            'carrierY' => CarrierRateOutcome::unavailable(),
        ]);

        $this->assertCount(1, $aggregate->getSuccessfulRates());
        $this->assertTrue($aggregate->hasSuccessfulRate());
        $this->assertFalse($aggregate->hasTechnicalFailure());
    }

    public function testMixedSuccessAndTechnicalFailureKeepsBothSignals(): void
    {
        // The technical-failure signal is NOT suppressed by a concurrent success — the
        // "any SUCCESS ⇒ no fallback" rule belongs to E-SL2, not to the aggregate.
        $aggregate = $this->aggregator->aggregate(self::LEVEL, [
            'carrierX' => CarrierRateOutcome::success(new CarrierRate(30.0)),
            'carrierY' => CarrierRateOutcome::technicalFailure(),
        ]);

        $this->assertTrue($aggregate->hasSuccessfulRate());
        $this->assertTrue($aggregate->hasTechnicalFailure());
        $this->assertCount(1, $aggregate->getSuccessfulRates());
    }

    public function testUnavailablePlusTechnicalFailureSignalsWithoutRates(): void
    {
        $aggregate = $this->aggregator->aggregate(self::LEVEL, [
            'carrierX' => CarrierRateOutcome::unavailable(),
            'carrierY' => CarrierRateOutcome::technicalFailure('CONN_TIMEOUT'),
        ]);

        $this->assertSame([], $aggregate->getSuccessfulRates());
        $this->assertTrue($aggregate->hasTechnicalFailure());
        $this->assertSame(2, $aggregate->getOutcomeCount());
    }

    public function testMultipleSuccessesAreAllPreservedInInputOrder(): void
    {
        $rateX = new CarrierRate(30.0);
        $rateY = new CarrierRate(35.0);
        // Deliberately NOT sorted by price — input registration order is preserved (no ranking).
        $aggregate = $this->aggregator->aggregate(self::LEVEL, [
            'carrierX' => CarrierRateOutcome::success($rateX),
            'carrierY' => CarrierRateOutcome::success($rateY),
        ]);

        $this->assertSame(['carrierX' => $rateX, 'carrierY' => $rateY], $aggregate->getSuccessfulRates());
        $this->assertTrue($aggregate->hasSuccessfulRate());
        $this->assertFalse($aggregate->hasTechnicalFailure());
    }

    public function testReasonStringsNeverDriveAggregation(): void
    {
        // UNAVAILABLE carrying a technical-looking reason stays UNAVAILABLE (no technical signal)…
        $unavailableWithTechnicalReason = $this->aggregator->aggregate(self::LEVEL, [
            'carrierX' => CarrierRateOutcome::unavailable('TECHNICAL_ERROR'),
        ]);
        $this->assertFalse($unavailableWithTechnicalReason->hasTechnicalFailure());

        // …and TECHNICAL_FAILURE with no reason at all still sets the signal.
        $technicalWithoutReason = $this->aggregator->aggregate(self::LEVEL, [
            'carrierY' => CarrierRateOutcome::technicalFailure(null),
        ]);
        $this->assertTrue($technicalWithoutReason->hasTechnicalFailure());
    }

    public function testUnknownServiceLevelCodeIsAConfigurationError(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown shipping service level code');
        $this->aggregator->aggregate('EXPRESS', []);
    }

    public function testRegisteredButDisabledLevelIsStillKnown(): void
    {
        $aggregate = $this->aggregator->aggregate('LEVEL_B', []);

        $this->assertSame('LEVEL_B', $aggregate->getServiceLevelCode());
        $this->addToAssertionCount(1);
    }

    public function testRejectsNonStringCarrierKey(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('keyed by a non-empty carrier code');
        $this->aggregator->aggregate(self::LEVEL, [0 => CarrierRateOutcome::unavailable()]);
    }

    public function testRejectsNonOutcomeEntry(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must implement');
        $this->aggregator->aggregate(self::LEVEL, ['carrierX' => new CarrierRate(30.0)]);
    }

    public function testAggregatorDoesNotDependOnFallbackProvider(): void
    {
        $constructor = (new \ReflectionClass(ServiceLevelRateAggregator::class))->getConstructor();
        $types = array_map(
            static fn ($parameter): string => (string) $parameter->getType(),
            $constructor->getParameters()
        );

        $this->assertNotContains('Secomm\ShippingCore\Api\Fallback\FallbackRateProviderInterface', $types);
        $this->assertNotContains('Secomm\ShippingCore\Api\Fallback\FallbackRateProviderPool', $types);
    }
    public function testExplicitFallbackEligibilityIsCarriedOnTheAggregate(): void
    {
        $eligibility = FallbackEligibility::legacyAddress();

        $aggregate = $this->aggregator->aggregate(self::LEVEL, [], $eligibility);

        $this->assertSame($eligibility, $aggregate->getFallbackEligibility());
        $this->assertTrue($aggregate->hasLegacyAddressFallbackEligibility());
    }

    public function testAggregateWithoutExplicitEligibilityCarriesNull(): void
    {
        $aggregate = $this->aggregator->aggregate(self::LEVEL, []);

        $this->assertNull($aggregate->getFallbackEligibility());
        $this->assertFalse($aggregate->hasLegacyAddressFallbackEligibility());
    }
}
