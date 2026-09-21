<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001 §9) — per-method fallback evaluation matrix.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Model;

use Launchpad\MageplazaTableRate\Model\Exception\FallbackConfigurationException;
use Launchpad\MageplazaTableRate\Model\FallbackCoordinator;
use Launchpad\MageplazaTableRate\Model\FallbackRateProvider;
use Launchpad\MageplazaTableRate\Model\MemberRatePolicy;
use Launchpad\MageplazaTableRate\Model\MethodSettingsProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeCollectorInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Fallback\SafeDegradationEligibilityPolicy;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;

class FallbackCoordinatorTest extends TestCase
{
    private ?float $capturedPrice = null;

    private MemberRatePolicy $memberRatePolicy;

    private string $stubMode = RateSourceMode::CARRIER_WITH_FALLBACK;

    private string $stubAddressPolicy = AddressResolutionPolicy::FALLBACK;

    private MethodSettingsProvider $settingsProvider;

    private CarrierRateOutcomeCollectorInterface $collector;

    private FallbackRateProvider $provider;

    private FallbackCoordinator $coordinator;

    private Result $result;

    private function makeResult(array $initial = []): Result
    {
        $rates = $initial;
        $result = $this->createMock(Result::class);
        $result->method('getAllRates')->willReturnCallback(static function () use (&$rates) {
            return $rates;
        });
        $result->method('append')->willReturnCallback(static function ($rate) use (&$rates) {
            $rates[] = $rate;

            return null;
        });
        $result->method('reset')->willReturnCallback(static function () use (&$rates) {
            $rates = [];

            return null;
        });

        return $result;
    }

    protected function setUp(): void
    {
        $this->settingsProvider = $this->getMockBuilder(MethodSettingsProvider::class)
            ->disableOriginalConstructor()->getMock();
        $this->collector = $this->createMock(CarrierRateOutcomeCollectorInterface::class);
        $this->provider = $this->getMockBuilder(FallbackRateProvider::class)
            ->disableOriginalConstructor()->getMock();
        $rateMethod = $this->createMock(Method::class);
        $rateMethod->method('setPrice')->willReturnCallback(function ($price): void {
            $this->capturedPrice = (float) $price;
        });
        $methodFactory = $this->createMock(MethodFactory::class);
        $methodFactory->method('create')->willReturn($rateMethod);

        $this->memberRatePolicy = $this->getMockBuilder(MemberRatePolicy::class)
            ->disableOriginalConstructor()->getMock();
        // Callback-based stubs: per-test overrides just assign $this->stubMode/$this->stubPolicy
        // (a second ->method(...)->willReturn would STACK, not replace).
        $this->memberRatePolicy->method('rateSourceMode')->willReturnCallback(fn (): string => $this->stubMode);
        $this->memberRatePolicy->method('addressResolutionPolicy')->willReturnCallback(fn (): string => $this->stubAddressPolicy);

        $this->coordinator = new FallbackCoordinator(
            $this->settingsProvider,
            $this->collector,
            new SafeDegradationEligibilityPolicy(),
            $this->memberRatePolicy,
            $this->provider,
            $methodFactory,
            $this->createMock(ScopeConfigInterface::class),
            new NullLogger()
        );
        $this->result = $this->makeResult();
    }

    /**
     * @param array<int, array{carrier_code: string, method_code: string}> $members
     */
    private function givenFallbackMethod(array $members, int $methodId = 10): void
    {
        $this->settingsProvider->method('getFallbackMethodIds')->willReturn([$methodId => $methodId]);
        $this->settingsProvider->method('getEnabledMembersMap')->willReturn([$methodId => $members]);
    }

    private function givenOutcomes(array $carrierMethodStatus): void
    {
        $outcomes = [];
        foreach ($carrierMethodStatus as $carrier => $methodStatus) {
            foreach ($methodStatus as $method => $status) {
                $outcomes[$carrier][$method] = match ($status) {
                    'SUCCESS' => CarrierRateOutcome::success(new \Secomm\ShippingCore\Model\Rate\CarrierRate(30000.0)),
                    'TECHNICAL' => CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'),
                    'AMBIGUOUS' => CarrierRateOutcome::unavailable('CANONICAL_AMBIGUOUS'),
                    'UNMAPPED' => CarrierRateOutcome::unavailable('CANONICAL_UNMAPPED'),
                    'MAPPING_MISSING' => CarrierRateOutcome::unavailable('PROVIDER_MAPPING_MISSING'),
                    default => CarrierRateOutcome::unavailable('SERVICE_UNAVAILABLE'),
                };
            }
        }
        $this->collector->method('getOutcomes')->willReturn($outcomes);
    }

    private function expectProviderCalculates(int $methodId = 10, float $amount = 35000.0): void
    {
        $fallbackRate = new \Secomm\ShippingCore\Model\Fallback\FallbackRate($amount, 'Giao tiết kiệm');
        $this->provider->expects($this->once())
            ->method('calculate')
            ->with($methodId, $this->isInstanceOf(RateRequest::class))
            ->willReturn($fallbackRate);
    }

    public function testAnyMemberSuccessSuppressesFallback(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
            ['carrier_code' => 'ghtk', 'method_code' => 'ghtk_standard'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'TECHNICAL'], 'ghtk' => ['ghtk_standard' => 'SUCCESS']]);
        $this->provider->expects($this->never())->method('calculate');

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(0, $this->result->getAllRates());
    }

    public function testTechnicalFailureTriggersFallback(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
            ['carrier_code' => 'ghtk', 'method_code' => 'ghtk_standard'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'TECHNICAL'], 'ghtk' => ['ghtk_standard' => 'UNAVAILABLE']]);
        $this->expectProviderCalculates();

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(1, $this->result->getAllRates());
        $this->assertSame(35000.0, $this->capturedPrice);
    }

    public function testCanonicalAmbiguousTriggersFallback(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'AMBIGUOUS']]);
        $this->expectProviderCalculates();

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(1, $this->result->getAllRates());
    }

    public function testUnmappedDoesNotTriggerFallback(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'UNMAPPED']]);
        $this->provider->expects($this->never())->method('calculate');

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(0, $this->result->getAllRates());
    }

    /** v10 §35.5 — PROVIDER_MAPPING_MISSING = INTEGRATION_LIMITATION → eligible. */
    public function testProviderMappingMissingTriggersFallback(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'MAPPING_MISSING']]);
        $this->expectProviderCalculates();

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(1, $this->result->getAllRates());
    }

    /** v10 §35 — CARRIER_ONLY member failures NEVER open a fallback. */
    public function testCarrierOnlyMemberNeverEligible(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'TECHNICAL']]);
        $this->stubMode = RateSourceMode::CARRIER_ONLY;
        $this->provider->expects($this->never())->method('calculate');

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(0, $this->result->getAllRates());
    }

    /** v10 §35.6 — FALLBACK_ONLY: eligibility DIRECTLY, no synthetic TECHNICAL_FAILURE needed. */
    public function testFallbackOnlyMemberIsEligibleDirectly(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
        ]);
        // carrier short-circuits and reports its SKIPPED marker (UNAVAILABLE + carrier-owned reason)
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'UNAVAILABLE']]);
        $this->stubMode = RateSourceMode::FALLBACK_ONLY;
        $this->expectProviderCalculates();

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(1, $this->result->getAllRates());
    }

    /** v10 §35.4 — AMBIGUOUS + AddressResolutionPolicy::STRICT → no ambiguity-driven fallback. */
    public function testAmbiguousWithStrictPolicyIsNotEligible(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'AMBIGUOUS']]);
        $this->stubAddressPolicy = AddressResolutionPolicy::STRICT;
        $this->provider->expects($this->never())->method('calculate');

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(0, $this->result->getAllRates());
    }

    /** v10 §35.4 — AMBIGUOUS + FALLBACK policy → eligible (mode permits). */
    public function testAmbiguousWithFallbackPolicyIsEligible(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'AMBIGUOUS']]);
        $this->expectProviderCalculates();

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(1, $this->result->getAllRates());
    }

    public function testAllUnavailableDoesNotTriggerFallback(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
            ['carrier_code' => 'ghtk', 'method_code' => 'ghtk_standard'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'UNAVAILABLE'], 'ghtk' => ['ghtk_standard' => 'UNAVAILABLE']]);
        $this->provider->expects($this->never())->method('calculate');

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(0, $this->result->getAllRates());
    }

    public function testNonParticipatingMemberAloneDoesNotTriggerFallback(): void
    {
        // Member configured but Magento never called it (not installed/disabled) — no outcome.
        $this->givenFallbackMethod([
            ['carrier_code' => 'secmm_off', 'method_code' => 'gone_method'],
        ]);
        $this->givenOutcomes([]);
        $this->provider->expects($this->never())->method('calculate');

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(0, $this->result->getAllRates());
    }

    public function testStaleMemberIsIgnoredWhenAnotherParticipates(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'ghost', 'method_code' => 'ghost_method'],
            ['carrier_code' => 'ghtk', 'method_code' => 'ghtk_standard'],
        ]);
        // Only GHTK participated; ghost produced nothing — its absence is NOT a failure.
        $this->givenOutcomes(['ghtk' => ['ghtk_standard' => 'TECHNICAL']]);
        $this->expectProviderCalculates();

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(1, $this->result->getAllRates());
    }

    public function testNoMatchingTableRowAppendsNothing(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'TECHNICAL']]);
        $this->provider->method('calculate')->willReturn(null);

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(0, $this->result->getAllRates());
    }

    public function testMisconfiguredGroupFailsSoft(): void
    {
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'TECHNICAL']]);
        $this->provider->method('calculate')->willThrowException(
            new FallbackConfigurationException(new Phrase('Fallback method #10 does not exist'))
        );

        // Must not throw into checkout; just no fallback.
        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(0, $this->result->getAllRates());
    }

    public function testNativeMethodCopyIsNotDuplicatedByFallback(): void
    {
        // Method C (show=1 + use_as_fallback=1) priced natively by the Mageplaza carrier in
        // THIS result — the fallback copy must be skipped (same business option, same code).
        $this->givenFallbackMethod([
            ['carrier_code' => 'secomm_ghn', 'method_code' => 'secomm_ghn'],
        ]);
        $this->givenOutcomes(['secomm_ghn' => ['secomm_ghn' => 'TECHNICAL']]);
        $native = $this->getMockBuilder(Method::class)
            ->addMethods(['getCarrier', 'getMethod'])
            ->disableOriginalConstructor()
            ->getMock();
        $native->method('getCarrier')->willReturn('mptablerate');
        $native->method('getMethod')->willReturn('10');
        $result = $this->makeResult([$native]);

        $this->provider->expects($this->never())->method('calculate');
        $this->coordinator->appendFallbackRates(new RateRequest(), $result);

        // only the pre-existing native rate — nothing appended
        $this->assertCount(1, $result->getAllRates());
    }

    public function testNoOutcomesIsCheapNoOp(): void
    {
        $this->settingsProvider->expects($this->never())->method('getFallbackMethodIds');
        $this->collector->method('getOutcomes')->willReturn([]);

        $this->coordinator->appendFallbackRates(new RateRequest(), $this->result);
        $this->assertCount(0, $this->result->getAllRates());
    }
}
