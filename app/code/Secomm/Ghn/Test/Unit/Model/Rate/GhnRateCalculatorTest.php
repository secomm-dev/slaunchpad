<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Rate;

use Magento\Framework\Phrase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderInvalidRequestException;
use Secomm\Ghn\Api\Exception\ProviderRateUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderRemoteException;
use Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Address\Mapping\GhnLocation;
use Secomm\Ghn\Model\Address\Mapping\GhnMappingResolver;
use Secomm\Ghn\Model\Capability\GhnAddressCapability;
use Secomm\Ghn\Model\Exception\GhnMappingNotFoundException;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Rate\EstimatedPackage;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimate;
use Secomm\Ghn\Model\Rate\GhnRateCalculator;
use Secomm\Ghn\Model\Rate\GhnRateQuery;
use Secomm\Ghn\Model\Config;
use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface;
use Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Address\CarrierAddressHandoff;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-FMBBSD — RATE contract slice: outcome classification (business vs technical), the
 * Stage-1/Stage-2 boundary (PROVIDER_MAPPING_MISSING ≠ CANONICAL_UNRESOLVED), and the current
 * official Calculate Fee payload contract (service_type_id by weight, cod_value, optional dims).
 */
class GhnRateCalculatorTest extends TestCase
{
    private Config&MockObject $config;

    private RuntimeAddressContextBuilderInterface&MockObject $contextBuilder;

    private CarrierAddressHandoffServiceInterface&MockObject $handoffService;

    private GhnMappingResolver&MockObject $mappingResolver;

    private GhnApiClientInterface&MockObject $apiClient;

    private GhnRateCalculator $calculator;

    private const FEE_PATH = 'v2/shipping-order/fee';

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->contextBuilder = $this->createMock(RuntimeAddressContextBuilderInterface::class);
        $this->handoffService = $this->createMock(CarrierAddressHandoffServiceInterface::class);
        $this->mappingResolver = $this->createMock(GhnMappingResolver::class);
        $this->apiClient = $this->createMock(GhnApiClientInterface::class);
        $this->contextBuilder->method('build')->willReturn(
            $this->createMock(\Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface::class)
        );

        $this->calculator = new GhnRateCalculator(
            $this->config,
            $this->contextBuilder,
            $this->handoffService,
            new GhnAddressCapability(),
            $this->mappingResolver,
            $this->apiClient,
            new GhnLogger($this->createMock(\Psr\Log\LoggerInterface::class))
        );
    }

    // ---------- guards ----------

    public function testZeroWeightIsUnavailableWithoutAnyApiCall(): void
    {
        $outcome = $this->calculator->calculate($this->query(0.0));

        $this->assertSame(CarrierRateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::SERVICE_UNAVAILABLE, $outcome->getFailureReason());
    }

    public function testNonVietnamDestinationIsUnavailableWithHandoffReason(): void
    {
        $this->givenHandoff($this->handoff(applicable: false, failureReason: ShippingFailureReason::UNSUPPORTED_DESTINATION));

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertSame(CarrierRateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::UNSUPPORTED_DESTINATION, $outcome->getFailureReason());
    }

    public function testCanonicalUnresolvedIsUnavailable(): void
    {
        // Unresolved canonical state reaches the carrier as a non-applicable handoff carrying
        // the Stage-1 failure reason (the handoff VO forbids applicable+reason combinations).
        $this->givenHandoff($this->handoff(applicable: false, failureReason: ShippingFailureReason::CANONICAL_UNRESOLVED));

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertSame(CarrierRateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::CANONICAL_UNRESOLVED, $outcome->getFailureReason());
    }

    /**
     * §28 boundary: a resolvable canonical unit without an APPROVED GHN mapping is a STAGE-2
     * failure — PROVIDER_MAPPING_MISSING, never CANONICAL_UNRESOLVED.
     */
    public function testProviderMappingMissingIsUnavailableNotCanonicalUnresolved(): void
    {
        $this->givenHandoff($this->handoff(applicable: true, failureReason: null, unitCode: 'VNAP25-XYZ'));
        $this->mappingResolver->method('resolve')->willThrowException(
            new GhnMappingNotFoundException(new Phrase('no approved mapping'))
        );

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertSame(CarrierRateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::PROVIDER_MAPPING_MISSING, $outcome->getFailureReason());
        $this->assertNull($outcome->getRate());
    }

    // ---------- payload contract ----------

    public function testLightParcelQuotesServiceType2WithoutOptionalFields(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->expects($this->once())->method('post')->with(
            'calculate_fee',
            self::FEE_PATH,
            [
                'service_type_id' => 2,
                'weight' => 1500,
                'to_district_id' => 1846,
                'to_ward_code' => '291124',
            ]
        )->willReturn(['total' => 25000, 'service_fee' => 22000]);

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertSame(CarrierRateOutcome::STATUS_SUCCESS, $outcome->getStatus());
        $this->assertSame(25000.0, $outcome->getRate()->getAmount());
        $this->assertSame('VND', $outcome->getRate()->getCurrency());
    }

    public function testHeavyQuoteRatesType5WithPerUnitItems(): void
    {
        // TASK-WAWNDS: heavy quotes rate REALLY — service_type_id=5 with the REQUIRED per-unit
        // items[] payload (sandbox: root-weight-only type-5 is provider-rejected).
        $this->givenResolvedLegacyUnit();
        $this->config->method('getOriginDistrictId')->willReturn(0);
        $captured = null;
        $this->apiClient->expects($this->once())->method('post')->willReturnCallback(
            function (string $op, string $path, array $payload) use (&$captured) {
                $captured = $payload;

                return ['total' => 605000];
            }
        );

        $outcome = $this->calculator->calculate($this->query(20000.0));

        $this->assertTrue($outcome->isSuccessful());
        $this->assertNotNull($captured);
        $this->assertSame(5, $captured['service_type_id']);
        $this->assertSame(20000, $captured['weight']);
        $this->assertSame([['name' => 'UNIT-SKU', 'quantity' => 1, 'weight' => 20000]], $captured['items']);
    }

    public function testMultiParcelQuoteExpandsPerUnitRowsAndAggregatesRootWeight(): void
    {
        // 2 estimated packages (30kg × 1 + 18kg × 1) → type 5, root weight = aggregate,
        // items[] = ONE ROW PER UNIT — quantity aggregation is NOT fee-equivalent (sandbox C2≠C).
        $this->givenResolvedLegacyUnit();
        $this->config->method('getOriginDistrictId')->willReturn(0);
        $captured = null;
        $this->apiClient->expects($this->once())->method('post')->willReturnCallback(
            function (string $op, string $path, array $payload) use (&$captured) {
                $captured = $payload;

                return ['total' => 605000];
            }
        );

        $packages = [
            new EstimatedPackage(11, 'SKU-30KG', 30000.0, 'quote_item_weight'),
            new EstimatedPackage(12, 'SKU-18KG', 18000.0, 'quote_item_weight'),
        ];
        $outcome = $this->calculator->calculate($this->queryFromEstimate(new QuoteParcelEstimate($packages)));

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(5, $captured['service_type_id']);
        $this->assertSame(48000, $captured['weight'], 'aggregate root weight — >50kg aggregate is NOT rejected');
        $this->assertCount(2, $captured['items']);
        $this->assertSame('SKU-30KG', $captured['items'][0]['name']);
        $this->assertSame(18000, $captured['items'][1]['weight']);
    }

    public function testTrustedDimensionOverVerifiedLimitRejectsBeforeAnyProviderCall(): void
    {
        // SANDBOX-OBSERVED hard limit: staging create rejected length with "…vượt quá mức cho
        // phép: 150" (supersedes the docs' 200cm). The violation gates BEFORE handoff/fee.
        $this->handoffService->expects($this->never())->method('handoffContextForOperation');
        $this->apiClient->expects($this->never())->method('post');

        $packages = [new EstimatedPackage(10, 'LONG-SKU', 25000.0, 'quote_item_weight', lengthCm: 151)];
        $outcome = $this->calculator->calculate($this->queryFromEstimate(new QuoteParcelEstimate($packages)));

        $this->assertSame(CarrierRateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame('GHN_PACKAGE_LENGTH_LIMIT_EXCEEDED', $outcome->getFailureReason());
    }

    public function testTrustedDimensionsAtOrUnderLimitStillQuote(): void
    {
        // 150cm is the verified boundary — AT the limit is acceptable.
        $this->givenResolvedLegacyUnit();
        $this->config->method('getOriginDistrictId')->willReturn(0);
        $this->apiClient->method('post')->willReturn(['total' => 605000]);

        $packages = [new EstimatedPackage(10, 'LONG-SKU', 25000.0, 'quote_item_weight', lengthCm: 150, widthCm: 20, heightCm: 20)];
        $this->assertTrue($this->calculator->calculate($this->queryFromEstimate(new QuoteParcelEstimate($packages)))->isSuccessful());
    }

    public function testMissingDimensionsAreNotHardRejections(): void
    {
        // brief §13: missing/untrusted data is an estimation limitation, NEVER a carrier
        // rejection — weight-only packages must still rate (sandbox probe I3: type-5 items
        // without dimensions quote fine).
        $this->givenResolvedLegacyUnit();
        $this->config->method('getOriginDistrictId')->willReturn(0);
        $this->apiClient->method('post')->willReturn(['total' => 605000]);

        $this->assertTrue($this->calculator->calculate($this->query(20000.0))->isSuccessful());
    }

    public function testAggregateWeightAboveDocumentedRootCapIsNotRejectedByAggregate(): void
    {
        // 2×35kg = 70kg aggregate: NO aggregate cap exists (sandbox D = 200; docs 50k is a
        // CREATE-root number, never fee-enforced). Per-package weight enforcement stays
        // DEFERRED until a provider-verified rejection exists.
        $this->givenResolvedLegacyUnit();
        $this->config->method('getOriginDistrictId')->willReturn(0);
        $this->apiClient->method('post')->willReturn(['total' => 605000]);

        $packages = [
            new EstimatedPackage(11, 'SKU-35KG', 35000.0, 'quote_item_weight'),
            new EstimatedPackage(12, 'SKU-35KG', 35000.0, 'quote_item_weight'),
        ];
        $this->assertTrue($this->calculator->calculate($this->queryFromEstimate(new QuoteParcelEstimate($packages)))->isSuccessful());
    }
    public function testAddressResolutionPolicyPassesThroughToSharedHandoff(): void
    {
        // TASK-MD2BD3 (v10) — thin adapter read: GHN forwards the configured policy to the
        // shared handoff service verbatim; selection/ranking stays OUTSIDE GHN.
        $this->config->method('getAddressResolutionPolicy')->willReturn(AddressResolutionPolicy::PICK_PRIMARY);
        $this->config->method('getOriginDistrictId')->willReturn(0);
        $this->handoffService->expects($this->once())->method('handoffContextForOperation')->with(
            $this->isInstanceOf(ShippingAddressResolutionContextInterface::class),
            $this->isInstanceOf(GhnAddressCapability::class),
            ShippingAddressOperation::RATE,
            AddressResolutionPolicy::PICK_PRIMARY
        )->willReturn($this->handoff(applicable: true, failureReason: null, unitCode: 'VNAP25-6A84B015D0'));
        $this->mappingResolver->method('resolve')->willReturn(
            new GhnLocation('VN_ADMIN_PRE_2025', 'VNAP25-6A84B015D0', '235', '1846', '291124', 'Nghệ An', 'Xã Nhân Thành')
        );
        $this->apiClient->method('post')->willReturn(['total' => 605000]);

        $this->assertTrue($this->calculator->calculate($this->query(20000.0))->isSuccessful());
    }

    public function testCandidatesNeverLeakIntoProviderMappingInput(): void
    {
        // brief §7: GHN must never inspect candidate lists — the mapping resolver only ever
        // receives the SINGLE selected unit code from the handoff result.
        $this->givenHandoff($this->handoff(applicable: true, failureReason: null, unitCode: 'VNAP25-SELECTED'));
        $this->mappingResolver->expects($this->once())->method('resolve')->with(
            VnSchemes::VN_ADMIN_PRE_2025,
            'VNAP25-SELECTED'
        )->willReturn(new GhnLocation('VN_ADMIN_PRE_2025', 'VNAP25-SELECTED', '235', '1846', '291124', 'Nghệ An', 'Xã Nhân Thành'));
        $this->apiClient->method('post')->willReturn(['total' => 1000]);

        $this->assertTrue($this->calculator->calculate($this->query(1500.0))->isSuccessful());
    }
    public function testLightSingleParcelQuotesType2WithoutItems(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->config->method('getOriginDistrictId')->willReturn(0);
        $captured = null;
        $this->apiClient->expects($this->once())->method('post')->willReturnCallback(
            function (string $op, string $path, array $payload) use (&$captured) {
                $captured = $payload;

                return ['total' => 70400];
            }
        );

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(2, $captured['service_type_id']);
        $this->assertArrayNotHasKey('items', $captured, 'type-2 keeps the accepted weight-only payload');
        $this->assertArrayNotHasKey('length', $captured);
        $this->assertArrayNotHasKey('width', $captured);
        $this->assertArrayNotHasKey('height', $captured);
    }

    public function testParcelJustUnderHeavyThresholdStillQuotesType2(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->expects($this->once())->method('post')->with(
            'calculate_fee',
            self::FEE_PATH,
            $this->callback(fn (array $payload): bool => $payload['service_type_id'] === 2 && $payload['weight'] === 19999)
        )->willReturn(['total' => 25000]);

        $this->assertTrue($this->calculator->calculate($this->query(19999.0))->isSuccessful());
    }

    public function testHandoffRunsThroughThePerOperationRatePath(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->method('post')->willReturn(['total' => 25000]);

        // The context build is fed the RATE-pinned legacy adapter → the runtime builder sees
        // the RATE required scheme (VN_ADMIN_PRE_2025), not a carrier-wide constant.
        $this->contextBuilder->expects($this->once())->method('build')->with(
            'VN',
            12,
            null,
            'Phường Bến Nghé',
            $this->callback(fn ($capability): bool => $capability instanceof CarrierAddressCapabilityInterface
                && $capability->getRequiredScheme() === VnSchemes::VN_ADMIN_PRE_2025
                && $capability->supportsTextualFallback() === false)
        )->willReturn($this->createMock(ShippingAddressResolutionContextInterface::class));

        $this->handoffService->expects($this->once())->method('handoffContextForOperation')->with(
            $this->isInstanceOf(ShippingAddressResolutionContextInterface::class),
            $this->isInstanceOf(GhnAddressCapability::class),
            ShippingAddressOperation::RATE
        )->willReturn($this->handoff(applicable: true, failureReason: null, unitCode: 'VNAP25-6A84B015D0'));

        $this->assertTrue($this->calculator->calculate($this->query(1500.0))->isSuccessful());
    }

    public function testOriginDistrictSentAndDimensionsNeverSent(): void
    {
        // TASK-WAWNDS: Magento carries no dimension-unit contract, and the sandbox proves
        // root dims DISTORT the type-2 fee — dims are never sent at RATE; origin stays optional.
        $this->config->method('getOriginDistrictId')->willReturn(235);
        $this->givenResolvedLegacyUnit();
        $this->apiClient->expects($this->once())->method('post')->with(
            'calculate_fee',
            self::FEE_PATH,
            $this->callback(fn (array $payload): bool => ($payload['from_district_id'] ?? null) === 235
                && !isset($payload['length']) && !isset($payload['width']) && !isset($payload['height']))
        )->willReturn(['total' => 30000]);

        $outcome = $this->calculator->calculate($this->query(2500.0));

        $this->assertTrue($outcome->isSuccessful());
    }

    public function testCollectionAmountRendersAsCodValue(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->expects($this->once())->method('post')->with(
            'calculate_fee',
            self::FEE_PATH,
            $this->callback(fn (array $payload): bool => ($payload['cod_value'] ?? null) === 285000)
        )->willReturn(['total' => 27000]);

        $outcome = $this->calculator->calculate($this->query(1500.0, collectionAmount: 285000));

        $this->assertTrue($outcome->isSuccessful());
    }

    public function testZeroCollectionAmountOmitsCodValue(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->expects($this->once())->method('post')->with(
            'calculate_fee',
            self::FEE_PATH,
            $this->callback(fn (array $payload): bool => !array_key_exists('cod_value', $payload))
        )->willReturn(['total' => 25000]);

        $this->assertTrue($this->calculator->calculate($this->query(1500.0, collectionAmount: 0))->isSuccessful());
    }

    public function testMissingFeeTotalIsTechnicalFailureNeverZeroRate(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->method('post')->willReturn(['service_fee' => 22000]);

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertSame(CarrierRateOutcome::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
        $this->assertNull($outcome->getRate());
    }

    // ---------- provider error classification ----------

    public function testAuthenticationFailureIsUnavailableNeverTechnical(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->method('post')->willThrowException(
            new ProviderAuthenticationException(new Phrase('token not configured'))
        );

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertSame(CarrierRateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::SERVICE_UNAVAILABLE, $outcome->getFailureReason());
    }

    public function testRouteWithoutServiceIsBusinessUnavailable(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->method('post')->willThrowException(
            new ProviderRateUnavailableException(new Phrase('NO SERVICE'))
        );

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertSame(CarrierRateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::SERVICE_UNAVAILABLE, $outcome->getFailureReason());
    }

    public function testInvalidRequestIsBusinessUnavailable(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->method('post')->willThrowException(
            new ProviderInvalidRequestException(new Phrase('USER_ERR_COMMON'))
        );

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertSame(CarrierRateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
    }

    public function testHttp5xxIsTechnicalFailureEligibleForFallback(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->method('post')->willThrowException(
            new ProviderServiceUnavailableException(new Phrase('SERVER_ERROR_COMMON'))
        );

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertSame(CarrierRateOutcome::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::TECHNICAL_ERROR, $outcome->getFailureReason());
    }

    public function testTimeoutIsTechnicalFailure(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->method('post')->willThrowException(
            new ProviderTimeoutException(new Phrase('timed out'))
        );

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertSame(CarrierRateOutcome::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
    }

    public function testMalformedTransportIsTechnicalFailure(): void
    {
        $this->givenResolvedLegacyUnit();
        $this->apiClient->method('post')->willThrowException(
            new ProviderRemoteException(new Phrase('malformed JSON'))
        );

        $outcome = $this->calculator->calculate($this->query(1500.0));

        $this->assertSame(CarrierRateOutcome::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
    }

    // ---------- helpers ----------

    private function query(
        float $weightGrams,
        ?int $collectionAmount = null
    ): GhnRateQuery {
        $packages = $weightGrams > 0 ? [new EstimatedPackage(10, 'UNIT-SKU', $weightGrams, 'quote_item_weight')] : [];

        return $this->queryFromEstimate(new QuoteParcelEstimate($packages), $collectionAmount);
    }

    private function queryFromEstimate(
        QuoteParcelEstimate $estimate,
        ?int $collectionAmount = null
    ): GhnRateQuery {
        return new GhnRateQuery('VN', 12, null, 'Phường Bến Nghé', $estimate, $collectionAmount);
    }

    private function givenResolvedLegacyUnit(): void
    {
        $this->givenHandoff($this->handoff(applicable: true, failureReason: null, unitCode: 'VNAP25-6A84B015D0'));
        $this->mappingResolver->method('resolve')->willReturn(
            new GhnLocation('VN_ADMIN_PRE_2025', 'VNAP25-6A84B015D0', '235', '1846', '291124', 'Nghệ An', 'Xã Nhân Thành')
        );
    }

    private function givenHandoff(CarrierAddressHandoffInterface $handoff): void
    {
        $this->handoffService->method('handoffContextForOperation')->willReturn($handoff);
    }

    private function handoff(
        bool $applicable,
        ?string $failureReason,
        string $unitCode = ''
    ): CarrierAddressHandoff {
        $resolved = null;
        if ($applicable) {
            $resolved = $this->createMock(ResolvedShippingAddressInterface::class);
            $resolved->method('isResolved')->willReturn(true);
            $resolved->method('getUnitCode')->willReturn($unitCode);
        }

        return new CarrierAddressHandoff(
            applicable: $applicable,
            resolvedAddress: $resolved,
            textualFallbackEligible: false,
            failureReason: $failureReason
        );
    }
}
