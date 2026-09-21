<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api;

use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;

/**
 * TASK-32ACTR (Phase E-SL1) — aggregate of the realtime carrier outcomes collected for ONE
 * dynamic service level. Pure aggregation state: it does NOT select a carrier, compute fallback
 * pricing, or decide availability — those belong to later orchestration (E-SL2).
 *
 * Carrier identity is preserved: successful rates keep the carrier-code keys the caller supplied
 * (Option A — no wrapper DTO), so later checkout/order/fulfillment handoff knows which carrier
 * produced which rate. Order of successful entries matches the input order — no business ranking.
 */
interface ServiceLevelRateAggregateInterface
{
    /** The (registered) service-level machine code this aggregate belongs to. */
    public function getServiceLevelCode(): string;

    /**
     * Successful realtime rates keyed by carrier code, input order preserved (all SUCCESS
     * outcomes are kept — selection, if ever needed, is a later concern).
     *
     * @return array<string, CarrierRateInterface>
     */
    public function getSuccessfulRates(): array;

    /** True when at least one realtime rate exists. */
    public function hasSuccessfulRate(): bool;

    /**
     * True when at least one outcome was TECHNICAL_FAILURE (diagnostic reasons are ignored —
     * status only). Unaffected by concurrent SUCCESS outcomes; the "any SUCCESS ⇒ no fallback"
     * rule belongs to E-SL2, not to this aggregate.
     */
    public function hasTechnicalFailure(): bool;

    /** Total number of outcomes aggregated (diagnostics/tests). */
    public function getOutcomeCount(): int;

    /**
     * TASK-5JQYMP (architecture v5 §14) — explicit fallback eligibility carried alongside the
     * realtime aggregate (e.g. LEGACY_ADDRESS_FALLBACK declared by a legacy RATE strategy);
     * null when the caller supplied none (technical eligibility is still derivable from
     * hasTechnicalFailure()).
     */
    public function getFallbackEligibility(): ?\Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;

    /** Convenience: explicit legacy-address eligibility carried on the aggregate. */
    public function hasLegacyAddressFallbackEligibility(): bool;
}
