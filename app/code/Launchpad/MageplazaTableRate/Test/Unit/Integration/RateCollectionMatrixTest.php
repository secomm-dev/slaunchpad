<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Integration;

use Launchpad\MageplazaTableRate\Model\FallbackCoordinator;
use Launchpad\MageplazaTableRate\Model\FallbackRateProvider;
use Launchpad\MageplazaTableRate\Model\FallbackConfigurationException;
use Launchpad\MageplazaTableRate\Model\MemberRatePolicy;
use Launchpad\MageplazaTableRate\Model\MethodSettingsProvider;
use Launchpad\MageplazaTableRate\Plugin\Shipping\CollectRatesPlugin;
use Launchpad\MageplazaTableRate\Model\MethodVisibilityFilter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Shipping;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\DestinationScope;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\OriginInterface;
use Secomm\ShippingCore\Api\OriginProviderInterface;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityResultInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;
use Secomm\ShippingCore\Api\Rate\RealtimeCarrierRateContributorInterface;
use Secomm\ShippingCore\Model\Fallback\ConfigurableFallbackPolicy;
use Secomm\ShippingCore\Model\Fallback\FallbackEligibility;
use Secomm\ShippingCore\Model\Fallback\FallbackRate;
use Secomm\ShippingCore\Model\Fallback\FallbackRateProviderPool;
use Secomm\ShippingCore\Model\Fallback\FallbackRateRequest;
use Secomm\ShippingCore\Model\Fallback\SafeDegradationEligibilityPolicy;
use Secomm\ShippingCore\Model\Origin;
use Secomm\ShippingCore\Model\Rate\CarrierEligibilityEvaluator;
use Secomm\ShippingCore\Model\Rate\CarrierRateExecutionRequest;
use Secomm\ShippingCore\Model\Rate\CarrierRateExecutionService;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcomeCollector;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevel;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\Address\CanonicalZoneMatcher;
use Secomm\ShippingCore\Model\Address\CanonicalZoneRegistry;
use Secomm\ShippingCore\Model\Address\ResolvedShippingAddress;

/**
 * TASK-SEC-D Phase 5 — D3 regression matrix over the OUTER Magento collection seam
 * (`CollectRatesPlugin`), composed with the REAL ShippingCore execution service for the
 * wired GHN member and the REAL coordinator for the legacy Mageplaza member. The fallback
 * provider mock is the only dispatch seam; every case asserts the exact number of
 * contributor/API/fallback calls (never a double execution).
 */
class RateCollectionMatrixTest extends TestCase
{
    private const LEVEL = 'STANDARD';

    private CarrierRateOutcomeCollector $collector;

    private FallbackRateProvider&MockObject $fallbackProvider;

    private MethodSettingsProvider&MockObject $settingsProvider;

    private MemberRatePolicy&MockObject $memberRatePolicy;

    private int $appendCount;

    /** Per-run stub outcome for the GHN realtime contributor (public: read by the test-local anonymous contributor). */
    public ?object $ghnOutcome = null;

    public int $contributorCalls = 0;

    public ?CarrierAddressHandoffInterface $contributorReceivedHandoff = null;

    /** TASK-1WKX9C — how many times the visibility filter ran for the current collect. */
    public int $filterCalls = 0;

    protected function setUp(): void
    {
        $this->appendCount = 0;
        $this->contributorCalls = 0;
        $this->contributorReceivedHandoff = null;
        $this->filterCalls = 0;
        $this->ghnOutcome = null;
        $this->collector = new CarrierRateOutcomeCollector(new NullLogger());
        $this->settingsProvider = $this->getMockBuilder(MethodSettingsProvider::class)
            ->disableOriginalConstructor()->getMock();
        $this->fallbackProvider = $this->getMockBuilder(FallbackRateProvider::class)
            ->disableOriginalConstructor()->getMock();
        $this->memberRatePolicy = $this->getMockBuilder(MemberRatePolicy::class)
            ->disableOriginalConstructor()->getMock();
    }

    // ---------------------------------------------------------------- composition

    private function buildPlugin(array $members, array $configuredModes = []): CollectRatesPlugin
    {
        $this->settingsProvider->method('getFallbackMethodIds')->willReturn(
            isset($members[10]) ? [10 => 10] : []
        );
        $this->settingsProvider->method('getEnabledMembersMap')->willReturn(
            isset($members[10]) ? [10 => $members[10]] : []
        );
        $this->memberRatePolicy->method('rateSourceMode')->willReturnCallback(
            fn (string $c): string => $configuredModes[$c] ?? RateSourceMode::CARRIER_WITH_FALLBACK
        );
        $this->memberRatePolicy->method('addressResolutionPolicy')->willReturn(AddressResolutionPolicy::FALLBACK);
        $this->fallbackProvider->method('calculate')->willReturnCallback(
            function (int $methodId): ?\Secomm\ShippingCore\Api\Fallback\FallbackRateInterface {
                $rate = $this->createMock(\Secomm\ShippingCore\Api\Fallback\FallbackRateInterface::class);
                $rate->method('getAmount')->willReturn(20000.0);
                $rate->method('getLabel')->willReturn('Fallback');

                return $rate;
            }
        );

        $rateMethod = $this->createMock(Method::class);
        $rateMethod->method('setPrice')->willReturnCallback(function ($price): void {
            $this->appendCount++;
        });
        $methodFactory = $this->createMock(MethodFactory::class);
        $methodFactory->method('create')->willReturn($rateMethod);

        $coordinator = new FallbackCoordinator(
            $this->settingsProvider,
            $this->collector,
            new SafeDegradationEligibilityPolicy(),
            $this->memberRatePolicy,
            $this->fallbackProvider,
            $methodFactory,
            $this->createMock(ScopeConfigInterface::class),
            new NullLogger()
        );
        $visibilityFilter = $this->createMock(MethodVisibilityFilter::class);
        $visibilityFilter->method('filter')->willReturnCallback(function (Result $result): void {
            $this->filterCalls++;
            // The real filter hides show_to_customer=0 methods — represented by resetting the
            // result when the fixture says the method was hidden pre-append.
        });

        $shipping = $this->createMock(Shipping::class);
        $result = $this->createMock(Result::class);
        $result->method('getAllRates')->willReturn([]);
        $shipping->method('getResult')->willReturn($result);

        return new CollectRatesPlugin($this->collector, $visibilityFilter, $coordinator);
    }

    /** The production collection bracket around a custom carrier tail. */
    private function collect(CollectRatesPlugin $plugin, callable $carrierTail): void
    {
        $shipping = $this->createMock(Shipping::class);
        $result = $this->createMock(Result::class);
        // Production Result::$_rates defaults to [] — mirror that neutral empty-rate behavior
        // (freeze-verification B2: the coordinator's nativeMethodIdsIn scan must see [], not null).
        $result->method('getAllRates')->willReturn([]);
        $shipping->method('getResult')->willReturn($result);
        $plugin->aroundCollectRates($shipping, function (RateRequest $request) use ($carrierTail): void {
            $carrierTail($request);
        }, new RateRequest());
    }

    /** Simulates the WIRED GHN carrier entry: execution service + decision transport. */
    private function ghnCarrierTail(string $mode, ?object $outcome, ?string $scope = null): \Closure
    {
        $evaluator = new CarrierEligibilityEvaluator(
            new CanonicalZoneMatcher(),
            new CanonicalZoneRegistry([new CanonicalZone('ZONE_HN', 'Hanoi', true, ['VN-01'], [], [])])
        );
        $originProvider = $this->createMock(OriginProviderInterface::class);
        $originProvider->method('resolve')->willReturn(
            new Origin(null, 'VN', null, null, null, null, null, null, null, null)
        );
        $resolvedHandoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $resolvedHandoff->method('isApplicable')->willReturn(true);
        $resolvedHandoff->method('getResolvedAddress')->willReturn(
            $this->createMock(\Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface::class)
        );
        $handoffService = $this->createMock(CarrierAddressHandoffServiceInterface::class);
        $handoffService->method('handoffContextForOperation')->willReturn($resolvedHandoff);
        $service = new CarrierRateExecutionService(
            $evaluator,
            $originProvider,
            $handoffService,
            new SafeDegradationEligibilityPolicy()
        );

        return function () use ($service, $mode, $outcome, $scope): void {
            $request = new CarrierRateExecutionRequest(
                carrierCode: 'secomm_ghn',
                destinationScope: $scope ?? DestinationScope::ALL,
                allowedZoneCodes: [],
                destinationProvinceCode: 'VN-01',
                destinationWardCode: null,
                rateSourceMode: $mode,
                addressResolutionPolicy: AddressResolutionPolicy::FALLBACK,
                capability: $this->createMock(\Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface::class),
                resolutionContext: $this->createMock(\Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface::class),
                shippingContext: new \Secomm\ShippingCore\Model\ShippingContext(1, 1, 'secomm_ghn', null, null),
                realtimeContributor: $this->contributor()
            );
            $decision = $service->execute($request);
            if ($decision->shouldInvokeRealtime() || $decision->getFallbackEligibility()->isEligible()
                || $decision->getReason() === ShippingFailureReason::DESTINATION_NOT_IN_SCOPE) {
                $this->collector->recordDecision(
                    'secomm_ghn',
                    'secomm_ghn',
                    $decision->getRealtimeOutcome()
                        ?? CarrierRateOutcome::unavailable('GHN_RATE_SKIPPED_FALLBACK_ONLY'),
                    $decision->getFallbackEligibility()
                );
            }
        };
    }

    private function contributor(): RealtimeCarrierRateContributorInterface
    {
        $self = $this;

        return new class ($self) implements RealtimeCarrierRateContributorInterface {
            public function __construct(private $matrix)
            {
            }

            public function contribute(string $carrierCode, $handoff): \Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface
            {
                $this->matrix->contributorCalls++;
                $this->matrix->contributorReceivedHandoff = $handoff;

                return $this->matrix->ghnOutcome
                    ?? \Secomm\ShippingCore\Model\Rate\CarrierRateOutcome::unavailable('SERVICE_UNAVAILABLE');
            }
        };
    }

    /** Simulates the LEGACY Mageplaza member: plain outcome record, no transport. */
    private function legacyMemberTail(string $status, string $reason): \Closure
    {
        return function () use ($status, $reason): void {
            $outcome = match ($status) {
                'SUCCESS' => CarrierRateOutcome::success($this->createMock(CarrierRateInterface::class)),
                'TECHNICAL' => CarrierRateOutcome::technicalFailure($reason),
                default => CarrierRateOutcome::unavailable($reason),
            };
            $this->collector->record('mptablerate', 'legacy', $outcome);
        };
    }

    private function ghnOutcome(string $status, string $reason = ''): void
    {
        $this->ghnOutcome = match ($status) {
            'SUCCESS' => CarrierRateOutcome::success($this->createMock(CarrierRateInterface::class)),
            'TECHNICAL' => CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR),
            'MAPPING_MISSING' => CarrierRateOutcome::unavailable(ShippingFailureReason::PROVIDER_MAPPING_MISSING),
            'INVALID_CONFIG' => CarrierRateOutcome::unavailable(ShippingFailureReason::INVALID_CONFIGURATION),
            default => CarrierRateOutcome::unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE),
        };
    }

    private function fallbackMember(): array
    {
        return [['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn']];
    }

    // ---------------------------------------------------------------- matrix

    /**
     * D3 matrix — scalar-config data provider (arrangement happens inside the test body;
     * data providers run before setUp and must not touch instance fixtures).
     *
     * @return array<string, array{0: string, 1: string, 2: string|null, 3: string|null, 4: int, 5: int, 6: string}>
     */
    public function primaryMatrixProvider(): array
    {
        return [
            'GHN success' => ['GHN success', RateSourceMode::CARRIER_WITH_FALLBACK, 'SUCCESS', null, 0, 1, 'fallback suppressed'],
            'CARRIER_ONLY technical' => ['CARRIER_ONLY + technical failure', RateSourceMode::CARRIER_ONLY, 'TECHNICAL', null, 0, 1, 'no fallback'],
            'CARRIER_WITH_FALLBACK technical' => ['CARRIER_WITH_FALLBACK + technical failure', RateSourceMode::CARRIER_WITH_FALLBACK, 'TECHNICAL', null, 1, 1, 'fallback once'],
            'FALLBACK_ONLY' => ['FALLBACK_ONLY', RateSourceMode::FALLBACK_ONLY, null, null, 1, 0, 'no resolution/API, fallback once'],
            'provider mapping missing' => ['provider mapping missing', RateSourceMode::CARRIER_WITH_FALLBACK, 'MAPPING_MISSING', null, 1, 1, 'integration-limitation fallback'],
            'invalid merchant config' => ['invalid merchant config', RateSourceMode::CARRIER_WITH_FALLBACK, 'INVALID_CONFIG', null, 0, 1, 'fail closed'],
            'zones miss' => ['destination outside selected zones', RateSourceMode::CARRIER_WITH_FALLBACK, 'SUCCESS', DestinationScope::SELECTED_ZONES, 0, 0, 'no realtime, no fallback'],
        ];
    }

    /** @dataProvider primaryMatrixProvider */
    public function testMatrix(
        string $label,
        string $mode,
        ?string $outcomeStatus,
        ?string $scope,
        int $expectedProviderCalls,
        int $expectedContributorCalls,
        string $note
    ): void {
        if ($outcomeStatus !== null) {
            $this->ghnOutcome($outcomeStatus);
        }
        $plugin = $this->buildPlugin([10 => $this->fallbackMember()]);
        $this->collect($plugin, $this->ghnCarrierTail($mode, null, $scope));

        $this->assertSame($expectedContributorCalls, $this->contributorCalls, $note);
        $this->assertSame($expectedProviderCalls, $this->appendCount, $note . ' — fallback dispatches');
    }

    public function testSuccessSuppressesLaterTechnicalDuplicate(): void
    {
        $plugin = $this->buildPlugin([10 => $this->fallbackMember()]);
        $this->ghnOutcome('SUCCESS');
        $tail = $this->ghnCarrierTail(RateSourceMode::CARRIER_WITH_FALLBACK, null);
        $this->collect($plugin, function () use ($tail): void {
            $tail();
            // Conflicting duplicate: a later technical failure cannot un-suppress the group.
            $this->collector->recordDecision(
                'secomm_ghn', 'secomm_ghn',
                CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR),
                FallbackEligibility::technical()
            );
        });

        $this->assertSame(0, $this->appendCount, 'Success suppression is terminal');
    }

    public function testExplicitTransportedNoneNeverFallsToLegacyPolicy(): void
    {
        $plugin = $this->buildPlugin([10 => $this->fallbackMember()]);
        $this->collect($plugin, function (): void {
            // Merchant invalid configuration recorded with explicit NONE transport.
            $this->collector->recordDecision(
                'secomm_ghn', 'secomm_ghn',
                CarrierRateOutcome::unavailable(ShippingFailureReason::INVALID_CONFIGURATION),
                FallbackEligibility::none()
            );
        });

        $this->assertSame(0, $this->appendCount, 'Explicit NONE is fail-closed (no legacy re-judge)');
    }

    public function testExceptionInCarrierTailStillClosesTheCollectorBracket(): void
    {
        $plugin = $this->buildPlugin([10 => $this->fallbackMember()]);
        $shipping = $this->createMock(Shipping::class);
        try {
            $plugin->aroundCollectRates($shipping, function (): void {
                $this->collector->recordDecision(
                    'a', 'm', CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'), FallbackEligibility::technical()
                );
                throw new \RuntimeException('carrier boom');
            }, new RateRequest());
            $this->fail('The carrier exception must still propagate (after the bracket closed)');
        } catch (\RuntimeException $e) {
            $this->assertSame('carrier boom', $e->getMessage());
        }

        $this->assertSame([], $this->collector->getDecisionRecords(), 'Bracket cleaned despite exception');
    }

    /**
     * TASK-1WKX9C — a carrier-limited collect is Magento's checkout save/validation
     * re-collect (ShippingInformationManagement / PaymentInformationManagement set
     * limitCarrier). Its result is never rendered, and the fallback-presented rate the
     * customer selected (a hidden method, show_to_customer=0) must survive core's
     * validation: the primary carrier never runs under the limit, so the fallback
     * append cannot re-offer it. Reproduced 2026-09-25: mptablerate_8 → HTTP 404.
     */
    public function testCarrierLimitedCollectNeverStripsHiddenMethods(): void
    {
        $plugin = $this->buildPlugin([10 => $this->fallbackMember()]);
        $shipping = $this->createMock(Shipping::class);
        $result = $this->createMock(Result::class);
        $result->method('getAllRates')->willReturn([]);
        $shipping->method('getResult')->willReturn($result);
        $request = new RateRequest();
        $request->setLimitCarrier('mptablerate');

        $plugin->aroundCollectRates($shipping, function (): void {
            // Only the limited carrier runs — no primary-carrier outcome is recorded.
        }, $request);

        $this->assertSame(0, $this->filterCalls, 'Hidden-method stripping is customer-facing only — never on a limited collect');
    }

    /** TASK-1WKX9C — customer-facing collects (no limitCarrier) keep stripping hidden methods. */
    public function testCustomerFacingCollectStillStripsHiddenMethods(): void
    {
        $plugin = $this->buildPlugin([10 => $this->fallbackMember()]);
        $shipping = $this->createMock(Shipping::class);
        $result = $this->createMock(Result::class);
        $result->method('getAllRates')->willReturn([]);
        $shipping->method('getResult')->willReturn($result);

        $plugin->aroundCollectRates($shipping, function (): void {
        }, new RateRequest());

        $this->assertSame(1, $this->filterCalls, 'Customer-facing collects strip show_to_customer=0 methods');
    }

    private function fallbackRequest(): FallbackRateRequest
    {
        return new FallbackRateRequest('VN', null, null, 1.0, 150000.0, 1.0, 1);
    }
}
