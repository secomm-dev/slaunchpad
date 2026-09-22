<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001 §5) — safe-degradation policy matrix tests.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Fallback;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Fallback\SafeDegradationEligibilityPolicy;

class SafeDegradationEligibilityPolicyTest extends TestCase
{
    private SafeDegradationEligibilityPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new SafeDegradationEligibilityPolicy();
    }

    public function testTechnicalFailureIsEligible(): void
    {
        $this->assertTrue(
            $this->policy->isFallbackEligible(
                CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE,
                ShippingFailureReason::TECHNICAL_ERROR
            )
        );
    }

    public function testCanonicalAmbiguousIsEligible(): void
    {
        $this->assertTrue(
            $this->policy->isFallbackEligible(
                CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                ShippingFailureReason::CANONICAL_AMBIGUOUS
            )
        );
    }

    /** v10 §35.5 — provider mapping missing = INTEGRATION_LIMITATION → eligible (status stays UNAVAILABLE). */
    public function testProviderMappingMissingIsEligible(): void
    {
        $this->assertTrue(
            $this->policy->isFallbackEligible(
                CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                ShippingFailureReason::PROVIDER_MAPPING_MISSING
            )
        );
    }

    /**
     * @dataProvider notEligibleProvider
     */
    public function testNotEligibleReasons(string $status, ?string $reason): void
    {
        $this->assertFalse($this->policy->isFallbackEligible($status, $reason));
    }

    public static function notEligibleProvider(): array
    {
        return [
            'UNMAPPED' => [CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, ShippingFailureReason::CANONICAL_UNMAPPED],
            'unsupported destination' => [CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, ShippingFailureReason::UNSUPPORTED_DESTINATION],
            'invalid configuration' => [CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, ShippingFailureReason::INVALID_CONFIGURATION],
            'service unavailable' => [CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, ShippingFailureReason::SERVICE_UNAVAILABLE],
            'generic unresolved' => [CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, ShippingFailureReason::CANONICAL_UNRESOLVED],
            'unavailable no reason' => [CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, null],
            'unknown status' => ['WEIRD', null],
            'carrier-owned diagnostic is NOT policy input' => [CarrierRateOutcomeInterface::STATUS_UNAVAILABLE, 'GHN_LOCATION_NOT_FOUND'],
        ];
    }

    public function testDiOverrideCanAddReasons(): void
    {
        $policy = new SafeDegradationEligibilityPolicy([ShippingFailureReason::INVALID_CONFIGURATION]);
        $this->assertTrue(
            $policy->isFallbackEligible(
                CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                ShippingFailureReason::INVALID_CONFIGURATION
            )
        );
        // default policy keeps auth/config OFF (configurable per §35.5)
        $this->assertFalse(
            $this->policy->isFallbackEligible(
                CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                ShippingFailureReason::INVALID_CONFIGURATION
            )
        );
    }

    public function testCarrierOwnedStringNeverUnlocks(): void
    {
        $this->assertFalse(
            $this->policy->isFallbackEligible(
                CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
                'AHAMOVE_OUTSIDE_COVERAGE'
            )
        );
    }
}
