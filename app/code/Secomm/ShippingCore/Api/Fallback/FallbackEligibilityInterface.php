<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Fallback;

/**
 * TASK-5JQYMP (architecture Revision v5 §14/§15.1, DEC-FEATYA2C0W-005) — explicit, provider-
 * neutral service-level fallback eligibility state.
 *
 * Separates FALLBACK ELIGIBILITY from FAILURE SEMANTICS: outcomes stay
 * SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE (an UNAVAILABLE outcome is never reclassified), while
 * eligibility is an orchestration state with proven sources — TECHNICAL_FALLBACK (temporary
 * technical/provider failure), LEGACY_ADDRESS_FALLBACK (merchant opt-in legacy RATE strategy)
 * and INTEGRATION_LIMITATION (TASK-8MQHJX Phase C amendment: shared/provider integration data,
 * mapping or adapter readiness insufficient — never a confirmed business rejection). Never
 * derived by parsing failure-reason strings.
 */
interface FallbackEligibilityInterface
{
    /** A temporary technical/provider failure makes the service level fallback-eligible. */
    public function hasTechnicalFallbackEligibility(): bool;

    /**
     * A merchant-declared legacy RATE strategy makes the service level fallback-eligible for
     * AMBIGUOUS/UNMAPPED/skipped-mapping contributions.
     */
    public function hasLegacyAddressFallbackEligibility(): bool;

    /**
     * A shared/provider integration limitation (e.g. provider mapping missing) makes the
     * service level fallback-eligible — the outcome stays UNAVAILABLE, never reclassified.
     */
    public function hasIntegrationLimitationEligibility(): bool;

    /** True when at least one eligibility source applies. */
    public function isEligible(): bool;

    /**
     * Applied sources, deterministic order (TECHNICAL_FALLBACK first, INTEGRATION_LIMITATION
     * last).
     *
     * @return string[] FallbackEligibilitySource::*
     */
    public function getSources(): array;
}
