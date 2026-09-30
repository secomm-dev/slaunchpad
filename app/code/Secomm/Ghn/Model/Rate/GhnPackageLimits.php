<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

use Secomm\Ghn\Model\GhnShipmentConstraints;

/**
 * TASK-WAWNDS — GHN parcel hard limits with EXPLICIT provenance (brief §8: evidence sources
 * are never merged).
 *
 * MAX_DIMENSION_CM = 150
 *   SANDBOX_OBSERVED hard rejection: create probe (2026-09-18, staging) → provider 400
 *   "Kích thước (dài) vượt quá mức cho phép: 150". This CONFLICTS with the official docs'
 *   "Maximum: 200 (cm)" — the sandbox value wins for rate-time eligibility because it is the
 *   provider behavior actually enforced.
 *
 * PER-PARCEL WEIGHT (TASK-MQ2DRG, 2026-09-23 — SUPERSEDES the "UNSETTLED / NO weight
 *   pre-rejection" stance of TASK-WAWNDS, DEC-TASKMQ2DRG-001):
 *   The sandbox FEE API still enforces neither aggregate nor per-parcel weight (2×35kg and a
 *   single 60kg both quoted 200) — that provider tolerance remains a documented FACT. It is
 *   now nevertheless a deliberate business NO: checkout cannot know the final packing, so a
 *   >50kg aggregate is pre-validated away (RATE_REQUEST_UNREPRESENTABLE) and a single unit
 *   >50kg is a hard carrier rejection (GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED) — both BEFORE the
 *   fee call. See {@see \Secomm\Ghn\Model\GhnShipmentConstraints} for per-value provenance.
 *
 * Reason codes are emitted ONLY when a trusted constraint is PRESENT and violated —
 * missing/untrusted data never misclassifies as a carrier rejection (brief §13).
 * `GHN_HEAVY_RATE_ESTIMATION_UNAVAILABLE` stays RESERVED (no emitter — the weight
 * pre-validation now emits REASON_PACKAGE_WEIGHT_LIMIT_EXCEEDED / the shared
 * RATE_REQUEST_UNREPRESENTABLE instead).
 */
final class GhnPackageLimits
{
    /** SANDBOX_OBSERVED (staging create probe, 2026-09-18) — supersedes docs' 200cm at runtime. */
    public const MAX_DIMENSION_CM = GhnShipmentConstraints::RATE_MAX_SIDE_CM;

    /** Reason codes emitted only for PRESENT-and-violating trusted dimensions. */
    public const REASON_PACKAGE_LENGTH_LIMIT_EXCEEDED = 'GHN_PACKAGE_LENGTH_LIMIT_EXCEEDED';
    public const REASON_PACKAGE_WIDTH_LIMIT_EXCEEDED = 'GHN_PACKAGE_WIDTH_LIMIT_EXCEEDED';
    public const REASON_PACKAGE_HEIGHT_LIMIT_EXCEEDED = 'GHN_PACKAGE_HEIGHT_LIMIT_EXCEEDED';

    /** Adapter/data limitation — NOT a carrier rejection (fallback-eligible upstream). */
    public const REASON_HEAVY_RATE_ESTIMATION_UNAVAILABLE = 'GHN_HEAVY_RATE_ESTIMATION_UNAVAILABLE';

    /**
     * TASK-MQ2DRG — a single sellable unit exceeds the per-package weight cap
     * ({@see \Secomm\Ghn\Model\GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G}): REAL carrier
     * rejection, never fallback-eligible (§35.5 — business rejection must not be masked).
     */
    public const REASON_PACKAGE_WEIGHT_LIMIT_EXCEEDED = 'GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED';

    private function __construct()
    {
    }
}
