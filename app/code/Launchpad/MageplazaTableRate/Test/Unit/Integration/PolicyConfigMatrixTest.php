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
use Launchpad\MageplazaTableRate\Model\MemberRatePolicy;
use Launchpad\MageplazaTableRate\Model\MethodSettingsProvider;
use Launchpad\MageplazaTableRate\Plugin\Shipping\CollectRatesPlugin;
use Launchpad\MageplazaTableRate\Model\MethodVisibilityFilter;
use Magento\Framework\App\Config\ScopeConfigInterface;
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
use Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;
use Secomm\ShippingCore\Api\Rate\RealtimeCarrierRateContributorInterface;
use Secomm\ShippingCore\Model\Fallback\SafeDegradationEligibilityPolicy;
use Secomm\ShippingCore\Model\Origin;
use Secomm\ShippingCore\Model\Rate\CarrierEligibilityEvaluator;
use Secomm\ShippingCore\Model\Rate\CarrierRateExecutionRequest;
use Secomm\ShippingCore\Model\Rate\CarrierRateExecutionService;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcomeCollector;
use Secomm\ShippingCore\Model\Address\CanonicalZoneMatcher;
use Secomm\ShippingCore\Model\Address\CanonicalZoneRegistry;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;

/**
 * TASK-SEC-D Phase 5 (r2) — address-policy / provider-config slice of the D3 matrix over the
 * outer collection seam, with DIRECT call-count instrumentation of every seam:
 * legacy policy evaluator, fallback provider, result append — plus contributor counts.
 * Zero legacy-policy calls for a transported member is asserted explicitly.
 */
class PolicyConfigMatrixTest extends TestCase
{
    private CarrierRateOutcomeCollector $collector;

    private MethodSettingsProvider&MockObject $settingsProvider;

    private FallbackRateProvider&MockObject $fallbackProvider;

    private MemberRatePolicy&MockObject $memberRatePolicy;

    private int $legacyPolicyCalls = 0;

    private int $appendCount = 0;

    public int $contributorCalls = 0;

    private ?FallbackCoordinator $coordinator = null;

    protected function setUp(): void
    {
        $this->legacyPolicyCalls = 0;
        $this->appendCount = 0;
        $this->contributorCalls = 0;
        $this->collector = new CarrierRateOutcomeCollector(new NullLogger());
        $this->settingsProvider = $this->getMockBuilder(MethodSettingsProvider::class)
            ->disableOriginalConstructor()->getMock();
        $this->fallbackProvider = $this->getMockBuilder(FallbackRateProvider::class)
            ->disableOriginalConstructor()->getMock();
        $this->memberRatePolicy = $this->getMockBuilder(MemberRatePolicy::class)
            ->disableOriginalConstructor()->getMock();
        $this->memberRatePolicy->method('rateSourceMode')->willReturnCallback(
            function (string $carrier): string {
                $this->legacyPolicyCalls++; // direct legacy-path instrumentation

                return RateSourceMode::CARRIER_WITH_FALLBACK;
            }
        );
        $this->memberRatePolicy->method('addressResolutionPolicy')->willReturn(AddressResolutionPolicy::FALLBACK);
    }

    /**
     * @dataProvider policyMatrixProvider
     * @param array{applicable: bool, resolved: bool, candidates: array<int, string>} $handoffShape
     */
    public function testPolicyAndConfigMatrix(
        string $label,
        string $mode,
        string $policy,
        array $handoffShape,
        ?string $contributorOutcomeStatus,
        ?string $contributorReason,
        int $expectedContributorCalls,
        int $expectedLegacyPolicyCalls,
        int $expectedProviderCalls,
        string $note
    ): void {
        // Wire a fallback group with the GHN member.
        $this->settingsProvider->method('getFallbackMethodIds')->willReturn([10 => 10]);
        $this->settingsProvider->method('getEnabledMembersMap')->willReturn([
            10 => [['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn']],
        ]);
        $rateMethod = $this->createMock(Method::class);
        $rateMethod->method('setPrice')->willReturnCallback(function (): void {
            $this->appendCount++;
        });
        $methodFactory = $this->createMock(MethodFactory::class);
        $methodFactory->method('create')->willReturn($rateMethod);
        $this->fallbackProvider->method('calculate')->willReturnCallback(
            function (): ?\Secomm\ShippingCore\Api\Fallback\FallbackRateInterface {
                $rate = $this->createMock(\Secomm\ShippingCore\Api\Fallback\FallbackRateInterface::class);
                $rate->method('getAmount')->willReturn(20000.0);
                $rate->method('getLabel')->willReturn('Fallback');

                return $rate;
            }
        );

        // Handoff shape per scenario — strict local stand-in for the shared handoff service.
        $handoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $handoff->method('isApplicable')->willReturn($handoffShape['applicable']);
        if ($handoffShape['resolved']) {
            $resolved = $this->createMock(ResolvedShippingAddressInterface::class);
            $resolved->method('isResolved')->willReturn(true);
            $handoff->method('getResolvedAddress')->willReturn($resolved);
        } else {
            $handoff->method('getResolvedAddress')->willReturn(null);
        }
        $handoff->method('getCandidateCodes')->willReturn($handoffShape['candidates']);
        $handoffService = $this->createMock(CarrierAddressHandoffServiceInterface::class);
        $handoffService->method('handoffContextForOperation')->willReturn($handoff);

        $service = new CarrierRateExecutionService(
            new CarrierEligibilityEvaluator(
                new \Secomm\ShippingCore\Model\Address\CanonicalZoneMatcher(),
                new CanonicalZoneRegistry([])
            ),
            $this->originProvider(),
            $handoffService,
            new SafeDegradationEligibilityPolicy()
        );

        $request = new CarrierRateExecutionRequest(
            carrierCode: 'secomm_ghn',
            destinationScope: \Secomm\ShippingCore\Api\Address\DestinationScope::ALL,
            allowedZoneCodes: [],
            destinationProvinceCode: 'VN-01',
            destinationWardCode: null,
            rateSourceMode: $mode,
            addressResolutionPolicy: $policy,
            capability: $this->createMock(\Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface::class),
            resolutionContext: $this->createMock(\Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface::class),
            shippingContext: new \Secomm\ShippingCore\Model\ShippingContext(1, 1, 'secomm_ghn', null, null),
            realtimeContributor: $this->contributorStub($contributorOutcomeStatus, $contributorReason)
        );

        $this->coordinator = new FallbackCoordinator(
            $this->settingsProvider,
            $this->collector,
            new SafeDegradationEligibilityPolicy(),
            $this->memberRatePolicy,
            $this->fallbackProvider,
            $methodFactory,
            $this->createMock(ScopeConfigInterface::class),
            new NullLogger()
        );
        $shipping = $this->createMock(Shipping::class);
        $result = $this->createMock(Result::class);
        // Production Result::$_rates defaults to [] — mirror that neutral empty-rate behavior
        // (freeze-verification B2: the coordinator's nativeMethodIdsIn scan must see [], not null).
        $result->method('getAllRates')->willReturn([]);
        $shipping->method('getResult')->willReturn($result);
        $plugin = new CollectRatesPlugin($this->collector, $this->createMock(MethodVisibilityFilter::class), $this->coordinator);

        // Open the collection bracket exactly like CollectRatesPlugin does in production.
        $this->collector->beginCollection();
        $decision = $service->execute($request);
        $this->collector->recordDecision(
            'secomm_ghn',
            'secomm_ghn',
            $decision->getRealtimeOutcome()
                ?? CarrierRateOutcome::unavailable('GHN_RATE_SKIPPED_FALLBACK_ONLY'),
            $decision->getFallbackEligibility()
        );
        // Production order: visibility filter ran first (no-op for these fixtures), then append.
        $this->coordinator->appendFallbackRates(new \Magento\Quote\Model\Quote\Address\RateRequest(), $result);

        $this->assertSame($expectedContributorCalls, $this->contributorCalls, $note);
        $this->assertSame($expectedLegacyPolicyCalls, $this->legacyPolicyCalls, $note . ' — legacy re-judge calls');
        $this->assertSame($expectedProviderCalls, $this->appendCount, $note . ' — fallback dispatches');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: array, 4: ?string, 5: ?string, 6: int, 7: int, 8: int, 9: string}>
     */
    public function policyMatrixProvider(): array
    {
        $resolved = static fn (): array => ['applicable' => true, 'resolved' => true, 'candidates' => []];
        $ambiguous = static fn (): array => ['applicable' => true, 'resolved' => false, 'candidates' => ['A', 'B']];
        $dropped = static fn (): array => ['applicable' => true, 'resolved' => false, 'candidates' => []];

        return [
            'ambiguous + STRICT' => ['ambiguous + STRICT', RateSourceMode::CARRIER_WITH_FALLBACK, AddressResolutionPolicy::STRICT, $ambiguous(), null, null, 0, 0, 0, 'no GHN, no fallback, zero legacy calls'],
            'ambiguous + FALLBACK' => ['ambiguous + FALLBACK', RateSourceMode::CARRIER_WITH_FALLBACK, AddressResolutionPolicy::FALLBACK, $ambiguous(), null, null, 0, 0, 1, 'legacy-address eligibility, fallback once'],
            'PICK_PRIMARY no primary + carrier-only' => ['PICK_PRIMARY no primary + carrier-only', RateSourceMode::CARRIER_ONLY, AddressResolutionPolicy::PICK_PRIMARY, $dropped(), null, null, 0, 0, 0, 'no realtime, no fallback'],
            'PICK_PRIMARY no primary + with-fallback' => ['PICK_PRIMARY no primary + with-fallback', RateSourceMode::CARRIER_WITH_FALLBACK, AddressResolutionPolicy::PICK_PRIMARY, $dropped(), null, null, 0, 0, 1, 'fallback eligible per frozen contract'],
            'canonical unmapped (textual) reaches realtime' => ['canonical unmapped (textual)', RateSourceMode::CARRIER_ONLY, AddressResolutionPolicy::FALLBACK, $dropped(), 'SUCCESS', '', 1, 0, 0, 'carrier-side textual strategy keeps working'],
        ];
    }

    // ---------------------------------------------------------------- helpers

    private function originProvider(): \Secomm\ShippingCore\Api\OriginProviderInterface
    {
        $mock = $this->createMock(\Secomm\ShippingCore\Api\OriginProviderInterface::class);
        $mock->method('resolve')->willReturn(
            new Origin(null, 'VN', null, null, null, null, null, null, null, null)
        );

        return $mock;
    }

    private function contributorStub(?string $status, ?string $reason): RealtimeCarrierRateContributorInterface
    {
        $self = $this;
        $rate = $this->createMock(\Secomm\ShippingCore\Api\Rate\CarrierRateInterface::class);

        return new class ($self, $status, $reason, $rate) implements RealtimeCarrierRateContributorInterface {
            public function __construct(
                private readonly \PHPUnit\Framework\TestCase $matrix,
                private readonly ?string $status,
                private readonly ?string $reason,
                private readonly \Secomm\ShippingCore\Api\Rate\CarrierRateInterface $rate
            ) {
            }

            public function contribute(string $carrierCode, CarrierAddressHandoffInterface $handoff): \Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface
            {
                $this->matrix->contributorCalls++;

                return match ($this->status) {
                    'SUCCESS' => CarrierRateOutcome::success($this->rate),
                    'TECHNICAL' => CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR),
                    default => CarrierRateOutcome::unavailable($this->reason ?? ShippingFailureReason::SERVICE_UNAVAILABLE),
                };
            }
        };
    }

}
