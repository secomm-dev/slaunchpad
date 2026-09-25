<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Secomm\Ghn\Model\Capability\GhnAddressCapability;
use Secomm\Ghn\Model\Carrier\Ghn;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Rate\EstimatedPackage;
use Secomm\Ghn\Model\Rate\GhnRateAdjuster;
use Secomm\Ghn\Model\Rate\GhnRateCalculator;
use Secomm\Ghn\Model\Rate\GhnRateQuery;
use Secomm\Ghn\Model\Rate\GhnRateRequestMapper;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimate;
use Secomm\Ghn\Model\Rate\RealtimeRateContributorFactory;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRegistryInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\DestinationScope;
use Secomm\ShippingCore\Api\Address\DestinationContextBuilderInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Rate\CarrierRateExecutionRequestInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateExecutionServiceInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeCollectorInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\Address\CanonicalZoneMatcher;
use Secomm\ShippingCore\Model\Address\CanonicalZoneRegistry;
use Secomm\ShippingCore\Model\Address\CarrierAddressHandoffService;
use Secomm\ShippingCore\Model\Address\RuntimeAddressContextBuilder;
use Secomm\ShippingCore\Model\Address\ShippingAddressResolutionManager;
use Secomm\ShippingCore\Model\Fallback\SafeDegradationEligibilityPolicy;
use Secomm\ShippingCore\Model\Rate\CarrierEligibilityEvaluator;
use Secomm\ShippingCore\Model\Rate\CarrierRate;
use Secomm\ShippingCore\Model\Rate\CarrierRateExecutionService;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\ShippingCore\Model\ShippingContextFactory;
use Secomm\VietNamAddress\Api\VnOperationalAddressResolverInterface;
use Secomm\VietNamAddress\Api\VnOperationalNameResolverInterface;
use Secomm\VietNamAddress\Model\Data\VnAddressUnitData;
use Secomm\VietNamAddress\Model\Data\VnOperationalIdentityData;
use Secomm\VietNamAddress\Model\Data\VnOperationalNameResolutionData;
use Secomm\VietNamAddress\Model\Data\VnOperationalResolutionData;
use Secomm\VietNamAddress\Model\MappingCandidateFinder;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Secomm\VietNamAddress\Model\VnAdminAddressResolver;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — REAL §29 matrix: the production carrier entry behind a
 * REAL shared execution chain (CarrierRateExecutionService + CarrierEligibilityEvaluator +
 * CanonicalZoneMatcher + zone registry + RuntimeAddressContextBuilder + REAL handoff over the
 * REAL VnAdminAddressResolver). The GHN calculator mock is the API-call counter:
 *
 *   ALL / zero zones            → realtime runs (1 API call), method priced
 *   SELECTED_ZONES hit          → realtime runs (1 API call)
 *   SELECTED_ZONES miss         → 0 contributor, 0 API call, outcome DESTINATION_NOT_IN_SCOPE
 *   SELECTED_ZONES + no zones   → ineligible (fail closed) + same outcome
 *   FALLBACK_ONLY eligible      → skip outcome (composition opens fallback)
 *   FALLBACK_ONLY + miss        → DESTINATION_NOT_IN_SCOPE (fallback must NOT open — §20)
 */
class GhnZoneExecutionTest extends TestCase
{
    private const ZONE_MATCH = 'ZONE_VN01';

    private const ZONE_OTHER = 'ZONE_VN79';

    private const WARD_2025 = 'VNA25-WARD001';

    private const WARD_PRE_2025 = 'VNAP25-TARGET01';

    private ScopeConfigInterface&MockObject $scopeConfig;

    private GhnRateCalculator&MockObject $rateCalculator;

    private GhnRateRequestMapper&MockObject $requestMapper;

    private CarrierRateOutcomeCollectorInterface&MockObject $outcomeCollector;

    /** @var CarrierRateOutcomeInterface[] */
    private array $recordedOutcomes = [];

    private int $apiCalls = 0;

    private CanonicalZoneRegistry $zones;

    private Ghn $carrier;

    protected function setUp(): void
    {
        $this->recordedOutcomes = [];
        $this->apiCalls = 0;
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->zones = new CanonicalZoneRegistry([
            new CanonicalZone(self::ZONE_MATCH, 'Hanoi area', true, ['VN-01'], [], []),
            new CanonicalZone(self::ZONE_OTHER, 'HCMC area', true, ['VN-79'], [], []),
        ]);
    }

    // ---------- §29 scenario matrix ----------

    public function testAllScopeWithZeroZonesKeepsRealtimeBehavior(): void
    {
        $this->carrier = $this->buildCarrier(DestinationScope::ALL, [], RateSourceMode::CARRIER_WITH_FALLBACK);
        $this->rateCalculator->expects($this->once())->method('quoteWithHandoff')->willReturn(
            CarrierRateOutcome::success(new CarrierRate(25000.0, 'VND'))
        );

        $result = $this->carrier->collectRates($this->vnRequest());

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(1, $this->apiCalls, 'ALL scope must reach the realtime provider exactly once.');
        $this->assertCount(1, $this->recordedOutcomes);
        $this->assertTrue($this->recordedOutcomes[0]->isSuccessful());
    }

    public function testSelectedZonesMatchRunsRealtime(): void
    {
        $this->carrier = $this->buildCarrier(
            DestinationScope::SELECTED_ZONES,
            [self::ZONE_MATCH],
            RateSourceMode::CARRIER_WITH_FALLBACK
        );
        $this->rateCalculator->expects($this->once())->method('quoteWithHandoff')->willReturn(
            CarrierRateOutcome::success(new CarrierRate(25000.0, 'VND'))
        );

        $result = $this->carrier->collectRates($this->vnRequest());

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(1, $this->apiCalls);
    }

    public function testSelectedZonesMissStopsEverythingBeforeMappingAndApi(): void
    {
        $this->carrier = $this->buildCarrier(
            DestinationScope::SELECTED_ZONES,
            [self::ZONE_OTHER],
            RateSourceMode::CARRIER_WITH_FALLBACK
        );
        $this->rateCalculator->expects($this->never())->method('quoteWithHandoff');

        $result = $this->carrier->collectRates($this->vnRequest());

        $this->assertFalse($result, 'Zone miss must hide the method.');
        $this->assertSame(0, $this->apiCalls, '§29: 0 contributor, 0 API calls on a zone miss.');
        $this->assertCount(1, $this->recordedOutcomes);
        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $this->recordedOutcomes[0]->getStatus());
        $this->assertSame(ShippingFailureReason::DESTINATION_NOT_IN_SCOPE, $this->recordedOutcomes[0]->getFailureReason());
    }

    public function testSelectedZonesWithNoConfiguredZonesFailsClosed(): void
    {
        $this->carrier = $this->buildCarrier(
            DestinationScope::SELECTED_ZONES,
            [],
            RateSourceMode::CARRIER_WITH_FALLBACK
        );

        $result = $this->carrier->collectRates($this->vnRequest());

        $this->assertFalse($result);
        $this->assertSame(0, $this->apiCalls);
        $this->assertSame(
            ShippingFailureReason::DESTINATION_NOT_IN_SCOPE,
            $this->recordedOutcomes[0]->getFailureReason()
        );
    }

    public function testFallbackOnlyEligibleReportsSkipAndLeavesFallbackToComposition(): void
    {
        $this->carrier = $this->buildCarrier(
            DestinationScope::ALL,
            [],
            RateSourceMode::FALLBACK_ONLY
        );

        $result = $this->carrier->collectRates($this->vnRequest());

        $this->assertFalse($result);
        $this->assertSame(0, $this->apiCalls);
        $this->assertSame(
            Ghn::REASON_RATE_SKIPPED_FALLBACK_ONLY,
            $this->recordedOutcomes[0]->getFailureReason()
        );
    }

    public function testFallbackOnlyIneligibleReportsDestinationNotInScope(): void
    {
        $this->carrier = $this->buildCarrier(
            DestinationScope::SELECTED_ZONES,
            [self::ZONE_OTHER],
            RateSourceMode::FALLBACK_ONLY
        );

        $result = $this->carrier->collectRates($this->vnRequest());

        $this->assertFalse($result);
        $this->assertSame(0, $this->apiCalls);
        $this->assertSame(
            ShippingFailureReason::DESTINATION_NOT_IN_SCOPE,
            $this->recordedOutcomes[0]->getFailureReason(),
            '§20: an eligibility miss must surface the reason the coordinator guard keys on.'
        );
    }

    // ---------- composition ----------

    private function buildCarrier(
        string $destinationScope,
        array $allowedZoneCodes,
        string $rateSourceMode
    ): Ghn {
        // --- config: mocked shared reader delegates (GhnConfig owns the carrier paths) ---
        $ghnConfig = $this->createMock(\Secomm\Ghn\Model\Config::class);
        $ghnConfig->method('getDestinationScope')->willReturn($destinationScope);
        $ghnConfig->method('getAllowedZoneCodes')->willReturn($allowedZoneCodes);
        $ghnConfig->method('getRateSourceMode')->willReturn($rateSourceMode);
        $ghnConfig->method('getAddressResolutionPolicy')->willReturn(
            \Secomm\ShippingCore\Api\Address\AddressResolutionPolicy::FALLBACK
        );

        // --- canonical identity bridges (id → region-only; name → ward) ---
        $provinceIdentity = new VnOperationalIdentityData(VnSchemes::VN_ADMIN_2025, 'VN-01', 1, 'VN-01');
        $wardIdentity = new VnOperationalIdentityData(VnSchemes::VN_ADMIN_2025, self::WARD_2025, 2, 'VN-01');
        $operationalAddressResolver = $this->createMock(VnOperationalAddressResolverInterface::class);
        $operationalAddressResolver->method('resolveFromRuntime')->willReturn(
            VnOperationalResolutionData::resolved($provinceIdentity)
        );
        $nameResolver = $this->createMock(VnOperationalNameResolverInterface::class);
        $nameResolver->method('resolveWardByName')->willReturn(
            VnOperationalNameResolutionData::exact($wardIdentity)
        );
        $runtimeContextBuilder = new RuntimeAddressContextBuilder($operationalAddressResolver, $nameResolver);

        // --- REAL handoff over the REAL resolver: the 2025 ward maps to ONE PRE-2025 unit ---
        $unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitData => match (true) {
                $scheme === VnSchemes::VN_ADMIN_2025 && $code === self::WARD_2025 => new VnAddressUnitData(
                    $scheme,
                    $code,
                    'VNA25-PARENT0',
                    'VN-01',
                    2,
                    'Phường Test',
                    'Test Ward'
                ),
                $scheme === VnSchemes::VN_ADMIN_PRE_2025 && $code === self::WARD_PRE_2025 => new VnAddressUnitData(
                    $scheme,
                    $code,
                    'VNAP25-DISTRICT0',
                    'VN-01',
                    3,
                    'Phường Test',
                    'Test Ward'
                ),
                default => null
            }
        );
        $candidateFinder = $this->createMock(MappingCandidateFinder::class);
        $candidateFinder->method('find')->willReturnCallback(
            fn (string $scheme, string $code, string $target): array => (
                $scheme === VnSchemes::VN_ADMIN_2025 && $code === self::WARD_2025 && $target === VnSchemes::VN_ADMIN_PRE_2025
            )
                ? [['code' => self::WARD_PRE_2025, 'relation_type' => 'RENAMED_TO', 'direction' => 'incoming']]
                : []
        );
        $handoffService = new CarrierAddressHandoffService(
            $this->createMock(DestinationContextBuilderInterface::class),
            new ShippingAddressResolutionManager(new VnAdminAddressResolver($unitProvider, $candidateFinder)),
            $this->createMock(\Secomm\VietNamAddress\Api\VnPrimaryCandidateSelectorInterface::class)
        );

        // --- REAL shared chain: eligibility → mode → origin → policy → contributor ---
        $evaluator = new CarrierEligibilityEvaluator(new CanonicalZoneMatcher(), $this->zones);
        $originProvider = $this->createMock(\Secomm\ShippingCore\Api\OriginProviderInterface::class);
        $originProvider->method('resolve')->willReturn(
            new \Secomm\ShippingCore\Model\Origin(null, 'VN', null, null, null, null, null, null, null, null)
        );
        $executionService = new CarrierRateExecutionService(
            $evaluator,
            $originProvider,
            $handoffService,
            new SafeDegradationEligibilityPolicy()
        );

        // --- realtime contributor: real factory over the counted calculator seam ---
        // FRESH doubles per build — PHPUnit stacks method() stubs on a shared mock, which
        // would double-fire the counting callbacks across rebuilds.
        $this->rateCalculator = $this->createMock(GhnRateCalculator::class);
        $this->requestMapper = $this->createMock(GhnRateRequestMapper::class);
        $this->outcomeCollector = $this->createMock(CarrierRateOutcomeCollectorInterface::class);
        $this->outcomeCollector->method('record')->willReturnCallback(
            function (string $carrier, string $method, CarrierRateOutcomeInterface $outcome): void {
                $this->recordedOutcomes[] = $outcome;
            }
        );
        // TASK-SEC-D-transport: decision records also surface their outcome for the same
        // reason-level assertions (transport eligibility is captured alongside).
        $this->outcomeCollector->method('recordDecision')->willReturnCallback(
            function (string $carrier, string $method, CarrierRateOutcomeInterface $outcome): void {
                $this->recordedOutcomes[] = $outcome;
            }
        );
        $this->rateCalculator->method('quoteWithHandoff')->willReturnCallback(
            function (GhnRateQuery $query, CarrierAddressHandoffInterface $handoff) {
                $this->apiCalls++;

                return CarrierRateOutcome::success(new CarrierRate(25000.0, 'VND'));
            }
        );
        $this->requestMapper->method('map')->willReturn(
            new GhnRateQuery('VN', 51, null, 'Phường Test', new QuoteParcelEstimate([
                new EstimatedPackage(10, 'UNIT-SKU', 1500.0, 'quote_item_weight'),
            ]), null)
        );
        $contributorFactory = new RealtimeRateContributorFactory(
            $this->rateCalculator,
            $this->requestMapper,
            new GhnLogger(new NullLogger())
        );

        // --- Magento carrier surface ---
        $this->scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => str_ends_with($path, '/active')
        );
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path) {
                $field = (string) str_replace('carriers/secomm_ghn/', '', $path);

                return ['title' => 'GHN', 'name' => 'GHN Delivery', 'showmethod' => 0][$field] ?? null;
            }
        );
        $priceCurrency = $this->createMock(\Magento\Framework\Pricing\PriceCurrencyInterface::class);
        $priceCurrency->method('round')->willReturnArgument(0);
        $rateMethodFactory = $this->createMock(MethodFactory::class);
        $rateMethodFactory->method('create')->willReturnCallback(
            fn (): Method => new Method($priceCurrency)
        );
        $rateResultFactory = $this->createMock(ResultFactory::class);
        $rateResult = $this->createMock(Result::class);
        $rateResultFactory->method('create')->willReturn($rateResult);

        $capability = new GhnAddressCapability($ghnConfig, new GhnLogger(new NullLogger()));

        return new Ghn(
            $this->scopeConfig,
            $this->createMock(\Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(\Magento\Framework\Xml\Security::class),
            $this->createMock(\Magento\Shipping\Model\Simplexml\ElementFactory::class),
            $rateResultFactory,
            $rateMethodFactory,
            $this->createMock(\Magento\Shipping\Model\Tracking\ResultFactory::class),
            $this->createMock(\Magento\Shipping\Model\Tracking\Result\ErrorFactory::class),
            $this->createMock(\Magento\Shipping\Model\Tracking\Result\StatusFactory::class),
            $this->createMock(\Magento\Directory\Model\RegionFactory::class),
            $this->createMock(\Magento\Directory\Model\CountryFactory::class),
            $this->createMock(\Magento\Directory\Model\CurrencyFactory::class),
            $this->createMock(\Magento\Directory\Helper\Data::class),
            $this->createMock(\Magento\CatalogInventory\Api\StockRegistryInterface::class),
            new GhnLogger(new NullLogger()),
            $this->outcomeCollector,
            $this->createMock(\Secomm\Ghn\Model\Tracking\GhnTrackingResultBuilder::class),
            $this->createMock(GhnRateAdjuster::class),
            $ghnConfig,
            $executionService,
            $runtimeContextBuilder,
            $operationalAddressResolver,
            new ShippingContextFactory(),
            $contributorFactory,
            $capability
        );
    }

    private function vnRequest(): RateRequest
    {
        $baseCurrency = $this->createMock(\Magento\Directory\Model\Currency::class);
        $baseCurrency->method('getCurrencyCode')->willReturn('VND');

        return (new RateRequest())
            ->setDestCountryId('VN')
            ->setDestRegionId(51)
            ->setDestCity('Phường Test')
            ->setPackageWeight(1.5)
            ->setBaseCurrency($baseCurrency);
    }
}
