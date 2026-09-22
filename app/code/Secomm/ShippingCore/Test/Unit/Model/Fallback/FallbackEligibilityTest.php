<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Fallback;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilitySource;
use Secomm\ShippingCore\Model\Fallback\FallbackEligibility;

/**
 * TASK-5JQYMP — explicit fallback eligibility state (architecture v5 §14/§15.1):
 * TECHNICAL_FALLBACK | LEGACY_ADDRESS_FALLBACK, never derived from failure reasons.
 */
class FallbackEligibilityTest extends TestCase
{
    public function testTechnicalSource(): void
    {
        $eligibility = FallbackEligibility::technical();

        $this->assertTrue($eligibility->hasTechnicalFallbackEligibility());
        $this->assertFalse($eligibility->hasLegacyAddressFallbackEligibility());
        $this->assertTrue($eligibility->isEligible());
        $this->assertSame([FallbackEligibilitySource::TECHNICAL_FALLBACK], $eligibility->getSources());
    }

    public function testLegacyAddressSource(): void
    {
        $eligibility = FallbackEligibility::legacyAddress();

        $this->assertTrue($eligibility->hasLegacyAddressFallbackEligibility());
        $this->assertFalse($eligibility->hasTechnicalFallbackEligibility());
        $this->assertTrue($eligibility->isEligible());
        $this->assertSame([FallbackEligibilitySource::LEGACY_ADDRESS_FALLBACK], $eligibility->getSources());
    }

    public function testNoneMeansNotEligible(): void
    {
        $eligibility = FallbackEligibility::none();

        $this->assertFalse($eligibility->isEligible());
        $this->assertSame([], $eligibility->getSources());
    }

    public function testIntegrationLimitationSource(): void
    {
        // TASK-8MQHJX Phase C amendment — third frozen source.
        $eligibility = FallbackEligibility::integrationLimitation();

        $this->assertTrue($eligibility->hasIntegrationLimitationEligibility());
        $this->assertFalse($eligibility->hasTechnicalFallbackEligibility());
        $this->assertFalse($eligibility->hasLegacyAddressFallbackEligibility());
        $this->assertTrue($eligibility->isEligible());
        $this->assertSame([FallbackEligibilitySource::INTEGRATION_LIMITATION], $eligibility->getSources());
    }

    public function testCombinedSourcesInDeterministicOrder(): void
    {
        $eligibility = new FallbackEligibility(true, true);

        $this->assertTrue($eligibility->isEligible());
        $this->assertSame(
            [FallbackEligibilitySource::TECHNICAL_FALLBACK, FallbackEligibilitySource::LEGACY_ADDRESS_FALLBACK],
            $eligibility->getSources()
        );
    }

    public function testAllSourcesOrderTechnicalFirstIntegrationLast(): void
    {
        $eligibility = new FallbackEligibility(true, true, true);

        $this->assertTrue($eligibility->isEligible());
        $this->assertSame(
            [
                FallbackEligibilitySource::TECHNICAL_FALLBACK,
                FallbackEligibilitySource::LEGACY_ADDRESS_FALLBACK,
                FallbackEligibilitySource::INTEGRATION_LIMITATION,
            ],
            $eligibility->getSources()
        );
    }
}
