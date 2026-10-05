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
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Address\Mapping\GhnMappingResolver;
use Secomm\Ghn\Model\Capability\GhnAddressCapability;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Rate\EstimatedPackage;
use Secomm\Ghn\Model\Rate\GhnRateCalculator;
use Secomm\Ghn\Model\Rate\GhnRateQuery;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimate;
use Secomm\Ghn\Model\Config;
use Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Fallback\SafeDegradationEligibilityPolicy;

/**
 * TASK-FXFMJ0 (DEC-TASKFXFMJ0-001) flow verification, REVISED by TASK-WNQCRW
 * (DEC-TASKWNQCRW-001, 2026-10-01):
 *
 * - the per-PACKAGE weight display gate (default 50000g, merchant-tunable) hides GHN before
 *   any fee call for a unit over the limit — UNAVAILABLE, never fallback-eligible (§9);
 * - an AGGREGATE of valid units still REACHES calculate_fee exactly once (70kg etc. —
 *   aggregates are never capped);
 * - fee SUCCESS is a realtime rate — weight alone never creates fallback eligibility;
 * - provider timeout on a heavy cart stays TECHNICAL_FAILURE (existing fallback policy may
 *   apply — unchanged by weight).
 *
 * Tests that quote >50kg SINGLE units construct RAISED-limit estimates — that keeps proving
 * the provider-side fact (the fee API has no 50kg bound; sandbox 2026-09-30) under the new
 * default gate. The default-gate behaviour itself is pinned in GhnRateCalculatorTest.
 *
 * Chain under test: QuoteParcelEstimate → real GhnRateCalculator → CarrierRateOutcome →
 * real SafeDegradationEligibilityPolicy.
 */
class HeavyWeightRateFlowVerificationTest extends TestCase
{
    private GhnApiClientInterface&MockObject $apiClient;

    private CarrierAddressHandoffServiceInterface&MockObject $handoffService;

    private GhnRateCalculator $calculator;

    private SafeDegradationEligibilityPolicy $policy;

    protected function setUp(): void
    {
        $config = $this->createMock(Config::class);
        $contextBuilder = $this->createMock(RuntimeAddressContextBuilderInterface::class);
        $contextBuilder->method('build')->willReturn(
            $this->createMock(\Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface::class)
        );
        $resolved = $this->createMock(\Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface::class);
        $resolved->method('isResolved')->willReturn(true);
        $resolved->method('getUnitCode')->willReturn('VNAP25-6A84B015D0');
        $this->handoffService = $this->createMock(CarrierAddressHandoffServiceInterface::class);
        $this->handoffService->method('handoffContextForOperation')->willReturn(
            new \Secomm\ShippingCore\Model\Address\CarrierAddressHandoff(
                applicable: true,
                resolvedAddress: $resolved,
                textualFallbackEligible: false,
                failureReason: null,
                candidateCodes: []
            )
        );
        $this->apiClient = $this->createMock(GhnApiClientInterface::class);

        $mappingResolver = $this->createMock(GhnMappingResolver::class);
        $mappingResolver->method('resolve')->willReturn(
            new \Secomm\Ghn\Model\Address\Mapping\GhnLocation(
                'VN_ADMIN_PRE_2025',
                'VNAP25-6A84B015D0',
                '235',
                '1846',
                '291124',
                'Nghệ An',
                'Xã Nhân Thành'
            )
        );

        $this->calculator = new GhnRateCalculator(
            $config,
            $contextBuilder,
            $this->handoffService,
            new GhnAddressCapability(),
            $mappingResolver,
            $this->apiClient,
            new GhnLogger($this->createMock(\Psr\Log\LoggerInterface::class))
        );

        // The REAL shared policy with the DEFAULT approved map (production wiring).
        $this->policy = new SafeDegradationEligibilityPolicy();
    }

    public function testSingleUnitSixtyKgQuotesWhenTheMerchantRaisesTheDisplayCap(): void
    {
        // Raised-limit estimate keeps proving the provider-side fact (no fee-API weight
        // bound — sandbox 2026-09-30): 60kg reaches calculate_fee exactly once, realtime.
        $captured = null;
        $this->apiClient->expects($this->once())->method('post')->willReturnCallback(
            function (string $operation, string $path, array $payload) use (&$captured) {
                $captured = $payload;

                return ['total' => 660000];
            }
        );

        $estimate = new QuoteParcelEstimate(
            [new EstimatedPackage(10, 'UNIT-SKU', 60000.0, 'quote_item_weight')],
            200,
            200,
            200,
            100000
        );
        $outcome = $this->calculator->calculate($this->queryFromEstimate($estimate));

        self::assertTrue($outcome->isSuccessful(), '60kg single unit must be quoted when the cap is raised (sandbox 2026-09-30)');
        self::assertSame(5, $captured['service_type_id']);
        self::assertSame(60000, $captured['weight']);
    }

    public function testOverLimitUnitOutcomeIsNeverFallbackEligible(): void
    {
        // TASK-WNQCRW §9: a per-unit weight violation is a hard carrier incompatibility —
        // UNAVAILABLE with GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED and NO fallback eligibility
        // (checked against the REAL production policy with the default approved map).
        $this->apiClient->expects($this->never())->method('post');

        $outcome = $this->calculator->calculate($this->query(50001.0));

        self::assertFalse($outcome->isSuccessful());
        self::assertSame(
            CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
            $outcome->getStatus()
        );
        self::assertFalse(
            $this->policy->isFallbackEligible(
                CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                $outcome->getFailureReason()
            ),
            'a per-unit weight violation must never unlock fallback'
        );
    }

    public function testAggregateSeventyKgReachesTheFeeApiExactlyOnceAndIsRealtime(): void
    {
        $captured = null;
        $this->apiClient->expects($this->once())->method('post')->willReturnCallback(
            function (string $operation, string $path, array $payload) use (&$captured) {
                $captured = $payload;

                return ['total' => 660000];
            }
        );

        $outcome = $this->calculator->calculate($this->queryFromEstimate(new QuoteParcelEstimate([
            new EstimatedPackage(11, 'SKU-35KG', 35000.0, 'quote_item_weight'),
            new EstimatedPackage(12, 'SKU-35KG', 35000.0, 'quote_item_weight'),
        ])));

        self::assertTrue($outcome->isSuccessful(), '2×35kg aggregate must be quoted (sandbox 2026-09-30)');
        self::assertSame(5, $captured['service_type_id']);
        self::assertSame(70000, $captured['weight']);
        self::assertCount(2, $captured['items'], 'per-unit rows preserved');
    }

    public function testHeavyCartTimeoutStaysTechnicalAndKeepsTheFallbackPathOpen(): void
    {
        // §15 — the mere weight value must not change failure semantics: a provider timeout on
        // a 70kg cart is TECHNICAL_FAILURE, which the existing policy may fallback on.
        $this->apiClient->expects($this->once())->method('post')->willThrowException(
            new ProviderTimeoutException(new Phrase('GHN timeout'))
        );

        $outcome = $this->calculator->calculate($this->queryFromEstimate(new QuoteParcelEstimate([
            new EstimatedPackage(11, 'SKU-35KG', 35000.0, 'quote_item_weight'),
            new EstimatedPackage(12, 'SKU-35KG', 35000.0, 'quote_item_weight'),
        ])));

        self::assertSame(CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
        self::assertTrue(
            $this->policy->isFallbackEligible(
                CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE,
                $outcome->getFailureReason()
            ),
            'technical failure keeps the existing fallback path — independent of weight'
        );
    }

    private function query(float $weightGrams): GhnRateQuery
    {
        // 1-arg ctor → the default limits govern (200cm dims / 50000g weight — TASK-WNQCRW).
        return $this->queryFromEstimate(new QuoteParcelEstimate([
            new EstimatedPackage(10, 'UNIT-SKU', $weightGrams, 'quote_item_weight'),
        ]));
    }

    private function queryFromEstimate(QuoteParcelEstimate $estimate): GhnRateQuery
    {
        return new GhnRateQuery('VN', 12, null, 'Phường Bến Nghé', $estimate, null);
    }
}
