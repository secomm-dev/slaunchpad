<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Rate;

use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Model\Address\Mapping\GhnLocation;
use Secomm\Ghn\Model\Address\Mapping\GhnMappingResolver;
use Secomm\Ghn\Model\Capability\GhnAddressCapability;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Rate\EstimatedPackage;
use Secomm\Ghn\Model\Rate\GhnRateCalculator;
use Secomm\Ghn\Model\Rate\GhnRateRequestMapper;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimate;
use Secomm\Ghn\Model\Rate\RealtimeRateContributor;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;

/**
 * TASK-SEC-D — direct API-client fixture: REAL GhnRateCalculator + REAL payload construction
 * + REAL contributor, with ONLY the external boundary mocked (GhnApiClientInterface).
 * Asserts the EXACT number of GHN API calls per scenario plus required payload fields —
 * contributor/calculator counts are never used as an API proxy.
 */
class CalculatorApiClientFixtureTest extends TestCase
{
    private GhnApiClientInterface&\PHPUnit\Framework\MockObject\MockObject $apiClient;

    private GhnMappingResolver&\PHPUnit\Framework\MockObject\MockObject $mappingResolver;

    /** @var array<int, array{operation: string, path: string, payload: array}> */
    private array $apiCalls = [];

    private bool $malformedResponse = false;

    protected function setUp(): void
    {
        $this->apiCalls = [];
        $this->apiClient = $this->createMock(GhnApiClientInterface::class);
        $this->apiClient->method('post')->willReturnCallback(
            function (string $operation, string $path, array $payload): array {
                $this->apiCalls[] = ['operation' => $operation, 'path' => $path, 'payload' => $payload];

                // Scenario switch (PHPUnit stacks stubs — a flag is the reliable switch):
                return $this->malformedResponse ? ['code' => 200, 'data' => []] : ['total' => 25000];
            }
        );
        $this->mappingResolver = $this->createMock(GhnMappingResolver::class);
        // GhnLocation is final — a real instance is the honest stand-in.
        $this->mappingResolver->method('resolve')->willReturn(
            new GhnLocation('VN_ADMIN_PRE_2025', 'VNAP25-DESTINAT1', 'VN-01', '1448', 'VNAP25-DESTINAT1', 'Hà Nội', 'Phường Test')
        );
    }

    private function contributor(?float $weightGrams = 500.0): RealtimeRateContributor
    {
        $config = $this->createMock(\Secomm\Ghn\Model\Config::class);
        $config->method('getAddressResolutionPolicy')->willReturn(AddressResolutionPolicy::FALLBACK);
        $capability = new GhnAddressCapability();

        $resolved = $this->createMock(ResolvedShippingAddressInterface::class);
        $resolved->method('isResolved')->willReturn(true);
        $resolved->method('getUnitCode')->willReturn('VNAP25-DESTINAT1');
        $handoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $handoff->method('isApplicable')->willReturn(true);
        $handoff->method('getResolvedAddress')->willReturn($resolved);

        $handoffService = $this->createMock(CarrierAddressHandoffServiceInterface::class);
        $handoffService->method('handoffContextForOperation')->willReturn($handoff);

        $packages = $weightGrams === null
            ? []
            : [new EstimatedPackage(1, 'SKU-1', $weightGrams, 'PRODUCT_UNIT_AS_PACKAGE')];
        $calculator = new GhnRateCalculator(
            $config,
            $this->createMock(\Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface::class),
            $handoffService,
            $capability,
            $this->mappingResolver,
            $this->apiClient,
            new GhnLogger(new \Psr\Log\NullLogger())
        );

        $mapper = $this->createMock(GhnRateRequestMapper::class);
        $mapper->method('map')->willReturnCallback(
            function () use ($packages): \Secomm\Ghn\Model\Rate\GhnRateQuery {
                return new \Secomm\Ghn\Model\Rate\GhnRateQuery(
                    'VN',
                    0,
                    null,
                    null,
                    new QuoteParcelEstimate($packages)
                );
            }
        );

        return new RealtimeRateContributor($calculator, $mapper, new GhnLogger(new \Psr\Log\NullLogger()), new RateRequest());
    }

    public function testSuccessCallsTheApiExactlyOnceWithRequiredPayloadFields(): void
    {
        $contributor = $this->contributor();
        $outcome = $contributor->contribute('secomm_ghn', $this->applicableHandoff());

        $this->assertSame(1, count($this->apiCalls), 'Exactly ONE GHN API call');
        $call = $this->apiCalls[0];
        $this->assertStringContainsString('fee', strtolower($call['path']), 'calculate-fee endpoint');
        $this->assertSame(2, $call['payload']['service_type_id'], 'Light parcel service type');
        $this->assertArrayHasKey('to_district_id', $call['payload']);
        $this->assertArrayHasKey('to_ward_code', $call['payload']);
        $this->assertSame('SUCCESS', $outcome->getStatus());
        $this->assertSame(25000.0, $outcome->getRate()?->getAmount());
    }

    public function testMappingMissingNeverCallsTheApi(): void
    {
        $this->mappingResolver->method('resolve')->willThrowException(
            new \Secomm\Ghn\Model\Exception\GhnMappingNotFoundException(new \Magento\Framework\Phrase('no mapping'))
        );

        $outcome = $this->contributor()->contribute('secomm_ghn', $this->applicableHandoff());

        $this->assertSame(0, count($this->apiCalls));
        $this->assertSame('UNAVAILABLE', $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::PROVIDER_MAPPING_MISSING, $outcome->getFailureReason());
    }

    public function testEmptyParcelIsBusinessRejectedBeforeTheApi(): void
    {
        $outcome = $this->contributor(weightGrams: null)->contribute('secomm_ghn', $this->applicableHandoff());

        $this->assertSame(0, count($this->apiCalls));
        $this->assertSame('UNAVAILABLE', $outcome->getStatus());
    }

    public function testTimeoutExceptionTranslatesToTechnicalFailure(): void
    {
        $this->apiClient->method('post')->willThrowException(
            new \Secomm\Ghn\Api\Exception\ProviderTimeoutException(new \Magento\Framework\Phrase('timeout'))
        );

        $outcome = $this->contributor()->contribute('secomm_ghn', $this->applicableHandoff());

        $this->assertSame(1, count($this->apiCalls));
        $this->assertSame('TECHNICAL_FAILURE', $outcome->getStatus(), 'Timeout stays fallback-eligible');
        $this->assertSame(ShippingFailureReason::TECHNICAL_ERROR, $outcome->getFailureReason());
    }

    public function testFiveXxTranslatesToTechnicalFailure(): void
    {
        $this->apiClient->method('post')->willThrowException(
            new \Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException(new \Magento\Framework\Phrase('503'))
        );

        $outcome = $this->contributor()->contribute('secomm_ghn', $this->applicableHandoff());

        $this->assertSame(1, count($this->apiCalls));
        $this->assertSame('TECHNICAL_FAILURE', $outcome->getStatus());
    }

    public function testMalformedResponseIsTechnicalNotAZeroRate(): void
    {
        $this->malformedResponse = true;

        $outcome = $this->contributor()->contribute('secomm_ghn', $this->applicableHandoff());

        $this->assertSame(1, count($this->apiCalls));
        $this->assertSame('TECHNICAL_FAILURE', $outcome->getStatus(), 'Never a zero/magic rate');
    }

    public function testAuthFailureIsBusinessRejectionNotTechnical(): void
    {
        $this->apiClient->method('post')->willThrowException(
            new \Secomm\Ghn\Api\Exception\ProviderAuthenticationException(new \Magento\Framework\Phrase('bad token'))
        );

        $outcome = $this->contributor()->contribute('secomm_ghn', $this->applicableHandoff());

        $this->assertSame(1, count($this->apiCalls));
        $this->assertSame('UNAVAILABLE', $outcome->getStatus(), 'Auth is fail-closed business/config');
        $this->assertSame(ShippingFailureReason::SERVICE_UNAVAILABLE, $outcome->getFailureReason());
    }

    public function testUnclassifiedThrowablePropagatesToTheCarrierCatchAll(): void
    {
        // A TypeError is a programming defect: the contributor must NOT swallow it into a
        // provider outcome — the carrier catch-all owns the fail-closed UNEXPECTED decision.
        $this->apiClient->method('post')->willReturnCallback(
            function (): never {
                throw new \TypeError('internal defect');
            }
        );

        $this->expectException(\TypeError::class);
        $this->contributor()->contribute('secomm_ghn', $this->applicableHandoff());
    }

    public function testPayloadCarriesNoSensitiveMaterial(): void
    {
        $contributor = $this->contributor();
        $contributor->contribute('secomm_ghn', $this->applicableHandoff());

        $payload = json_encode($this->apiCalls[0]['payload'] ?? []) ?: '';
        foreach (['token', 'password', 'secret', 'authorization'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, strtolower($payload));
        }
    }

    private function applicableHandoff(): CarrierAddressHandoffInterface
    {
        $handoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $handoff->method('isApplicable')->willReturn(true);
        $resolved = $this->createMock(ResolvedShippingAddressInterface::class);
        $resolved->method('isResolved')->willReturn(true);
        $resolved->method('getUnitCode')->willReturn('VNAP25-DESTINAT1');
        $handoff->method('getResolvedAddress')->willReturn($resolved);

        return $handoff;
    }
}
