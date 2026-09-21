<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Fallback;

/**
 * TASK-M3ME32 (Phase E-SL2) — per-service-level emergency-fallback policy.
 *
 * ONE question only: is fallback pricing enabled for this service-level code? The policy knows
 * nothing about aggregates, outcomes, or reasons — eligibility gating happens in the
 * orchestrator (SUCCESS suppresses fallback entirely; only TECHNICAL_FAILURE reaches the
 * policy).
 *
 * Storage (default implementation): DI mapping — lean, version-controlled, no DB/admin CRUD
 * today; an admin-config-backed implementation can replace it later via preference without any
 * contract change. Unlisted service levels are DENIED by default: emergency pricing is opt-in
 * per level (SLA-sensitive levels stay off unless explicitly enabled).
 */
interface FallbackPolicyInterface
{
    public function isEnabled(string $serviceLevelCode): bool;
}
