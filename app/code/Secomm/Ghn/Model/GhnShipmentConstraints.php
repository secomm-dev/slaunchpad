<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model;

/**
 * TASK-MQ2DRG — single source of truth for GHN shipment weight/dimension constraints
 * (directive §9: no duplicated 20/50kg literals across classes). Every value carries its
 * OWN provenance — evidence sources are never merged (same rule as GhnPackageLimits).
 *
 * Per the evidence-merging rule the values below deliberately have DIFFERENT provenance:
 *
 * TYPE_2_MAX_WEIGHT_G = 20,000 g — DOCUMENTED (GHN fee contract: total < 20kg single package
 *   → service_type 2; ≥ 20kg → type 5). Sandbox behaviour matches.
 *
 * TYPE_5_MAX_WEIGHT_G = 50,000 g — DOCUMENTED (Create contract per-package cap; enforced
 *   fail-closed at CREATE by GhnPhysicalParcelInterpreter). At RATE this value is a BUSINESS
 *   DECISION (TL + user approval 2026-09-23, DEC-TASKMQ2DRG-001): the fee API itself tolerates
 *   heavier aggregates (sandbox-proven), but checkout cannot know the final packing, so a
 *   >50kg aggregate is not an authoritative request and is pre-validated away
 *   (RATE_REQUEST_UNREPRESENTABLE) instead of being quoted. This is NOT provider-observed
 *   enforcement.
 *
 * MAX_SIDE_CM = 200 cm — DOCUMENTED (Create contract; enforced at CREATE by
 *   GhnPhysicalParcelInterpreter).
 *
 * RATE_MAX_SIDE_CM = 150 cm — SANDBOX_OBSERVED (staging create probe 2026-09-18: provider
 *   400 "Kích thước (dài) vượt quá mức cho phép: 150"); supersedes the documented 200cm at
 *   RATE because it is the behaviour actually enforced. (Dimensions are only enforced when a
 *   trusted, present dimension violates — missing dimensions never reject.)
 */
final class GhnShipmentConstraints
{
    public const TYPE_2_MAX_WEIGHT_G = 20000;

    public const TYPE_5_MAX_WEIGHT_G = 50000;

    public const MAX_SIDE_CM = 200;

    public const RATE_MAX_SIDE_CM = 150;

    private function __construct()
    {
    }
}
