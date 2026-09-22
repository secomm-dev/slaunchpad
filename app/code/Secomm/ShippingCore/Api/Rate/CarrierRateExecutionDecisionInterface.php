<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Rate;

use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;

/**
 * TASK-8MQHJX (Phase C, architecture v10 §35) — immutable result of ONE carrier RATE
 * execution. Coordinates EXISTING domain values (handoff, outcome, fallback eligibility) —
 * it deliberately introduces NO parallel domain model.
 *
 * Downstream ownership stays untouched: service-level aggregation, final fallback dispatch,
 * realtime-success suppression and method selection are NOT decided here — a decision is
 * exactly one carrier's contribution metadata for one destination.
 */
interface CarrierRateExecutionDecisionInterface
{
    /**
     * True when the execution service invoked (or gated execution through to) the realtime
     * contributor — i.e. the realtime path was taken. False for ineligible carriers,
     * FALLBACK_ONLY, unresolved origin in a realtime mode, and address-policy-blocked states.
     */
    public function shouldInvokeRealtime(): bool;

    /** The realtime outcome when the contributor ran; null when the realtime path never ran. */
    public function getRealtimeOutcome(): ?CarrierRateOutcomeInterface;

    /** The final carrier-facing handoff when the address stage was reached; null before it. */
    public function getCarrierFacingHandoff(): ?CarrierAddressHandoffInterface;

    /**
     * Fallback contribution metadata: FallbackEligibility::none() means NO fallback
     * contribution (ineligible carrier, CARRIER_ONLY, STRICT block, suppressed after SUCCESS…);
     * a state with sources means the downstream fallback orchestration MAY consider this
     * carrier's service level. Emitting eligibility never triggers a fallback by itself.
     */
    public function getFallbackEligibility(): FallbackEligibilityInterface;

    /**
     * Path-level diagnostic (ShippingFailureReason::*, the eligibility reason, or the realtime
     * outcome status for suppressed fallbacks); null when there is nothing to add beyond the
     * outcome itself. Diagnostic only — status/eligibility fields are the semantics.
     */
    public function getReason(): ?string;
}
