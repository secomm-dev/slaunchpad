<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — approved safe-degradation eligibility policy.
 * Updated to architecture v9/v10 §35.5 fallback-eligibility normalization (TASK-MD2BD3).
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Fallback;

use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityPolicyInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;

/**
 * v10 (§35.5) default eligibility map — carriers report FACTS (status + structured reason);
 * this policy decides. Outcome STATUS semantics never change:
 *
 *   TECHNICAL_FAILURE                       → YES (TECHNICAL_FALLBACK)
 *   UNAVAILABLE + CANONICAL_AMBIGUOUS       → YES (LEGACY_ADDRESS_FALLBACK — address policy
 *                                             gate applied by the composition/consumer)
 *   UNAVAILABLE + PROVIDER_MAPPING_MISSING  → YES (INTEGRATION_LIMITATION — v9 §35.5; outcome
 *                                             stays UNAVAILABLE, never reclassified)
 *   everything else                         → NO:
 *     CANONICAL_UNMAPPED, UNSUPPORTED_DESTINATION, SERVICE_UNAVAILABLE (coverage/real business
 *     rejection), CANONICAL_UNRESOLVED, invalid customer address.
 *
 * Auth/configuration failures are CONFIGURABLE (§35.5: configurable YES + mandatory
 * high-severity warning when enabled) — extend `eligibleUnavailableReasons` with
 * ShippingFailureReason::INVALID_CONFIGURATION via DI to opt in; the consumer composition owns
 * the operational warning seam. Carrier-owned free-form diagnostic codes can never unlock
 * fallback — only ShippingCore-owned constants are ever compared, no message parsing.
 */
class SafeDegradationEligibilityPolicy implements FallbackEligibilityPolicyInterface
{
    /**
     * @param array<int, string> $eligibleUnavailableReasons ShippingFailureReason::* values that
     *        make an UNAVAILABLE outcome fallback-eligible (default per v10 §35.5:
     *        CANONICAL_AMBIGUOUS + PROVIDER_MAPPING_MISSING)
     */
    public function __construct(
        private readonly array $eligibleUnavailableReasons = [
            ShippingFailureReason::CANONICAL_AMBIGUOUS,
            ShippingFailureReason::PROVIDER_MAPPING_MISSING,
        ]
    ) {
    }

    public function isFallbackEligible(string $status, ?string $failureReason): bool
    {
        if ($status === CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE) {
            return true;
        }

        if ($status === CarrierRateOutcomeInterface::STATUS_UNAVAILABLE
            && $failureReason !== null
            && in_array($failureReason, $this->eligibleUnavailableReasons, true)
        ) {
            return true;
        }

        return false;
    }
}
