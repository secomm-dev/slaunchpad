<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Rate;

/**
 * TASK-8MQHJX (Phase C, architecture v10 §35) — shared provider-neutral execution gating for
 * ONE carrier RATE operation:
 *
 *   Canonical Destination → origin readiness → CarrierEligibility → RateSourceMode
 *   → [realtime modes only] AddressResolutionPolicy → carrier-facing handoff
 *   → realtime contributor → contribution emission (outcome + fallback eligibility).
 *
 * Hard invariants (frozen v10):
 *  - eligibility short-circuits EVERYTHING downstream for an ineligible carrier — no mode
 *    evaluation, no policy evaluation, no handoff, no realtime, no fallback contribution;
 *  - FALLBACK_ONLY skips origin/address/policy/realtime entirely (normal fallback behavior
 *    for an eligible carrier) — it never silently mutates into a realtime mode;
 *  - a realtime mode with an unresolved origin is NOT executable and never re-moded: it fails
 *    closed (CARRIER_ONLY → nothing; CARRIER_WITH_FALLBACK → eligibility per the shared policy
 *    for UNAVAILABLE+INVALID_CONFIGURATION, default not eligible);
 *  - CARRIER_ONLY never emits fallback eligibility; CARRIER_WITH_FALLBACK emits eligibility
 *    only where the shared FallbackEligibilityPolicy says the failure is eligible.
 *
 * This service NEVER dispatches a fallback provider, aggregates carriers, selects winning
 * rates, or suppresses fallback globally — those remain with the service-level orchestrator
 * and the composition layer. It emits ONE carrier contribution.
 */
interface CarrierRateExecutionServiceInterface
{
    public function execute(
        CarrierRateExecutionRequestInterface $request
    ): CarrierRateExecutionDecisionInterface;
}
