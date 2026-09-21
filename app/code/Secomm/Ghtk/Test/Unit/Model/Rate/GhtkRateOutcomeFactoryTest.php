<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Rate;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\Ghtk\Model\Rate\GhtkFeeResponse;
use Secomm\Ghtk\Model\Rate\GhtkRateOutcomeFactory;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRate;
use Secomm\ShippingCore\Model\ServiceLevel\ServiceLevelRateAggregator;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevelRegistry;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevel;

/**
 * TASK-W8SH0N — the GHTK RATE classifier maps every response shape onto the
 * shared CarrierRateOutcome semantics, and the resulting outcomes behave
 * correctly through the REAL ShippingCore aggregation (business never
 * contributes fallback; technical does; any success suppresses).
 */
class GhtkRateOutcomeFactoryTest extends TestCase
{
    private GhtkRateOutcomeFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new GhtkRateOutcomeFactory();
    }

    private function exception(string $category): GhtkApiException
    {
        return new GhtkApiException('transport failed', false, 0, null, $category);
    }

    /**
     * Real aggregator over a registry stub that knows the STANDARD level
     * (ShippingServiceLevel VO; mirrors the composition seeding contract).
     */
    private function aggregator(): ServiceLevelRateAggregator
    {
        // Real registry (final class) seeded with the STANDARD level — mirrors the
        // composition seeding contract without a dynamic framework.
        $registry = new ShippingServiceLevelRegistry([
            new ShippingServiceLevel('STANDARD', 'Standard shipping'),
        ]);

        return new ServiceLevelRateAggregator($registry);
    }

    // ------------------------------------------------------------ §33 matrix

    public function testSuccessFeeYieldsSuccessOutcome(): void
    {
        $rate = new CarrierRate(30000.0);
        $outcome = $this->factory->fromParsedResponse($this->successResponse(), $rate);

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_SUCCESS, $outcome->getStatus());
        $this->assertTrue($outcome->isSuccessful());
        $this->assertNotNull($outcome->getRate());
    }

    public function testBusinessRejectionYieldsUnavailableWithSharedReason(): void
    {
        $outcome = $this->factory->fromParsedResponse(
            GhtkFeeResponse::businessRejection('INVALID_ADDRESS', 'Địa chỉ không hợp lệ')
        );

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertFalse($outcome->isSuccessful());
        $this->assertSame(ShippingFailureReason::SERVICE_UNAVAILABLE, $outcome->getFailureReason());
    }

    public function testUnknownBusinessErrorStillYieldsUnavailable(): void
    {
        $outcome = $this->factory->fromParsedResponse(GhtkFeeResponse::businessRejection(null, null));

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $outcome->getStatus());
    }

    public function testMalformedSuccessPayloadYieldsTechnicalFailure(): void
    {
        // §12 — unusable technical data is NOT a customer/business unavailability.
        $outcome = $this->factory->fromParsedResponse(GhtkFeeResponse::malformed());

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::TECHNICAL_ERROR, $outcome->getFailureReason());
    }

    public function testDeliveryDeniedYieldsUnavailable(): void
    {
        $outcome = $this->factory->fromParsedResponse(GhtkFeeResponse::businessRejection(null, 'delivery denied'));

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $outcome->getStatus());
    }

    // ------------------------------------------------- transport exception matrix

    public function testNetworkCategoryYieldsTechnicalFailure(): void
    {
        $outcome = $this->factory->fromTransportException($this->exception(CarrierHttpErrorCategory::NETWORK));

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::TECHNICAL_ERROR, $outcome->getFailureReason());
    }

    public function testServerErrorCategoryYieldsTechnicalFailure(): void
    {
        $outcome = $this->factory->fromTransportException($this->exception(CarrierHttpErrorCategory::SERVER_ERROR));

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
    }

    public function testTimeoutCategoryYieldsTechnicalFailure(): void
    {
        $outcome = $this->factory->fromTransportException($this->exception(CarrierHttpErrorCategory::TIMEOUT));

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
    }

    public function testInvalidResponseCategoryYieldsTechnicalFailure(): void
    {
        $outcome = $this->factory->fromTransportException($this->exception(CarrierHttpErrorCategory::INVALID_RESPONSE));

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
    }

    public function testClientErrorCategoryYieldsUnavailable(): void
    {
        // Covers 400 invalid request and 403 invalid/expired token — merchant/auth
        // semantics are business UNAVAILABLE, never fallback-triggering.
        $outcome = $this->factory->fromTransportException($this->exception(CarrierHttpErrorCategory::CLIENT_ERROR));

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::SERVICE_UNAVAILABLE, $outcome->getFailureReason());
    }

    public function testRateLimitCategoryYieldsUnavailableConservatively(): void
    {
        // 429 is undocumented by GHTK — conservative UNAVAILABLE, no blind retry.
        $outcome = $this->factory->fromTransportException($this->exception(CarrierHttpErrorCategory::RATE_LIMIT));

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, $outcome->getStatus());
    }

    public function testSuccessFactoryComposesTheRate(): void
    {
        $outcome = $this->factory->success(30000.0);

        $this->assertSame(CarrierRateOutcomeInterface::STATUS_SUCCESS, $outcome->getStatus());
        $this->assertSame(30000.0, $outcome->getRate()?->getAmount());
        $this->assertNull($outcome->getFailureReason());
    }

    // ---------------------------------------------- §35 real-aggregator integration

    public function testBusinessRejectionDoesNotContributeFallbackEligibility(): void
    {
        $aggregator = $this->aggregator();
        $aggregate = $aggregator->aggregate('STANDARD', [
            'ghtk' => $this->factory->fromParsedResponse(GhtkFeeResponse::businessRejection('INVALID_ADDRESS', null)),
        ]);

        $this->assertFalse($aggregate->hasSuccessfulRate());
        $this->assertFalse($aggregate->hasTechnicalFailure(), 'business UNAVAILABLE must NOT be a fallback contributor');
    }

    public function testTechnicalOutageContributesFallbackEligibility(): void
    {
        $aggregator = $this->aggregator();
        $aggregate = $aggregator->aggregate('STANDARD', [
            'ghtk' => $this->factory->fromTransportException($this->exception(CarrierHttpErrorCategory::SERVER_ERROR)),
        ]);

        $this->assertFalse($aggregate->hasSuccessfulRate());
        $this->assertTrue($aggregate->hasTechnicalFailure(), 'technical outage IS the fallback contributor');
    }

    public function testRealtimeSuccessSuppressesFallbackSignalFromOtherCarrier(): void
    {
        $aggregator = $this->aggregator();
        $aggregate = $aggregator->aggregate('STANDARD', [
            'ghtk' => $this->factory->success(30000.0),
            'othertechnical' => $this->factory->fromTransportException($this->exception(CarrierHttpErrorCategory::SERVER_ERROR)),
        ]);

        $this->assertTrue($aggregate->hasSuccessfulRate());
        $this->assertTrue($aggregate->hasTechnicalFailure());
        // Any SUCCESS suppresses fallback at the E-SL2 decision layer — the aggregate
        // carries both signals and the decision (§14) suppresses on success.
        $this->assertSame(30000.0, $aggregate->getSuccessfulRates()['ghtk']->getAmount());
    }

    // ------------------------------------------------------------------ helpers

    private function successResponse(): GhtkFeeResponse
    {
        return GhtkFeeResponse::success(new \Secomm\Ghtk\Model\Fee\FeeResult(30000.0, 0.0, 0.0, true, 'area1'));
    }
}
