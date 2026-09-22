<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Rate;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderInvalidRequestException;
use Secomm\Ghn\Api\Exception\ProviderRateUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderRemoteException;
use Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Exception\GhnMappingNotFoundException;
use Secomm\Ghn\Model\Exception\GhnRateEstimationException;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Rate\GhnRateCalculator;
use Secomm\Ghn\Model\Rate\GhnRateRequestMapper;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimate;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimator;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\Ghn\Model\Rate\RealtimeRateContributor;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;

/**
 * TASK-WAWNDS freeze (v10 §35) — the realtime contributor boundary: GHN receives ONLY the
 * final carrier-facing handoff, closes over its own RateRequest, maps the estimate, and
 * returns the shared outcome domain. Stage-1 canonical resolution NEVER happens here
 * (handoff-service never called); mapping/API failures pass through verbatim; estimation
 * limitations surface as structured GHN reasons for the shared fallback policy to judge.
 */
class RealtimeRateContributorTest extends TestCase
{
    private GhnRateCalculator&MockObject $rateCalculator;

    private GhnRateRequestMapper&MockObject $requestMapper;

    private RateRequest&MockObject $request;

    private RealtimeRateContributor $contributor;

    protected function setUp(): void
    {
        $this->rateCalculator = $this->createMock(GhnRateCalculator::class);
        $this->requestMapper = $this->createMock(GhnRateRequestMapper::class);
        $this->request = $this->createMock(RateRequest::class);
        $this->contributor = new RealtimeRateContributor(
            $this->rateCalculator,
            $this->requestMapper,
            new GhnLogger($this->createMock(\Psr\Log\LoggerInterface::class)),
            $this->request
        );
    }

    public function testWrongCarrierCodeIsAWiringMistake(): void
    {
        $this->expectException(\LogicException::class);

        $this->contributor->contribute('secomm_ghtk', $this->handoff());
    }

    public function testMissingClosedRequestIsAWiringMistake(): void
    {
        $contributor = new RealtimeRateContributor(
            $this->rateCalculator,
            $this->requestMapper,
            new GhnLogger($this->createMock(\Psr\Log\LoggerInterface::class))
        );

        $this->expectException(\LogicException::class);
        $contributor->contribute('secomm_ghn', $this->handoff());
    }

    public function testEstimationLimitationSurfacesStructuredReason(): void
    {
        $this->requestMapper->method('map')->willThrowException(new GhnRateEstimationException(
            QuoteParcelEstimator::REASON_ESTIMATION_UNAVAILABLE,
            new Phrase('no usable weight')
        ));

        $outcome = $this->contributor->contribute('secomm_ghn', $this->handoff());

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame(QuoteParcelEstimator::REASON_ESTIMATION_UNAVAILABLE, $outcome->getFailureReason());
    }

    public function testUnresolvedHandoffIsDefensivelyUnavailableWithoutCalculator(): void
    {
        // Normal runtime never delivers an unresolved handoff here (the execution service
        // gates it) — the branch is misuse protection only.
        $handoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $handoff->method('isApplicable')->willReturn(true);
        $handoff->method('getResolvedAddress')->willReturn(null);
        $handoff->method('getFailureReason')->willReturn('CANONICAL_UNRESOLVED');
        $this->requestMapper->method('map')->willReturn($this->query());
        $this->rateCalculator->expects($this->never())->method('quoteWithHandoff');

        $outcome = $this->contributor->contribute('secomm_ghn', $handoff);

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame('CANONICAL_UNRESOLVED', $outcome->getFailureReason());
    }

    public function testLocalizedConfigExceptionFromMapperFailsClosed(): void
    {
        // Weight-unit failure surfaces from the MAPPER path (StoreWeightConverter) — the
        // contributor translates it to merchant-side INVALID_CONFIGURATION (fail closed),
        // mirroring the carrier boundary. It must never leak across the seam.
        $this->requestMapper->method('map')->willThrowException(
            new LocalizedException(new Phrase('weight unit'))
        );
        $this->rateCalculator->expects($this->never())->method('quoteWithHandoff');

        $outcome = $this->contributor->contribute('secomm_ghn', $this->handoff());

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame('INVALID_CONFIGURATION', $outcome->getFailureReason());
    }
    public function testResolvedHandoffDelegatesToTheStage2FeePath(): void
    {
        $query = $this->query();
        $this->requestMapper->expects($this->once())->method('map')->with($this->request)->willReturn($query);
        $handoff = $this->handoff();
        $expected = CarrierRateOutcome::success(new \Secomm\ShippingCore\Model\Rate\CarrierRate(605000.0, 'VND'));
        $this->rateCalculator->expects($this->once())->method('quoteWithHandoff')
            ->with($query, $handoff)->willReturn($expected);

        $outcome = $this->contributor->contribute('secomm_ghn', $handoff);

        $this->assertSame($expected, $outcome);
    }

    public function testProviderMappingMissingOutcomePassesThroughVerbatim(): void
    {
        // §18: mapping-missing stays UNAVAILABLE + PROVIDER_MAPPING_MISSING; the execution
        // service maps it to INTEGRATION_LIMITATION upstream — GHN never dispatches fallback.
        $query = $this->query();
        $this->requestMapper->method('map')->willReturn($query);
        $mappingOutcome = CarrierRateOutcome::unavailable('PROVIDER_MAPPING_MISSING');
        $this->rateCalculator->method('quoteWithHandoff')->willReturn($mappingOutcome);

        $outcome = $this->contributor->contribute('secomm_ghn', $this->handoff());

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame('PROVIDER_MAPPING_MISSING', $outcome->getFailureReason());
    }

    /**
     * TASK-WAWNDS correctness pass — the seam is outcome-based: every supported provider/
     * domain exception converts to a CarrierRateOutcome with EXACT parity to
     * GhnRateCalculator::calculate() (technical group → TECHNICAL_FAILURE+TECHNICAL_ERROR;
     * mapping-missing → UNAVAILABLE+PROVIDER_MAPPING_MISSING; business group →
     * UNAVAILABLE+SERVICE_UNAVAILABLE; LocalizedException config/unit → UNAVAILABLE+
     * INVALID_CONFIGURATION). No supported exception may cross the seam.
     *
     * @dataProvider providerExceptionProvider
     */
    public function testProviderDomainExceptionsTranslateToOutcomes(
        \Throwable $exception,
        string $expectedStatus,
        string $expectedReason
    ): void {
        $query = $this->query();
        $this->requestMapper->method('map')->willReturn($query);
        $this->rateCalculator->method('quoteWithHandoff')->willThrowException($exception);

        $outcome = $this->contributor->contribute('secomm_ghn', $this->handoff());

        $this->assertSame($expectedStatus, $outcome->getStatus());
        $this->assertSame($expectedReason, $outcome->getFailureReason());
    }

    public static function providerExceptionProvider(): array
    {
        $p = static fn (string $class): \Throwable => new $class(new Phrase('provider said no'));

        return [
            'timeout → TECHNICAL' => [
                $p(ProviderTimeoutException::class),
                CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE,
                ShippingFailureReason::TECHNICAL_ERROR,
            ],
            'remote/5xx → TECHNICAL' => [
                $p(ProviderRemoteException::class),
                CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE,
                ShippingFailureReason::TECHNICAL_ERROR,
            ],
            'service-unavailable(429/5xx) → TECHNICAL' => [
                $p(ProviderServiceUnavailableException::class),
                CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE,
                ShippingFailureReason::TECHNICAL_ERROR,
            ],
            'mapping missing → UNAVAILABLE+PROVIDER_MAPPING_MISSING' => [
                new GhnMappingNotFoundException(new Phrase('no approved mapping')),
                CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                'PROVIDER_MAPPING_MISSING',
            ],
            'business rate rejection → UNAVAILABLE+SERVICE_UNAVAILABLE' => [
                $p(ProviderRateUnavailableException::class),
                CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                ShippingFailureReason::SERVICE_UNAVAILABLE,
            ],
            'auth rejection → UNAVAILABLE+SERVICE_UNAVAILABLE' => [
                $p(ProviderAuthenticationException::class),
                CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                ShippingFailureReason::SERVICE_UNAVAILABLE,
            ],
        ];
    }

    public function testProgrammingDefectsStillPropagate(): void
    {
        // No Throwable catch: a bug (TypeError, …) must stay observable — never converted
        // into a provider outage outcome.
        $query = $this->query();
        $this->requestMapper->method('map')->willReturn($query);
        $this->rateCalculator->method('quoteWithHandoff')->willThrowException(
            new \TypeError('processor bug')
        );

        $this->expectException(\TypeError::class);
        $this->contributor->contribute('secomm_ghn', $this->handoff());
    }

    // ---------- helpers ----------

    private function query(): \Secomm\Ghn\Model\Rate\GhnRateQuery
    {
        $packages = [new \Secomm\Ghn\Model\Rate\EstimatedPackage(10, 'UNIT-SKU', 1500.0, 'quote_item_weight')];

        return new \Secomm\Ghn\Model\Rate\GhnRateQuery(
            'VN',
            12,
            null,
            'Phường Bến Nghé',
            new QuoteParcelEstimate($packages),
            null
        );
    }

    private function handoff(): CarrierAddressHandoffInterface&MockObject
    {
        $handoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $handoff->method('isApplicable')->willReturn(true);
        $resolved = $this->createMock(\Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface::class);
        $resolved->method('isResolved')->willReturn(true);
        $resolved->method('getUnitCode')->willReturn('VNAP25-SELECTED');
        $handoff->method('getResolvedAddress')->willReturn($resolved);

        return $handoff;
    }
}
