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
 * are never merged). TASK-WNQCRW (2026-10-01, DEC-TASKWNQCRW-001) — provenance stays SPLIT
 * into four distinct claims:
 *
 * MAX_DIMENSION_CM = 200
 *   DOCUMENTED (Create contract per-side cap) — TASK-ZS2B41 rev. 2026-10-01: the SHARED
 *   default for both RATE and CREATE (GhnShipmentConstraints::MAX_SIDE_CM). The former
 *   separate RATE default 150 — SANDBOX_OBSERVED (create probe 2026-09-18, staging:
 *   provider 400 "Kích thước (dài) vượt quá mức cho phép: 150") — is SUPERSEDED as the
 *   default by user decision; merchants lower carriers/secomm_ghn/max_{length,width,
 *   height}_cm in admin to re-enforce 150 on accounts that behave that way.
 *
 * MAX_WEIGHT_G = 50,000
 *   DOCUMENTED (GHN CREATE per-package hard capability) used as the CHECKOUT ELIGIBILITY
 *   GUARD default — merchant-tunable via carriers/secomm_ghn/max_package_weight_g
 *   (TASK-WNQCRW rev. of DEC-TASKFXFMJ0-001's "no RATE weight cap" clause). Calculate Fee
 *   itself has NO provider-enforced 50kg upper bound (SANDBOX_OBSERVED 2026-09-30, matrix
 *   12/12: single 50.001/60kg quote 200) — so RAISING the config lets heavy units quote;
 *   CREATE still enforces 50000 per physical package regardless. Aggregate weight is NEVER
 *   capped (SANDBOX_OBSERVED: 70kg/120kg aggregates quote) as long as every unit passes.
 *
 * Reason codes are emitted ONLY when a trusted constraint is violated — dimensions must be
 * PRESENT to reject; weight is ALWAYS present (the estimator rejects non-positive weights
 * upstream). Missing/untrusted data never misclassifies as a carrier rejection (brief §13).
 * `GHN_HEAVY_RATE_ESTIMATION_UNAVAILABLE` stays RESERVED (no emitter).
 */
final class GhnPackageLimits
{
    /** Authoritative shared default (200, Create contract) — BC default of QuoteParcelEstimate's optional ctor params. */
    public const MAX_DIMENSION_CM = GhnShipmentConstraints::MAX_SIDE_CM;

    /** TASK-WNQCRW (2026-10-01) — authoritative default of the RATE weight display gate (grams); = frozen CREATE per-package cap. */
    public const MAX_WEIGHT_G = GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G;

    /** Reason codes emitted only for PRESENT-and-violating trusted dimensions. */
    public const REASON_PACKAGE_LENGTH_LIMIT_EXCEEDED = 'GHN_PACKAGE_LENGTH_LIMIT_EXCEEDED';
    public const REASON_PACKAGE_WIDTH_LIMIT_EXCEEDED = 'GHN_PACKAGE_WIDTH_LIMIT_EXCEEDED';
    public const REASON_PACKAGE_HEIGHT_LIMIT_EXCEEDED = 'GHN_PACKAGE_HEIGHT_LIMIT_EXCEEDED';

    /** TASK-WNQCRW — restored emitter: merchant-tunable RATE weight display gate (history:
     *  created under DEC-TASKMQ2DRG-001, removed by DEC-TASKFXFMJ0-001, restored 2026-10-01). */
    public const REASON_PACKAGE_WEIGHT_LIMIT_EXCEEDED = 'GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED';

    /** Adapter/data limitation — NOT a carrier rejection (fallback-eligible upstream). */
    public const REASON_HEAVY_RATE_ESTIMATION_UNAVAILABLE = 'GHN_HEAVY_RATE_ESTIMATION_UNAVAILABLE';

    private function __construct()
    {
    }
}
