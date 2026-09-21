<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\ServiceLevel;

use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;
use Secomm\ShippingCore\Api\ServiceLevelRateAggregateInterface;

/**
 * TASK-32ACTR — immutable service-level rate aggregate VO; @see ServiceLevelRateAggregateInterface.
 */
final class ServiceLevelRateAggregate implements ServiceLevelRateAggregateInterface
{
    /**
     * @param string $serviceLevelCode registered machine code
     * @param array<string, CarrierRateInterface> $successfulRates carrier-code keyed, input order
     * @param bool $hasTechnicalFailure any TECHNICAL_FAILURE outcome present
     * @param int $outcomeCount total outcomes aggregated
     */
    public function __construct(
        private readonly string $serviceLevelCode,
        private readonly array $successfulRates,
        private readonly bool $hasTechnicalFailure,
        private readonly int $outcomeCount,
        private readonly ?FallbackEligibilityInterface $fallbackEligibility = null
    ) {
    }

    public function getServiceLevelCode(): string
    {
        return $this->serviceLevelCode;
    }

    public function getSuccessfulRates(): array
    {
        return $this->successfulRates;
    }

    public function hasSuccessfulRate(): bool
    {
        return $this->successfulRates !== [];
    }

    public function hasTechnicalFailure(): bool
    {
        return $this->hasTechnicalFailure;
    }

    public function getOutcomeCount(): int
    {
        return $this->outcomeCount;
    }

    public function getFallbackEligibility(): ?\Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface
    {
        return $this->fallbackEligibility;
    }

    public function hasLegacyAddressFallbackEligibility(): bool
    {
        return $this->fallbackEligibility?->hasLegacyAddressFallbackEligibility() ?? false;
    }
}
