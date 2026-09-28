<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Rate;

use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateDecisionRecordInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;

/**
 * TASK-SEC-D-transport — immutable decision record; @see CarrierRateDecisionRecordInterface.
 *
 * Named factories make the transport presence explicit at construction:
 * `withTransport(outcome, eligibility)` vs `legacyOutcomeOnly(outcome)` — the two states can
 * never be confused by a nullable accident.
 */
final class CarrierRateDecisionRecord implements CarrierRateDecisionRecordInterface
{
    public function __construct(
        private readonly string $carrierCode,
        private readonly string $methodCode,
        private readonly CarrierRateOutcomeInterface $outcome,
        private readonly bool $hasEligibilityTransport,
        private readonly ?FallbackEligibilityInterface $fallbackEligibility = null
    ) {
        if ($this->hasEligibilityTransport xor $this->fallbackEligibility !== null) {
            throw new \LogicException(
                'A decision record with eligibility transport must carry its eligibility; one without must not.'
            );
        }
    }

    public static function withTransport(
        string $carrierCode,
        string $methodCode,
        CarrierRateOutcomeInterface $outcome,
        FallbackEligibilityInterface $fallbackEligibility
    ): self {
        return new self($carrierCode, $methodCode, $outcome, true, $fallbackEligibility);
    }

    public static function legacyOutcomeOnly(
        string $carrierCode,
        string $methodCode,
        CarrierRateOutcomeInterface $outcome
    ): self {
        return new self($carrierCode, $methodCode, $outcome, false, null);
    }

    public function getCarrierCode(): string
    {
        return $this->carrierCode;
    }

    public function getMethodCode(): string
    {
        return $this->methodCode;
    }

    public function getOutcome(): CarrierRateOutcomeInterface
    {
        return $this->outcome;
    }

    public function hasEligibilityTransport(): bool
    {
        return $this->hasEligibilityTransport;
    }

    public function getFallbackEligibility(): ?FallbackEligibilityInterface
    {
        return $this->fallbackEligibility;
    }
}
