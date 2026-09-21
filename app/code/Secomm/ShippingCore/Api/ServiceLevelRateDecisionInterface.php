<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api;

use Secomm\ShippingCore\Api\Fallback\FallbackRateInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;

/**
 * TASK-M3ME32 (Phase E-SL2) — final service-level availability decision: where does the price
 * for this service level come from?
 *
 * REALTIME  — usable realtime carrier rates exist (all of them are exposed, unranked; carrier
 *             identity stays in the keys — selection is nobody's job in ShippingCore).
 * FALLBACK  — no realtime rate, but the emergency fallback provider returned an explicit rate
 *             for the (policy-enabled, technically-failed) service level. Fallback is emergency
 *             pricing for a SERVICE LEVEL — never attached to a carrier identity.
 * UNAVAILABLE — no rate of any kind for this service level.
 *
 * No reason taxonomy: the three sources are sufficient for the Launchpad scope; richer
 * diagnostics stay in the upstream outcomes/aggregate.
 */
interface ServiceLevelRateDecisionInterface
{
    public const SOURCE_REALTIME = 'REALTIME';
    public const SOURCE_FALLBACK = 'FALLBACK';
    public const SOURCE_UNAVAILABLE = 'UNAVAILABLE';

    public function getServiceLevelCode(): string;

    /** SOURCE_REALTIME | SOURCE_FALLBACK | SOURCE_UNAVAILABLE. */
    public function getSource(): string;

    /**
     * Realtime rates keyed by carrier code (as produced by the E-SL1 aggregate — unranked,
     * unmodified); empty unless the source is REALTIME.
     *
     * @return array<string, CarrierRateInterface>
     */
    public function getRealtimeRates(): array;

    /** The emergency fallback rate when the source is FALLBACK; null otherwise. */
    public function getFallbackRate(): ?FallbackRateInterface;

    /** True for REALTIME and FALLBACK; false for UNAVAILABLE. */
    public function isAvailable(): bool;
}
