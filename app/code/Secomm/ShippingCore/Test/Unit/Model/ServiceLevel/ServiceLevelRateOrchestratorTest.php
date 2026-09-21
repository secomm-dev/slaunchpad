<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\ServiceLevel;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Fallback\FallbackPolicyInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackRateInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackRateProviderInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackRateRequestInterface;
use Secomm\ShippingCore\Api\ServiceLevelRateDecisionInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRate;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Fallback\FallbackEligibility;
use Secomm\ShippingCore\Api\ServiceLevelRateAggregateInterface;
use Secomm\ShippingCore\Model\Fallback\FallbackRate;
use Secomm\ShippingCore\Model\Fallback\FallbackRateProviderPool;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevel;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevelRegistry;
use Secomm\ShippingCore\Model\ServiceLevel\ServiceLevelRateAggregator;
use Secomm\ShippingCore\Model\ServiceLevel\ServiceLevelRateOrchestrator;

/**
 * TASK-M3ME32 — fallback decision matrix (directive §23) + provider invocation rules:
 * SUCCESS suppresses fallback entirely; the provider is called at most once, only on the valid
 * fallback path; disabled levels expose nothing. Test-defined levels only.
 */
class ServiceLevelRateOrchestratorTest extends TestCase
{
    private const LEVEL = 'LEVEL_A';
    private const DISABLED_LEVEL = 'LEVEL_OFF';

    private ShippingServiceLevelRegistry $registry;
    private FallbackPolicyInterface&MockObject $policy;
    private FallbackRateProviderInterface&MockObject $provider;
    private FallbackRateRequestInterface&MockObject $fallbackRequest;
    private ServiceLevelRateOrchestrator $orchestrator;

    protected function setUp(): void
    {
        $this->registry = new ShippingServiceLevelRegistry([
            new ShippingServiceLevel(self::LEVEL, 'Test Level A'),
            new ShippingServiceLevel(self::DISABLED_LEVEL, 'Disabled Level', enabled: false),
        ]);
        $this->policy = $this->createMock(FallbackPolicyInterface::class);
        $this->provider = $this->createMock(FallbackRateProviderInterface::class);
        $this->fallbackRequest = $this->createMock(FallbackRateRequestInterface::class);
        $this->orchestrator = new ServiceLevelRateOrchestrator(
            $this->registry,
            $this->policy,
            new FallbackRateProviderPool([$this->provider])
        );
    }

    public function testDisabledLevelIsUnavailableAndNeverTouchesProvider(): void
    {
        // Even with a SUCCESS-carrying aggregate, a disabled level exposes nothing (§25).
        $this->policy->expects($this->never())->method('isEnabled');
        $this->provider->expects($this->never())->method('getRate');

        $decision = $this->orchestrator->decide(
            self::DISABLED_LEVEL,
            $this->aggregateFor(self::DISABLED_LEVEL, success: true),
            $this->fallbackRequest
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $decision->getSource());
        $this->assertSame([], $decision->getRealtimeRates());
        $this->assertNull($decision->getFallbackRate());
        $this->assertFalse($decision->isAvailable());
    }

    public function testRealtimeSuccessSuppressesFallbackProviderCall(): void
    {
        $this->policy->expects($this->never())->method('isEnabled');
        $this->provider->expects($this->never())->method('getRate');

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL, success: true),
            $this->fallbackRequest
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_REALTIME, $decision->getSource());
        $this->assertSame(['carrierX'], array_keys($decision->getRealtimeRates()));
        $this->assertSame(30.0, $decision->getRealtimeRates()['carrierX']->getAmount());
        $this->assertNull($decision->getFallbackRate());
        $this->assertTrue($decision->isAvailable());
    }

    public function testRealtimeSuccessWithConcurrentTechnicalFailureStillSuppressesFallback(): void
    {
        $this->provider->expects($this->never())->method('getRate');

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL, success: true, technicalFailure: true),
            $this->fallbackRequest
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_REALTIME, $decision->getSource());
        $this->assertTrue($decision->isAvailable());
    }

    public function testNoRateAndNoTechnicalFailureIsUnavailableWithoutProviderCall(): void
    {
        $this->provider->expects($this->never())->method('getRate');

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL),
            $this->fallbackRequest
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $decision->getSource());
    }

    public function testPolicyDisabledBlocksFallbackProviderCall(): void
    {
        $this->policy->method('isEnabled')->willReturn(false);
        $this->provider->expects($this->never())->method('getRate');

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL, technicalFailure: true),
            $this->fallbackRequest
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $decision->getSource());
        $this->assertFalse($decision->isAvailable());
    }

    public function testZeroProvidersWithOtherwiseEligibleFallbackIsUnavailableNotThrowing(): void
    {
        $orchestrator = new ServiceLevelRateOrchestrator(
            $this->registry,
            $this->policyAlwaysEnabled(),
            new FallbackRateProviderPool([])
        );

        $decision = $orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL, technicalFailure: true),
            $this->fallbackRequest
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $decision->getSource());
        $this->assertFalse($decision->isAvailable());
    }

    public function testProviderNullResultMeansUnavailable(): void
    {
        $this->policy->method('isEnabled')->willReturn(true);
        $this->provider->expects($this->once())->method('getRate')
            ->with(self::LEVEL, $this->fallbackRequest)
            ->willReturn(null);

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL, technicalFailure: true),
            $this->fallbackRequest
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $decision->getSource());
        $this->assertFalse($decision->isAvailable());
    }

    public function testValidFallbackPathCallsProviderExactlyOnce(): void
    {
        $this->policy->method('isEnabled')->willReturn(true);
        $fallbackRate = new FallbackRate(40.0, 'Emergency Standard');
        $this->provider->expects($this->once())->method('getRate')
            ->with(self::LEVEL, $this->fallbackRequest)
            ->willReturn($fallbackRate);

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL, technicalFailure: true),
            $this->fallbackRequest
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_FALLBACK, $decision->getSource());
        $this->assertSame([], $decision->getRealtimeRates());
        $this->assertSame($fallbackRate, $decision->getFallbackRate());
        $this->assertTrue($decision->isAvailable());
    }

    public function testZeroValuedFallbackRateIsStillAFallbackDecision(): void
    {
        // r2 semantics: 0 is a valid explicit rate — only provider null means "no fallback".
        $this->policy->method('isEnabled')->willReturn(true);
        $this->provider->expects($this->once())->method('getRate')
            ->willReturn(new FallbackRate(0.0, 'Free Fallback'));

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL, technicalFailure: true),
            $this->fallbackRequest
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_FALLBACK, $decision->getSource());
        $this->assertTrue($decision->isAvailable());
    }

    public function testMultipleRegisteredProvidersAreAReportedConfigurationError(): void
    {
        // §15 — no provider routing: ambiguity fails fast instead of first-provider-wins.
        $orchestrator = new ServiceLevelRateOrchestrator(
            $this->registry,
            $this->policyAlwaysEnabled(),
            new FallbackRateProviderPool([
                $this->createMock(FallbackRateProviderInterface::class),
                $this->createMock(FallbackRateProviderInterface::class),
            ])
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('More than one fallback rate provider');
        $orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL, technicalFailure: true),
            $this->fallbackRequest
        );
    }

    public function testAggregateCodeMismatchFailsFast(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot decide for');
        $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::DISABLED_LEVEL),
            $this->fallbackRequest
        );
    }

    public function testUnknownServiceLevelCodeIsAConfigurationError(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown shipping service level code');
        $this->orchestrator->decide('EXPRESS', $this->aggregateFor('EXPRESS'), $this->fallbackRequest);
    }

    public function testLegacyEligibilityWithNoSuccessYieldsFallbackWhenPolicyAllows(): void
    {
        // GHN RATE = DIRECT_FALLBACK (legacy strategy): no carrier outcome at all, legacy flag supplied.
        $this->policy->method('isEnabled')->willReturn(true);
        $fallbackRate = new FallbackRate(40.0, 'Emergency Standard');
        $this->provider->expects($this->once())->method('getRate')
            ->with(self::LEVEL, $this->fallbackRequest)
            ->willReturn($fallbackRate);

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL),
            $this->fallbackRequest,
            FallbackEligibility::legacyAddress()
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_FALLBACK, $decision->getSource());
        $this->assertSame($fallbackRate, $decision->getFallbackRate());
        $this->assertTrue($decision->isAvailable());
    }

    public function testLegacyEligibilityDoesNotSuppressRealtimeSuccess(): void
    {
        // Example F/G (architecture v5): legacy-eligible carrier + another carrier SUCCESS
        // → REALTIME wins, fallback provider never called.
        $this->policy->expects($this->never())->method('isEnabled');
        $this->provider->expects($this->never())->method('getRate');

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL, success: true, technicalFailure: true),
            $this->fallbackRequest,
            FallbackEligibility::legacyAddress()
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_REALTIME, $decision->getSource());
    }

    public function testBothEligibilitySourcesYieldExactlyOneFallbackCall(): void
    {
        $this->policy->method('isEnabled')->willReturn(true);
        $this->provider->expects($this->once())->method('getRate')
            ->willReturn(new FallbackRate(40.0, 'Emergency Standard'));

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL, technicalFailure: true),
            $this->fallbackRequest,
            FallbackEligibility::legacyAddress()
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_FALLBACK, $decision->getSource());
    }

    public function testEligibilityWithPolicyDisabledIsUnavailable(): void
    {
        $this->policy->method('isEnabled')->willReturn(false);
        $this->provider->expects($this->never())->method('getRate');

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL),
            $this->fallbackRequest,
            FallbackEligibility::legacyAddress()
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $decision->getSource());
        $this->assertFalse($decision->isAvailable());
    }

    public function testLegacyEligibilityWithProviderNullIsUnavailable(): void
    {
        $this->policy->method('isEnabled')->willReturn(true);
        $this->provider->expects($this->once())->method('getRate')->willReturn(null);

        $decision = $this->orchestrator->decide(
            self::LEVEL,
            $this->aggregateFor(self::LEVEL),
            $this->fallbackRequest,
            FallbackEligibility::legacyAddress()
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $decision->getSource());
        $this->assertFalse($decision->isAvailable());
    }

    /**
     * Real E-SL1 aggregate built through the real aggregator (contract-fit end to end).
     */
    private function aggregateFor(
        string $serviceLevelCode,
        bool $success = false,
        bool $technicalFailure = false
    ): ServiceLevelRateAggregateInterface {
        $outcomes = [];
        if ($success) {
            $outcomes['carrierX'] = CarrierRateOutcome::success(new CarrierRate(30.0));
        }
        if ($technicalFailure) {
            $outcomes['carrierY'] = CarrierRateOutcome::technicalFailure(null);
        }

        $aggregator = new ServiceLevelRateAggregator($this->registry);

        return $aggregator->aggregate($serviceLevelCode, $outcomes);
    }

    private function policyAlwaysEnabled(): FallbackPolicyInterface
    {
        $policy = $this->createMock(FallbackPolicyInterface::class);
        $policy->method('isEnabled')->willReturn(true);

        return $policy;
    }
}
