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
use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\DestinationContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\DestinationScope;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Fallback\FallbackRateProviderInterface;
use Secomm\ShippingCore\Api\OriginInterface;
use Secomm\ShippingCore\Api\OriginProviderInterface;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityResultInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;
use Secomm\ShippingCore\Api\Rate\RealtimeCarrierRateContributorInterface;
use Secomm\ShippingCore\Api\ServiceLevelRateDecisionInterface;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\Address\CanonicalZoneMatcher;
use Secomm\ShippingCore\Model\Address\CanonicalZoneRegistry;
use Secomm\ShippingCore\Model\Address\CarrierAddressHandoffService;
use Secomm\ShippingCore\Model\Fallback\ConfigurableFallbackPolicy;
use Secomm\ShippingCore\Model\Fallback\FallbackRate;
use Secomm\ShippingCore\Model\Fallback\FallbackRateProviderPool;
use Secomm\ShippingCore\Model\Fallback\FallbackRateRequest;
use Secomm\ShippingCore\Model\Fallback\SafeDegradationEligibilityPolicy;
use Secomm\ShippingCore\Model\Origin;
use Secomm\ShippingCore\Model\Rate\CarrierEligibilityEvaluator;
use Secomm\ShippingCore\Model\Rate\CarrierRateExecutionRequest;
use Secomm\ShippingCore\Model\Rate\CarrierRateExecutionService;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\ShippingCore\Model\ServiceLevel\ServiceLevelRateAggregator;
use Secomm\ShippingCore\Model\ServiceLevel\ServiceLevelRateOrchestrator;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevel;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevelRegistry;
use Secomm\ShippingCore\Model\Address\ShippingAddressResolutionContext;
use Secomm\ShippingCore\Model\Address\ShippingAddressResolutionManager;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Api\VnPrimaryCandidateSelectorInterface;
use Secomm\VietNamAddress\Model\Data\VnAddressUnitData;
use Secomm\VietNamAddress\Model\Data\VnPrimaryCandidateSelection;
use Secomm\VietNamAddress\Model\MappingCandidateFinder;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Secomm\VietNamAddress\Model\VnAdminAddressResolver;

/**
 * TASK-8MQHJX (Phase D) — END-TO-END runtime flow over REAL ShippingCore + REAL
 * Secomm_VietNamAddress implementations (only the reference/HTTP layer is mocked):
 *
 *   CarrierEligibility → RateSourceMode → origin readiness → AddressResolutionPolicy
 *   (via the REAL handoff service over the REAL resolution manager/resolver)
 *   → realtime contributor → contribution → ServiceLevelRateAggregator
 *   → ServiceLevelRateOrchestrator (final owner: aggregation + suppression + fallback dispatch).
 *
 * Proves the §5 case matrix, §6 address-policy paths, §7 realtime-success suppression,
 * §9 INTEGRATION_LIMITATION mapping and §8 orchestration ownership — with ZERO parallel
 * orchestration path: the fallback provider mock is the only dispatch seam and it is owned by
 * the orchestrator.
 */
class CarrierRateExecutionFlowIntegrationTest extends TestCase
{
    private const PRE = VnSchemes::VN_ADMIN_PRE_2025;
    private const CURRENT = VnSchemes::VN_ADMIN_2025;
    private const SOURCE_PRE_UNIT = 'VNAP25-OLD111111';
    private const MERGED_CURRENT_UNIT = 'VNA25-MERGED1';
    private const LEVEL = 'STANDARD';

    private CarrierAddressHandoffService $handoffService;
    private CarrierRateExecutionService $executionService;
    private ServiceLevelRateAggregator $aggregator;
    private ServiceLevelRateOrchestrator $orchestrator;
    private OriginProviderInterface&MockObject $originProvider;
    private VnPrimaryCandidateSelectorInterface&MockObject $selector;
    private FallbackRateProviderInterface&MockObject $fallbackProvider;
    /** @var array<int, array{carrier: string, candidates: string[], resolved: bool}> */
    private array $realtimeLog;

    protected function setUp(): void
    {
        $this->realtimeLog = [];

        // --- canonical resolution: REAL resolver, only the reference layer is mocked ---
        $unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitData =>
                ($scheme === self::PRE && $code === self::SOURCE_PRE_UNIT)
                || ($scheme === self::CURRENT && $code === self::MERGED_CURRENT_UNIT)
                    ? new VnAddressUnitData($scheme, $code, 'VNAP25-DISTRICT0', 'VN-01', 3, 'Phường Test', 'Test Ward')
                    : null
        );
        $candidateFinder = $this->createMock(MappingCandidateFinder::class);
        $candidateFinder->method('find')->willReturnCallback(
            // Reverse of a merge: the CURRENT unit merges TWO historical PRE units → AMBIGUOUS.
            fn (string $scheme, string $code, string $target): array =>
                $scheme === self::CURRENT && $code === self::MERGED_CURRENT_UNIT && $target === self::PRE
                    ? [
                        ['code' => 'VNAP25-B2B2B2B2B2', 'relation_type' => 'MERGED_INTO', 'direction' => 'incoming'],
                        ['code' => 'VNAP25-A1A1A1A1A1', 'relation_type' => 'MERGED_INTO', 'direction' => 'incoming'],
                    ]
                    : []
        );
        $manager = new ShippingAddressResolutionManager(new VnAdminAddressResolver($unitProvider, $candidateFinder));

        $this->selector = $this->createMock(VnPrimaryCandidateSelectorInterface::class);
        $this->handoffService = new CarrierAddressHandoffService(
            $this->createMock(DestinationContextBuilderInterface::class),
            $manager,
            $this->selector
        );

        // --- eligibility: REAL evaluator + REAL matcher + REAL registry ---
        $zones = new CanonicalZoneRegistry([
            new CanonicalZone('ZONE_VN01', 'Hanoi area', true, ['VN-01'], [], []),
            new CanonicalZone('ZONE_VN02', 'Other province', true, ['VN-02'], [], []),
        ]);
        $evaluator = new CarrierEligibilityEvaluator(new CanonicalZoneMatcher(), $zones);

        // --- execution: REAL service over the REAL shared eligibility policy ---
        $this->originProvider = $this->createMock(OriginProviderInterface::class);
        $this->originProvider->method('resolve')->willReturn(
            new Origin(null, 'VN', null, null, null, null, null, null, null, null)
        );
        $this->executionService = new CarrierRateExecutionService(
            $evaluator,
            $this->originProvider,
            $this->handoffService,
            new SafeDegradationEligibilityPolicy()
        );

        // --- service-level orchestration: REAL aggregator + REAL orchestrator (final owner) ---
        $levelRegistry = new ShippingServiceLevelRegistry([
            new ShippingServiceLevel(self::LEVEL, 'Standard delivery', true, 0),
        ]);
        $this->fallbackProvider = $this->createMock(FallbackRateProviderInterface::class);
        $this->orchestrator = new ServiceLevelRateOrchestrator(
            $levelRegistry,
            new ConfigurableFallbackPolicy([self::LEVEL => true]),
            new FallbackRateProviderPool([$this->fallbackProvider])
        );
        $this->aggregator = new ServiceLevelRateAggregator($levelRegistry);
    }

    // ---------------------------------------------------------------- §5 case matrix

    public function testCaseA_AllScopeCarrierOnlyInvokesRealtimeAndNeverFallback(): void
    {
        $this->fallbackProvider->expects($this->never())->method('getRate');

        $decision = $this->executionService->execute(
            $this->request('carrier-a', RateSourceMode::CARRIER_ONLY, outcome: $this->success())
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $this->assertCount(1, $this->realtimeLog, 'Realtime contributor must be invoked exactly once');
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_REALTIME, $final->getSource());
        $this->assertTrue($final->isAvailable());
    }

    public function testCaseB_SelectedZonesMatchAllowsRealtimePath(): void
    {
        $decision = $this->executionService->execute(
            $this->request(
                'carrier-a',
                RateSourceMode::CARRIER_ONLY,
                scope: DestinationScope::SELECTED_ZONES,
                zones: ['ZONE_VN01'],
                outcome: $this->success()
            )
        );

        $this->assertTrue($decision->shouldInvokeRealtime());
        $this->assertCount(1, $this->realtimeLog);
        $this->assertNull($decision->getReason());
    }

    public function testCaseC_SelectedZonesMissStopsEverythingIncludingFallback(): void
    {
        $this->fallbackProvider->expects($this->never())->method('getRate');

        $decision = $this->executionService->execute(
            $this->request(
                'carrier-a',
                RateSourceMode::CARRIER_WITH_FALLBACK,
                scope: DestinationScope::SELECTED_ZONES,
                zones: ['ZONE_VN02']
            )
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $this->assertCount(0, $this->realtimeLog, 'Realtime must never run for a zone miss');
        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertNull($decision->getCarrierFacingHandoff());
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $final->getSource());
    }

    public function testCaseD_FallbackOnlyEligibleLetsOrchestratorDispatchFallback(): void
    {
        // The fallback provider is dispatched by the ORCHESTRATOR, never by the execution layer.
        $this->fallbackProvider->expects($this->once())->method('getRate')
            ->willReturn(new FallbackRate(25000.0, 'Emergency table rate'));
        $this->originProvider->expects($this->never())->method('resolve');

        $decision = $this->executionService->execute($this->request('carrier-a', RateSourceMode::FALLBACK_ONLY));
        $final = $this->runFlow($decision, 'carrier-a');

        $this->assertCount(0, $this->realtimeLog, 'FALLBACK_ONLY must never reach the carrier');
        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertNull($decision->getCarrierFacingHandoff());
        $this->assertTrue($decision->getFallbackEligibility()->hasLegacyAddressFallbackEligibility());
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_FALLBACK, $final->getSource());
        $this->assertSame(25000.0, $final->getFallbackRate()?->getAmount());
    }

    public function testCaseE_FallbackOnlyIneligibleContributesNothing(): void
    {
        $this->fallbackProvider->expects($this->never())->method('getRate');
        $this->originProvider->expects($this->never())->method('resolve');

        $decision = $this->executionService->execute(
            $this->request(
                'carrier-a',
                RateSourceMode::FALLBACK_ONLY,
                scope: DestinationScope::SELECTED_ZONES,
                zones: ['ZONE_VN02']
            )
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $final->getSource());
    }

    public function testCaseF_TechnicalFailureRealtimeFirstThenOrchestratorDispatchesFallback(): void
    {
        $this->fallbackProvider->expects($this->once())->method('getRate')
            ->willReturn(new FallbackRate(18000.0, 'Fallback'));

        $decision = $this->executionService->execute(
            $this->request('carrier-a', RateSourceMode::CARRIER_WITH_FALLBACK, outcome: $this->technical())
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $this->assertTrue($decision->shouldInvokeRealtime(), 'Realtime must run FIRST');
        $this->assertTrue($decision->getFallbackEligibility()->hasTechnicalFallbackEligibility());
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_FALLBACK, $final->getSource());
    }

    public function testCaseG_ProviderMappingMissingKeepsOutcomeUnavailableWithIntegrationLimitation(): void
    {
        $this->fallbackProvider->expects($this->once())->method('getRate')
            ->willReturn(new FallbackRate(21000.0, 'Fallback'));

        $decision = $this->executionService->execute(
            $this->request(
                'carrier-a',
                RateSourceMode::CARRIER_WITH_FALLBACK,
                outcome: CarrierRateOutcome::unavailable(ShippingFailureReason::PROVIDER_MAPPING_MISSING)
            )
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $outcome = $decision->getRealtimeOutcome();
        $this->assertNotNull($outcome);
        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $outcome->getStatus(), 'Never reclassified');
        $this->assertTrue($decision->getFallbackEligibility()->hasIntegrationLimitationEligibility());
        // v10 §35.5: mapping-missing eligibility must reach the FINAL fallback owner.
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_FALLBACK, $final->getSource());
    }

    public function testCaseH_MerchantInvalidConfigurationFailsClosedEndToEnd(): void
    {
        $this->fallbackProvider->expects($this->never())->method('getRate');

        $decision = $this->executionService->execute(
            $this->request(
                'carrier-a',
                RateSourceMode::CARRIER_WITH_FALLBACK,
                outcome: CarrierRateOutcome::unavailable(ShippingFailureReason::INVALID_CONFIGURATION)
            )
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame([], $decision->getFallbackEligibility()->getSources());
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $final->getSource());
    }

    // ---------------------------------------------------------------- §6 address policies (real handoff + real resolver)

    public function testStrictAmbiguousEndToEndNoRealtimeNoFallback(): void
    {
        $this->fallbackProvider->expects($this->never())->method('getRate');
        $this->selector->expects($this->never())->method('selectPrimary');

        $decision = $this->executionService->execute(
            $this->request(
                'carrier-a',
                RateSourceMode::CARRIER_WITH_FALLBACK,
                sourceScheme: self::CURRENT,
                sourceUnit: self::MERGED_CURRENT_UNIT,
                targetScheme: self::PRE,
                policy: AddressResolutionPolicy::STRICT
            )
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $this->assertCount(0, $this->realtimeLog);
        $this->assertFalse($decision->shouldInvokeRealtime());
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $final->getSource());
    }

    public function testFallbackPolicyAmbiguousEndToEndOrchestratorDispatchesFallback(): void
    {
        $this->fallbackProvider->expects($this->once())->method('getRate')
            ->willReturn(new FallbackRate(19000.0, 'Legacy fallback'));

        $decision = $this->executionService->execute(
            $this->request(
                'carrier-a',
                RateSourceMode::CARRIER_WITH_FALLBACK,
                sourceScheme: self::CURRENT,
                sourceUnit: self::MERGED_CURRENT_UNIT,
                targetScheme: self::PRE
            )
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $handoff = $decision->getCarrierFacingHandoff();
        $this->assertNotNull($handoff);
        $this->assertNull($handoff->getResolvedAddress());
        $this->assertCount(2, $handoff->getCandidateCodes(), 'AMBIGUOUS shape retains candidates for the policy consumer');
        $this->assertCount(0, $this->realtimeLog, 'AMBIGUOUS must not reach the realtime contributor');
        $this->assertTrue($decision->getFallbackEligibility()->hasLegacyAddressFallbackEligibility());
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_FALLBACK, $final->getSource());
    }

    public function testPickPrimarySuccessEndToEndCarrierReceivesOnlySelectedDestination(): void
    {
        $this->selector->expects($this->once())->method('selectPrimary')
            ->willReturn(new VnPrimaryCandidateSelection(VnPrimaryCandidateSelection::STATUS_SELECTED, 'VNAP25-A1A1A1A1A1', 2));

        $decision = $this->executionService->execute(
            $this->request(
                'carrier-a',
                RateSourceMode::CARRIER_ONLY,
                sourceScheme: self::CURRENT,
                sourceUnit: self::MERGED_CURRENT_UNIT,
                targetScheme: self::PRE,
                policy: AddressResolutionPolicy::PICK_PRIMARY,
                outcome: $this->success()
            )
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $this->assertCount(1, $this->realtimeLog);
        $this->assertTrue($this->realtimeLog[0]['resolved'], 'Carrier receives ONE resolved canonical destination');
        $this->assertSame([], $this->realtimeLog[0]['candidates'], 'No candidate list / order / rank crosses the boundary');
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_REALTIME, $final->getSource());
    }

    public function testPickPrimaryFailureCarrierOnlyEndToEndNoFallback(): void
    {
        $this->fallbackProvider->expects($this->never())->method('getRate');
        $this->selector->method('selectPrimary')
            ->willReturn(new VnPrimaryCandidateSelection(VnPrimaryCandidateSelection::STATUS_NO_DESIGNATED_PRIMARY, null, 2));

        $decision = $this->executionService->execute(
            $this->request(
                'carrier-a',
                RateSourceMode::CARRIER_ONLY,
                sourceScheme: self::CURRENT,
                sourceUnit: self::MERGED_CURRENT_UNIT,
                targetScheme: self::PRE,
                policy: AddressResolutionPolicy::PICK_PRIMARY
            )
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $this->assertCount(0, $this->realtimeLog);
        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $final->getSource());
    }

    public function testPickPrimaryFailureWithFallbackModeReachesFallbackThroughOrchestrator(): void
    {
        $this->fallbackProvider->expects($this->once())->method('getRate')
            ->willReturn(new FallbackRate(20000.0, 'Fallback'));
        $this->selector->method('selectPrimary')
            ->willReturn(new VnPrimaryCandidateSelection(VnPrimaryCandidateSelection::STATUS_NO_DESIGNATED_PRIMARY, null, 2));

        $decision = $this->executionService->execute(
            $this->request(
                'carrier-a',
                RateSourceMode::CARRIER_WITH_FALLBACK,
                sourceScheme: self::CURRENT,
                sourceUnit: self::MERGED_CURRENT_UNIT,
                targetScheme: self::PRE,
                policy: AddressResolutionPolicy::PICK_PRIMARY
            )
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $this->assertCount(0, $this->realtimeLog);
        $this->assertTrue($decision->getFallbackEligibility()->hasLegacyAddressFallbackEligibility());
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_FALLBACK, $final->getSource());
    }

    // ---------------------------------------------------------------- §7 realtime-success suppression (multi-contributor)

    public function testRealtimeSuccessSuppressesFallbackAcrossCarriers(): void
    {
        // Carrier A: fallback-eligible technical failure. Carrier B: realtime SUCCESS.
        $decisionA = $this->executionService->execute(
            $this->request('carrier-a', RateSourceMode::CARRIER_WITH_FALLBACK, outcome: $this->technical())
        );
        $decisionB = $this->executionService->execute(
            $this->request('carrier-b', RateSourceMode::CARRIER_WITH_FALLBACK, outcome: $this->success())
        );

        $this->fallbackProvider->expects($this->never())->method('getRate');

        $outcomes = [];
        foreach (['carrier-a' => $decisionA, 'carrier-b' => $decisionB] as $carrier => $decision) {
            $outcome = $decision->getRealtimeOutcome();
            $this->assertNotNull($outcome);
            $outcomes[$carrier] = $outcome;
        }
        $aggregate = $this->aggregator->aggregate(self::LEVEL, $outcomes, $decisionA->getFallbackEligibility());
        $final = $this->orchestrator->decide(
            self::LEVEL,
            $aggregate,
            $this->fallbackRequest(),
            $decisionA->getFallbackEligibility()
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_REALTIME, $final->getSource());
        $this->assertArrayHasKey('carrier-b', $final->getRealtimeRates());
        $this->assertTrue($final->isAvailable());
    }

    // ---------------------------------------------------------------- §9 INTEGRATION_LIMITATION regression (E2E)

    public function testCarrierOnlyMappingMissingNeverDegrades(): void
    {
        $this->fallbackProvider->expects($this->never())->method('getRate');

        $decision = $this->executionService->execute(
            $this->request(
                'carrier-a',
                RateSourceMode::CARRIER_ONLY,
                outcome: CarrierRateOutcome::unavailable(ShippingFailureReason::PROVIDER_MAPPING_MISSING)
            )
        );
        $final = $this->runFlow($decision, 'carrier-a');

        $this->assertFalse($decision->getFallbackEligibility()->isEligible());
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $final->getSource());
    }

    // ---------------------------------------------------------------- fixtures & helpers

    private function request(
        string $carrierCode,
        string $mode,
        string $scope = DestinationScope::ALL,
        array $zones = [],
        string $sourceScheme = self::PRE,
        string $sourceUnit = self::SOURCE_PRE_UNIT,
        string $targetScheme = self::PRE,
        string $policy = AddressResolutionPolicy::FALLBACK,
        ?CarrierRateOutcomeInterface $outcome = null
    ): CarrierRateExecutionRequest {
        $capability = new readonly class (self::PRE, $targetScheme) implements CarrierOperationAddressCapabilityInterface {
            public function __construct(
                private readonly string $declaredScheme,
                private readonly string $contextScheme
            ) {
            }

            public function getRequiredScheme(string $operation): string
            {
                return $this->contextScheme;
            }

            public function getSupportedRepresentations(string $operation): array
            {
                return ['UNIT_ID'];
            }

            public function supportsTextualFallback(string $operation): bool
            {
                return false;
            }
        };

        $context = new ShippingAddressResolutionContext(
            countryId: 'VN',
            sourceScheme: $sourceScheme,
            sourceUnitCode: $sourceUnit,
            targetScheme: $targetScheme
        );

        return new CarrierRateExecutionRequest(
            carrierCode: $carrierCode,
            destinationScope: $scope,
            allowedZoneCodes: $zones,
            destinationProvinceCode: 'VN-01',
            destinationWardCode: null,
            rateSourceMode: $mode,
            addressResolutionPolicy: $policy,
            capability: $capability,
            resolutionContext: $context,
            shippingContext: new \Secomm\ShippingCore\Model\ShippingContext(1, 1, $carrierCode, null, null),
            realtimeContributor: $this->contributor($outcome ?? $this->success())
        );
    }

    /**
     * Boundary-recording contributor: proves WHAT crosses to the carrier (final handoff only)
     * and that exactly one canonical destination — never a candidate list — is delivered.
     */
    private function contributor(CarrierRateOutcomeInterface $outcome): RealtimeCarrierRateContributorInterface
    {
        $log = &$this->realtimeLog;
        $captured = $outcome;

        return new class ($captured, $log) implements RealtimeCarrierRateContributorInterface {
            private CarrierRateOutcomeInterface $outcome;

            /** @var array<int, array{carrier: string, candidates: string[], resolved: bool}> */
            private array $log;

            public function __construct(CarrierRateOutcomeInterface $outcome, array &$log)
            {
                $this->outcome = $outcome;
                $this->log = &$log;
            }

            public function contribute(
                string $carrierCode,
                CarrierAddressHandoffInterface $handoff
            ): CarrierRateOutcomeInterface {
                $this->log[] = [
                    'carrier' => $carrierCode,
                    'candidates' => $handoff->getCandidateCodes(),
                    'resolved' => $handoff->getResolvedAddress() !== null,
                ];

                return $this->outcome;
            }
        };
    }

    /** Feed ONE decision into the EXISTING orchestration chain (aggregator → orchestrator). */
    private function runFlow(
        \Secomm\ShippingCore\Api\Rate\CarrierRateExecutionDecisionInterface $decision,
        string $carrierCode
    ): ServiceLevelRateDecisionInterface {
        $outcomes = [];
        $outcome = $decision->getRealtimeOutcome();
        if ($outcome !== null) {
            $outcomes[$carrierCode] = $outcome;
        }
        $eligibility = $decision->getFallbackEligibility();
        $aggregate = $this->aggregator->aggregate(
            self::LEVEL,
            $outcomes,
            $eligibility->isEligible() ? $eligibility : null
        );

        return $this->orchestrator->decide(
            self::LEVEL,
            $aggregate,
            $this->fallbackRequest(),
            $eligibility->isEligible() ? $eligibility : null
        );
    }

    private function fallbackRequest(): FallbackRateRequest
    {
        return new FallbackRateRequest('VN', null, null, 1.0, 150000.0, 1.0, 1);
    }

    private function success(): CarrierRateOutcomeInterface
    {
        return CarrierRateOutcome::success($this->createMock(CarrierRateInterface::class));
    }

    private function technical(): CarrierRateOutcomeInterface
    {
        return CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR);
    }
}
