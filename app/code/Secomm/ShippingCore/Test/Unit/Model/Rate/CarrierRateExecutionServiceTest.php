<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Rate;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\DestinationScope;
use Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityPolicyInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilitySource;
use Secomm\ShippingCore\Api\OriginInterface;
use Secomm\ShippingCore\Api\OriginProviderInterface;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityEvaluatorInterface;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityResultInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;
use Secomm\ShippingCore\Api\Rate\RealtimeCarrierRateContributorInterface;
use Secomm\ShippingCore\Api\ShippingContextInterface;
use Secomm\ShippingCore\Model\Fallback\SafeDegradationEligibilityPolicy;
use Secomm\ShippingCore\Model\Rate\CarrierRateExecutionRequest;
use Secomm\ShippingCore\Model\Rate\CarrierRateExecutionService;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;

/**
 * TASK-8MQHJX (Phase C) — shared execution gating. Proves the frozen v10 hard order
 * (eligibility → mode → origin → address policy → realtime) and the per-mode semantics:
 * ineligible = zero contributions everywhere; FALLBACK_ONLY skips everything but keeps
 * fallback eligibility; CARRIER_ONLY never emits fallback; CARRIER_WITH_FALLBACK emits
 * eligibility only per the shared policy; STRICT/FALLBACK/PICK_PRIMARY address blocks.
 *
 * Stubbing convention: NO default stubs are registered in setUp (a later method() stub would
 * never win over the earlier one) — every test states its scenario through the given* helpers.
 */
class CarrierRateExecutionServiceTest extends TestCase
{
    private CarrierEligibilityEvaluatorInterface&MockObject $evaluator;
    private OriginProviderInterface&MockObject $originProvider;
    private CarrierAddressHandoffServiceInterface&MockObject $handoffService;
    private RealtimeCarrierRateContributorInterface&MockObject $contributor;
    private FallbackEligibilityPolicyInterface $fallbackPolicy;
    private CarrierRateExecutionService $service;

    protected function setUp(): void
    {
        $this->evaluator = $this->createMock(CarrierEligibilityEvaluatorInterface::class);
        $this->originProvider = $this->createMock(OriginProviderInterface::class);
        $this->handoffService = $this->createMock(CarrierAddressHandoffServiceInterface::class);
        $this->contributor = $this->createMock(RealtimeCarrierRateContributorInterface::class);
        // REAL shared policy — eligibility decisions must follow the frozen default map.
        $this->fallbackPolicy = new SafeDegradationEligibilityPolicy();

        $this->service = new CarrierRateExecutionService(
            $this->evaluator,
            $this->originProvider,
            $this->handoffService,
            $this->fallbackPolicy
        );
    }

    // ---------------------------------------------------------------- §17 ordering / ineligible

    public function testIneligibleCarrierShortCircuitsBeforeModeOriginPolicyAndRealtime(): void
    {
        $this->givenEligibility(false);
        $this->originProvider->expects($this->never())->method('resolve');
        $this->handoffService->expects($this->never())->method('handoffContextForOperation');
        $this->contributor->expects($this->never())->method('contribute');

        $decision = $this->service->execute(
            $this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK, scope: DestinationScope::SELECTED_ZONES)
        );

        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertNull($decision->getRealtimeOutcome());
        $this->assertNull($decision->getCarrierFacingHandoff());
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame('DESTINATION_NOT_IN_SCOPE', $decision->getReason());
    }

    public function testEligibilityIsEvaluatedBeforeAnythingElse(): void
    {
        // Ordering proof: every delegation happens in the frozen order, and eligibility runs
        // first even on the fullest path (CARRIER_ONLY, resolved address, success).
        $calls = [];
        $this->evaluator->method('evaluate')->willReturnCallback(
            function () use (&$calls): CarrierEligibilityResultInterface {
                $calls[] = 'eligibility';

                return $this->eligibilityResult(true);
            }
        );
        $this->originProvider->method('resolve')->willReturnCallback(
            function () use (&$calls): OriginInterface {
                $calls[] = 'origin';

                return $this->origin('VN');
            }
        );
        $resolvedHandoff = $this->handoff(resolved: $this->resolvedAddress());
        $this->handoffService->method('handoffContextForOperation')->willReturnCallback(
            function () use (&$calls, $resolvedHandoff): CarrierAddressHandoffInterface {
                $calls[] = 'handoff';

                return $resolvedHandoff;
            }
        );
        $this->contributor->method('contribute')->willReturnCallback(
            function () use (&$calls): CarrierRateOutcomeInterface {
                $calls[] = 'realtime';

                return CarrierRateOutcome::success($this->createMock(CarrierRateInterface::class));
            }
        );

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_ONLY));

        $this->assertSame(['eligibility', 'origin', 'handoff', 'realtime'], $calls);
        $this->assertTrue($decision->shouldInvokeRealtime());
    }

    // ---------------------------------------------------------------- §18 FALLBACK_ONLY

    public function testFallbackOnlySkipsOriginPolicyHandoffRealtimeAndKeepsFallbackEligibility(): void
    {
        $this->givenEligibility(true);
        // PICK_PRIMARY as the configured policy proves it is never even evaluated.
        $this->originProvider->expects($this->never())->method('resolve');
        $this->handoffService->expects($this->never())->method('handoffContextForOperation');
        $this->contributor->expects($this->never())->method('contribute');

        $decision = $this->service->execute(
            $this->makeRequest(RateSourceMode::FALLBACK_ONLY, AddressResolutionPolicy::PICK_PRIMARY)
        );

        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertNull($decision->getRealtimeOutcome());
        $this->assertNull($decision->getCarrierFacingHandoff());
        $this->assertTrue($decision->getFallbackEligibility()->isEligible());
        $this->assertSame(
            [FallbackEligibilitySource::LEGACY_ADDRESS_FALLBACK],
            $decision->getFallbackEligibility()->getSources()
        );
    }

    public function testFallbackOnlyIneligibleCarrierGetsNoFallbackContribution(): void
    {
        $this->givenEligibility(false);

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::FALLBACK_ONLY));

        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
    }

    // ---------------------------------------------------------------- §19 CARRIER_ONLY

    public function testCarrierOnlyEligibleResolvedInvokesRealtimeWithHandoff(): void
    {
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        $resolvedHandoff = $this->handoff(resolved: $this->resolvedAddress());
        $this->handoffService->method('handoffContextForOperation')->willReturn($resolvedHandoff);
        $outcome = CarrierRateOutcome::success($this->createMock(CarrierRateInterface::class));
        $this->contributor->expects($this->once())->method('contribute')
            ->with('ghn', $resolvedHandoff)
            ->willReturn($outcome);

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_ONLY));

        $this->assertTrue($decision->shouldInvokeRealtime());
        $this->assertSame($outcome, $decision->getRealtimeOutcome());
        $this->assertSame($resolvedHandoff, $decision->getCarrierFacingHandoff());
        // CARRIER_ONLY never emits fallback eligibility — even on success paths.
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
    }

    public function testCarrierOnlyTechnicalFailureEmitsNoFallback(): void
    {
        $this->givenHappyPathUntilRealtime();
        $this->contributor->method('contribute')
            ->willReturn(CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR));

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_ONLY));

        $this->assertTrue($decision->shouldInvokeRealtime());
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame([], $decision->getFallbackEligibility()->getSources());
    }

    public function testCarrierOnlyUnavailableEmitsNoFallback(): void
    {
        $this->givenHappyPathUntilRealtime();
        $this->contributor->method('contribute')
            ->willReturn(CarrierRateOutcome::unavailable(ShippingFailureReason::CANONICAL_AMBIGUOUS));

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_ONLY));

        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
    }

    // ---------------------------------------------------------------- §20 CARRIER_WITH_FALLBACK

    public function testCarrierWithFallbackInvokesRealtimeFirstThenEmitsTechnicalEligibility(): void
    {
        $this->givenHappyPathUntilRealtime();
        $this->contributor->expects($this->once())->method('contribute')
            ->willReturn(CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR));

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK));

        $this->assertTrue($decision->shouldInvokeRealtime());
        $this->assertTrue($decision->getFallbackEligibility()->hasTechnicalFallbackEligibility());
        $this->assertSame(
            [FallbackEligibilitySource::TECHNICAL_FALLBACK],
            $decision->getFallbackEligibility()->getSources()
        );
    }

    public function testCarrierWithFallbackUnavailableAmbiguousEmitsLegacyAddressEligibility(): void
    {
        $this->givenHappyPathUntilRealtime();
        $this->contributor->method('contribute')
            ->willReturn(CarrierRateOutcome::unavailable(ShippingFailureReason::CANONICAL_AMBIGUOUS));

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK));

        $this->assertTrue($decision->getFallbackEligibility()->hasLegacyAddressFallbackEligibility());
        $this->assertSame(
            [FallbackEligibilitySource::LEGACY_ADDRESS_FALLBACK],
            $decision->getFallbackEligibility()->getSources()
        );
    }

    public function testCarrierWithFallbackBusinessRejectionEmitsNoFallback(): void
    {
        $this->givenHappyPathUntilRealtime();
        $this->contributor->method('contribute')
            ->willReturn(CarrierRateOutcome::unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE));

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK));

        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
    }

    public function testCarrierWithFallbackSuccessEmitsNoFallbackEligibility(): void
    {
        $this->givenHappyPathUntilRealtime();
        $this->contributor->method('contribute')
            ->willReturn(CarrierRateOutcome::success($this->createMock(CarrierRateInterface::class)));

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK));

        $this->assertTrue($decision->shouldInvokeRealtime());
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertNull($decision->getReason());
    }

    public function testProviderMappingMissingWithFallbackModeEmitsIntegrationLimitation(): void
    {
        // TASK-8MQHJX Phase C amendment: the frozen v10 §35.5 case — outcome stays
        // UNAVAILABLE (never reclassified), eligibility source is INTEGRATION_LIMITATION.
        $this->givenHappyPathUntilRealtime();
        $outcome = CarrierRateOutcome::unavailable(ShippingFailureReason::PROVIDER_MAPPING_MISSING);
        $this->contributor->method('contribute')->willReturn($outcome);

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK));

        $this->assertTrue($decision->shouldInvokeRealtime());
        $this->assertSame($outcome, $decision->getRealtimeOutcome());
        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $decision->getRealtimeOutcome()->getStatus());
        $this->assertTrue($decision->getFallbackEligibility()->hasIntegrationLimitationEligibility());
        $this->assertFalse($decision->getFallbackEligibility()->hasTechnicalFallbackEligibility());
        $this->assertFalse($decision->getFallbackEligibility()->hasLegacyAddressFallbackEligibility());
        $this->assertSame(
            [FallbackEligibilitySource::INTEGRATION_LIMITATION],
            $decision->getFallbackEligibility()->getSources()
        );
    }

    public function testCarrierOnlyProviderMappingMissingEmitsNoFallback(): void
    {
        $this->givenHappyPathUntilRealtime();
        $this->contributor->method('contribute')
            ->willReturn(CarrierRateOutcome::unavailable(ShippingFailureReason::PROVIDER_MAPPING_MISSING));

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_ONLY));

        $this->assertTrue($decision->shouldInvokeRealtime());
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame([], $decision->getFallbackEligibility()->getSources());
    }

    public function testServiceUnavailableNeverEmitsIntegrationLimitation(): void
    {
        // Carrier business/service rejection is never masked as an integration limitation.
        $this->givenHappyPathUntilRealtime();
        $this->contributor->method('contribute')
            ->willReturn(CarrierRateOutcome::unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE));

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK));

        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame([], $decision->getFallbackEligibility()->getSources());
    }

    public function testCarrierCapabilityRejectionNeverEmitsIntegrationLimitation(): void
    {
        // Non-VN destination (UNSUPPORTED_DESTINATION) is a capability/business rejection:
        // policy judges it ineligible, so no source of any kind may appear.
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        $this->handoffService->method('handoffContextForOperation')
            ->willReturn($this->handoff(applicable: false));
        $this->contributor->expects($this->never())->method('contribute');

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK));

        $this->assertSame([], $decision->getFallbackEligibility()->getSources());
    }

    public function testMerchantInvalidConfigurationStaysFailClosedEvenWhenPolicyOptsIn(): void
    {
        // INVALID_CONFIGURATION is frozen as MERCHANT-side carrier configuration — it must
        // fail closed even when a composition opts it into the shared policy: the metadata
        // distinguishes meanings, and the merchant-side meaning is never degraded.
        $optInPolicy = new SafeDegradationEligibilityPolicy(
            [ShippingFailureReason::INVALID_CONFIGURATION]
        );
        $service = new CarrierRateExecutionService(
            $this->evaluator,
            $this->originProvider,
            $this->handoffService,
            $optInPolicy
        );
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        $this->handoffService->method('handoffContextForOperation')
            ->willReturn($this->handoff(resolved: $this->resolvedAddress()));
        $this->contributor->method('contribute')
            ->willReturn(CarrierRateOutcome::unavailable(ShippingFailureReason::INVALID_CONFIGURATION));

        $decision = $service->execute($this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK));

        $this->assertTrue($optInPolicy->isFallbackEligible(
            CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
            ShippingFailureReason::INVALID_CONFIGURATION
        ));
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame([], $decision->getFallbackEligibility()->getSources());
    }

    // ---------------------------------------------------------------- §21 address policies

    public function testStrictAmbiguousBlocksRealtimeAndFallbackEvenWithFallbackMode(): void
    {
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        // STRICT drops candidates inside the handoff service: unresolved + no candidates.
        $this->handoffService->method('handoffContextForOperation')
            ->with($this->anything(), $this->anything(), 'RATE', AddressResolutionPolicy::STRICT)
            ->willReturn($this->handoff(resolved: null, candidates: [], textual: false));
        $this->contributor->expects($this->never())->method('contribute');

        $decision = $this->service->execute(
            $this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK, AddressResolutionPolicy::STRICT)
        );

        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertNull($decision->getRealtimeOutcome());
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame(ShippingFailureReason::CANONICAL_UNRESOLVED, $decision->getReason());
    }

    public function testFallbackPolicyAmbiguousWithFallbackModeEmitsEligibilityWithoutRealtime(): void
    {
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        $ambiguousHandoff = $this->handoff(resolved: null, candidates: ['VNA25-A', 'VNA25-B'], textual: false);
        $this->handoffService->method('handoffContextForOperation')
            ->with($this->anything(), $this->anything(), 'RATE', AddressResolutionPolicy::FALLBACK)
            ->willReturn($ambiguousHandoff);
        $this->contributor->expects($this->never())->method('contribute');

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK));

        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertSame($ambiguousHandoff, $decision->getCarrierFacingHandoff());
        $this->assertTrue($decision->getFallbackEligibility()->hasLegacyAddressFallbackEligibility());
    }

    public function testFallbackPolicyAmbiguousCarrierOnlyGetsNoFallback(): void
    {
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        $this->handoffService->method('handoffContextForOperation')
            ->willReturn($this->handoff(resolved: null, candidates: ['VNA25-A'], textual: false));

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_ONLY));

        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
    }

    public function testPickPrimarySelectionSuccessReachesRealtimeWithSelectedDestinationOnly(): void
    {
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        $selectedHandoff = $this->handoff(resolved: $this->resolvedAddress(), candidates: []);
        $this->handoffService->method('handoffContextForOperation')
            ->with($this->anything(), $this->anything(), 'RATE', AddressResolutionPolicy::PICK_PRIMARY)
            ->willReturn($selectedHandoff);
        $this->contributor->expects($this->once())->method('contribute')
            ->with('ghn', $selectedHandoff)
            ->willReturn(CarrierRateOutcome::success($this->createMock(CarrierRateInterface::class)));

        $decision = $this->service->execute(
            $this->makeRequest(RateSourceMode::CARRIER_ONLY, AddressResolutionPolicy::PICK_PRIMARY)
        );

        $this->assertTrue($decision->shouldInvokeRealtime());
        $this->assertSame($selectedHandoff, $decision->getCarrierFacingHandoff());
    }

    public function testPickPrimaryNoValidPrimaryCarrierOnlyNoRealtimeNoFallback(): void
    {
        // Selection failed inside the handoff service: candidates dropped, nothing resolved.
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        $this->handoffService->method('handoffContextForOperation')
            ->willReturn($this->handoff(resolved: null, candidates: [], textual: false));
        $this->contributor->expects($this->never())->method('contribute');

        $decision = $this->service->execute(
            $this->makeRequest(RateSourceMode::CARRIER_ONLY, AddressResolutionPolicy::PICK_PRIMARY)
        );

        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
    }

    public function testPickPrimaryNoValidPrimaryWithFallbackModeIsFallbackEligible(): void
    {
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        $this->handoffService->method('handoffContextForOperation')
            ->willReturn($this->handoff(resolved: null, candidates: [], textual: false));
        $this->contributor->expects($this->never())->method('contribute');

        $decision = $this->service->execute(
            $this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK, AddressResolutionPolicy::PICK_PRIMARY)
        );

        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertTrue($decision->getFallbackEligibility()->hasLegacyAddressFallbackEligibility());
    }

    public function testUnmappedTextualEligibleHandoffStillInvokesRealtime(): void
    {
        // UNMAPPED is not an ambiguity block: the carrier-side textual strategy keeps working
        // (handoff communicates eligibility; execution does not gate it away).
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        $unmappedHandoff = $this->handoff(resolved: null, candidates: [], textual: true);
        $this->handoffService->method('handoffContextForOperation')->willReturn($unmappedHandoff);
        $this->contributor->expects($this->once())->method('contribute')
            ->with('ghn', $unmappedHandoff)
            ->willReturn(CarrierRateOutcome::success($this->createMock(CarrierRateInterface::class)));

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_ONLY));

        $this->assertTrue($decision->shouldInvokeRealtime());
    }

    public function testNonVnDestinationNeverInvokesRealtime(): void
    {
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        $this->handoffService->method('handoffContextForOperation')
            ->willReturn($this->handoff(applicable: false));
        $this->contributor->expects($this->never())->method('contribute');

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK));

        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame(ShippingFailureReason::UNSUPPORTED_DESTINATION, $decision->getReason());
    }

    // ---------------------------------------------------------------- §22 origin readiness

    public function testCarrierOnlyWithUnresolvedOriginFailsClosedWithoutModeMutation(): void
    {
        $this->givenEligibility(true);
        $this->givenOrigin(null);
        $this->handoffService->expects($this->never())->method('handoffContextForOperation');
        $this->contributor->expects($this->never())->method('contribute');

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_ONLY));

        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertNull($decision->getRealtimeOutcome());
        // Fail closed: no silently inferred fallback and no re-moded behavior.
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame(ShippingFailureReason::INVALID_CONFIGURATION, $decision->getReason());
    }

    public function testCarrierWithFallbackUnresolvedOriginDefaultPolicyEmitsNoFallback(): void
    {
        $this->givenEligibility(true);
        $this->givenOrigin('');

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::CARRIER_WITH_FALLBACK));

        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
    }

    public function testFallbackOnlyIgnoresUnresolvedOriginByDesign(): void
    {
        $this->givenEligibility(true);
        $this->originProvider->expects($this->never())->method('resolve');

        $decision = $this->service->execute($this->makeRequest(RateSourceMode::FALLBACK_ONLY));

        $this->assertTrue($decision->getFallbackEligibility()->isEligible());
    }

    // ---------------------------------------------------------------- request validation

    public function testUnknownModeIsRejectedAtConstruction(): void
    {
        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('Unknown rate source mode');

        $this->makeRequest('TELEPATHY');
    }

    public function testUnknownPolicyIsRejectedAtConstruction(): void
    {
        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('Unknown address resolution policy');

        $this->makeRequest(RateSourceMode::CARRIER_ONLY, 'MAGIC');
    }

    // ---------------------------------------------------------------- scenario helpers

    private function givenEligibility(bool $eligible): void
    {
        $this->evaluator->method('evaluate')->willReturn($this->eligibilityResult($eligible));
    }

    private function givenOrigin(?string $countryId): void
    {
        $this->originProvider->method('resolve')->willReturn($this->origin($countryId));
    }

    /** Eligible carrier + resolved origin + resolved handoff — the pre-realtime happy path. */
    private function givenHappyPathUntilRealtime(): void
    {
        $this->givenEligibility(true);
        $this->givenOrigin('VN');
        $this->handoffService->method('handoffContextForOperation')
            ->willReturn($this->handoff(resolved: $this->resolvedAddress()));
    }

    private function eligibilityResult(bool $eligible): CarrierEligibilityResultInterface
    {
        return $this->createConfiguredMock(CarrierEligibilityResultInterface::class, [
            'isEligible' => $eligible,
            'getReasonCode' => $eligible ? 'ELIGIBLE' : 'DESTINATION_NOT_IN_SCOPE',
            'getMatchedZoneCode' => null,
        ]);
    }

    private function makeRequest(
        string $mode,
        string $policy = AddressResolutionPolicy::FALLBACK,
        string $scope = DestinationScope::ALL
    ): CarrierRateExecutionRequest {
        return new CarrierRateExecutionRequest(
            carrierCode: 'ghn',
            destinationScope: $scope,
            allowedZoneCodes: $scope === DestinationScope::SELECTED_ZONES ? ['ZONE_A'] : [],
            destinationProvinceCode: 'VN-SG',
            destinationWardCode: 'VNA25-26734',
            rateSourceMode: $mode,
            addressResolutionPolicy: $policy,
            capability: $this->createMock(CarrierOperationAddressCapabilityInterface::class),
            resolutionContext: $this->createMock(ShippingAddressResolutionContextInterface::class),
            shippingContext: $this->createMock(ShippingContextInterface::class),
            realtimeContributor: $this->contributor
        );
    }

    private function handoff(
        ?ResolvedShippingAddressInterface $resolved = null,
        array $candidates = [],
        bool $textual = false,
        bool $applicable = true
    ): CarrierAddressHandoffInterface {
        return $this->createConfiguredMock(CarrierAddressHandoffInterface::class, [
            'isApplicable' => $applicable,
            'getResolvedAddress' => $resolved,
            'isTextualFallbackEligible' => $textual,
            'getFailureReason' => $resolved === null ? ShippingFailureReason::CANONICAL_UNRESOLVED : null,
            'getCandidateCodes' => $candidates,
            'getSupportedRepresentations' => [],
        ]);
    }

    private function resolvedAddress(): ResolvedShippingAddressInterface
    {
        return $this->createConfiguredMock(ResolvedShippingAddressInterface::class, ['isResolved' => true]);
    }

    private function origin(?string $countryId): OriginInterface
    {
        return $this->createConfiguredMock(OriginInterface::class, ['getCountryId' => $countryId]);
    }
}
