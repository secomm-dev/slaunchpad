<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Failure;

/**
 * TASK-NAT3YV r1 (TL/SA review) — the ONE canonical owner of SHARED, cross-stage ShippingCore
 * failure reasons. Contracts that report reasons (carrier address handoff, carrier rate
 * outcome, future orchestration surfaces) reference these constants instead of declaring
 * duplicate strings.
 *
 * Taxonomy is deliberately SMALL: only reasons that are meaningful across stages. Carrier
 * modules keep detailed provider-specific reason strings (e.g. GHN_LOCATION_NOT_FOUND,
 * AHAMOVE_OUTSIDE_COVERAGE) as free-form diagnostics — never centralized here, no
 * registry/database/enum.
 *
 * ORCHESTRATION RULE: the outcome/handoff STATUS drives runtime behavior; a reason string is
 * diagnostic context only. Later service-level orchestration must never infer fallback
 * eligibility from reason strings (e.g. UNAVAILABLE carrying TECHNICAL_ERROR remains
 * UNAVAILABLE — never fallback-triggering).
 */
final class ShippingFailureReason
{
    /** Destination outside the Vietnam canonical scope (Stage-1 handoff). */
    public const UNSUPPORTED_DESTINATION = 'UNSUPPORTED_DESTINATION';

    /** Canonical resolution did not produce a usable target identity (Stage 1). */
    public const CANONICAL_UNRESOLVED = 'CANONICAL_UNRESOLVED';

    /**
     * Canonical resolution produced MORE THAN ONE candidate (snapshot failure_class=AMBIGUOUS) —
     * a specific, structured unresolved state. Semantically DISTINCT from TECHNICAL_ERROR and
     * never converted into it; by safe-degradation policy (TASK-5XQXZK, DEC-TASK5XQXZK-001)
     * this reason IS fallback-eligible.
     */
    public const CANONICAL_AMBIGUOUS = 'CANONICAL_AMBIGUOUS';

    /**
     * Canonical resolution produced ZERO candidates (snapshot failure_class=UNMAPPED) —
     * distinct from AMBIGUOUS; NOT fallback-eligible by default.
     */
    public const CANONICAL_UNMAPPED = 'CANONICAL_UNMAPPED';

    /** Canonical identity resolved but the carrier has no provider mapping (Stage 2 — deterministic data/config state). */
    public const PROVIDER_MAPPING_MISSING = 'PROVIDER_MAPPING_MISSING';

    /** Carrier/business cannot serve the request (route, service, auth/config…). */
    public const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';

    /** Merchant-side carrier configuration invalid/unusable (never TECHNICAL_FAILURE). */
    public const INVALID_CONFIGURATION = 'INVALID_CONFIGURATION';

    /** Temporary technical/provider failure (timeout, HTTP 5xx, outage…). */
    public const TECHNICAL_ERROR = 'TECHNICAL_ERROR';
}
