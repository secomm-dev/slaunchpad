<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — safe-degradation fallback eligibility policy contract.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Fallback;

use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;

/**
 * ShippingCore owns the POLICY "may a fallback price be attempted because a realtime carrier
 * quote could not be obtained for an approved safe-degradation reason?". Carriers report FACTS
 * (status + structured {@see \Secomm\ShippingCore\Api\Failure\ShippingFailureReason}); this
 * policy decides eligibility — nobody may set arbitrary fallbackEligible flags, and no code may
 * parse free-form failure message strings.
 *
 * Canonical question, single outcome in / boolean out. Composition-specific aggregation (e.g.
 * "any member SUCCESS suppresses") stays with the caller — this contract judges ONE outcome.
 */
interface FallbackEligibilityPolicyInterface
{
    /**
     * @param string $status CarrierRateOutcomeInterface::STATUS_* value
     * @param string|null $failureReason ShippingFailureReason::* or a carrier-owned diagnostic
     *        string (carrier-owned strings are deliberately NOT policy inputs unless whitelisted
     *        by the policy implementation)
     */
    public function isFallbackEligible(string $status, ?string $failureReason): bool;
}
