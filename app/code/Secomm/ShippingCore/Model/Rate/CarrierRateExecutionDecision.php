<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Rate;

use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateExecutionDecisionInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Fallback\FallbackEligibility;

/**
 * TASK-8MQHJX (Phase C) — immutable execution result VO; @see CarrierRateExecutionDecisionInterface.
 *
 * Composition of existing domain values only (outcome, handoff, fallback eligibility) — no
 * parallel domain model. Named factories document the execution branches.
 */
final class CarrierRateExecutionDecision implements CarrierRateExecutionDecisionInterface
{
    /**
     * @param bool $shouldInvokeRealtime
     * @param CarrierRateOutcomeInterface|null $realtimeOutcome
     * @param CarrierAddressHandoffInterface|null $carrierFacingHandoff
     * @param FallbackEligibilityInterface $fallbackEligibility
     * @param string|null $reason
     */
    public function __construct(
        private readonly bool $shouldInvokeRealtime,
        private readonly ?CarrierRateOutcomeInterface $realtimeOutcome = null,
        private readonly ?CarrierAddressHandoffInterface $carrierFacingHandoff = null,
        private readonly FallbackEligibilityInterface $fallbackEligibility = new FallbackEligibility(),
        private readonly ?string $reason = null
    ) {
        if ($this->shouldInvokeRealtime xor $this->realtimeOutcome !== null) {
            throw new \LogicException(
                'A decision with a realtime path must carry its outcome; one without must not.'
            );
        }
    }

    public function shouldInvokeRealtime(): bool
    {
        return $this->shouldInvokeRealtime;
    }

    public function getRealtimeOutcome(): ?CarrierRateOutcomeInterface
    {
        return $this->realtimeOutcome;
    }

    public function getCarrierFacingHandoff(): ?CarrierAddressHandoffInterface
    {
        return $this->carrierFacingHandoff;
    }

    public function getFallbackEligibility(): FallbackEligibilityInterface
    {
        return $this->fallbackEligibility;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
