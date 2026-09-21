<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api;

use Secomm\ShippingCore\Api\Fallback\FallbackRateRequestInterface;

/**
 * TASK-M3ME32 (Phase E-SL2 — FINAL ShippingCore foundation slice) — decides where the price for
 * one service level comes from: REALTIME (E-SL1 aggregate has successful rates), FALLBACK
 * (emergency provider, only when the level is enabled + no realtime success + a technical
 * failure occurred + policy enabled + exactly one provider), or UNAVAILABLE.
 *
 * Business rules enforced here (directive, architecture v5):
 * - eligibility has EXACTLY two sources: TECHNICAL_FALLBACK (aggregate technical failure) and
 *   LEGACY_ADDRESS_FALLBACK (caller-supplied, merchant legacy RATE strategy) - reason strings
 *   are never parsed;
 * - any realtime SUCCESS suppresses fallback — the provider is never called;
 * - UNAVAILABLE alone never triggers fallback;
 * - a disabled service level is UNAVAILABLE (its realtime rates are not exposed);
 * - the fallback provider is called at most ONCE, only on the valid fallback path;
 * - the caller supplies the ready FallbackRateRequest — no Magento rate-request leakage.
 *
 * This completes the ShippingCore foundation flow:
 * address handoff → carrier API/provider mapping → CarrierRateOutcome →
 * ServiceLevelRateAggregate → ServiceLevelRateDecision.
 * No selection, no routing, no retry — further generic architecture requires evidence from
 * real carrier/bridge integration first.
 */
interface ServiceLevelRateOrchestratorInterface
{
    /**
     * @param string $serviceLevelCode a REGISTERED service-level machine code
     * @throws \Magento\Framework\Exception\LocalizedException unknown service-level code
     * @throws \LogicException aggregate/code mismatch, or more than one fallback provider
     *         registered (configuration ambiguity — never silently routed)
     */
    public function decide(
        string $serviceLevelCode,
        ServiceLevelRateAggregateInterface $aggregate,
        FallbackRateRequestInterface $fallbackRequest
    ): ServiceLevelRateDecisionInterface;
}
