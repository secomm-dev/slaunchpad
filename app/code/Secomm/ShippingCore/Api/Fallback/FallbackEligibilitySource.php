<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Fallback;

/**
 * TASK-5JQYMP (architecture Revision v5 §14/§15.1, DEC-FEATYA2C0W-005) — the proven sources of
 * service-level fallback eligibility. Orchestration state, not a failure classification: an
 * UNAVAILABLE outcome carrying a technical-looking reason is still UNAVAILABLE and still never
 * fallback-triggering by itself.
 *
 * TASK-8MQHJX Phase C amendment (TL-approved, closes an implementation-discovered
 * representational gap in the already-frozen v10 §35.5 fallback semantics — no v11):
 * INTEGRATION_LIMITATION was always named by §35.5 ("supersede §14: 2 nguồn") but the
 * two-source contract could not represent it without mislabeling. Adding it is a generic
 * contract amendment, not carrier-specific behavior.
 */
final class FallbackEligibilitySource
{
    /** Temporary technical/provider failure (timeout, connection, 5xx, outage). */
    public const TECHNICAL_FALLBACK = 'TECHNICAL_FALLBACK';

    /** Merchant opt-in legacy RATE strategy (FALLBACK_ONLY / MAP_THEN_FALLBACK+AMBIGUOUS/UNMAPPED). */
    public const LEGACY_ADDRESS_FALLBACK = 'LEGACY_ADDRESS_FALLBACK';

    /**
     * Realtime contribution could not be produced because shared/provider integration data,
     * mapping, adapter readiness or equivalent integration capability is insufficient —
     * AND the failure is NOT a confirmed carrier business/service rejection (service-area
     * rejection, hard business limits, customer/business validation rejections stay
     * SERVICE_UNAVAILABLE-class and never degrade). Canonical frozen case:
     * UNAVAILABLE + ShippingFailureReason::PROVIDER_MAPPING_MISSING. Deliberately NOT used
     * for ShippingFailureReason::INVALID_CONFIGURATION, which is defined as merchant-side
     * carrier configuration and must fail closed (§35.5's configurable auth/config seam
     * needs its own provider-auth reason + warning seam before it can ever degrade safely).
     */
    public const INTEGRATION_LIMITATION = 'INTEGRATION_LIMITATION';
}
