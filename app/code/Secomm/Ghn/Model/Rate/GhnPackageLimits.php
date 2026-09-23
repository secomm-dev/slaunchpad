<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

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
 * PER-PARCEL WEIGHT: UNSETTLED
 *   Docs say 50,000 g at CREATE root; the sandbox FEE API enforces neither aggregate nor
 *   per-parcel weight (2×35kg and a single 60kg both quoted 200). CREATE-side weight probes
 *   were blocked by upstream tenant-api timeouts (2026-09-18) → enforcement DEFERRED until a
 *   provider-verified rejection exists. NO weight pre-rejection at RATE (brief §8: never
 *   enforce an unproven aggregate/parcel cap).
 *
 * Reason codes are emitted ONLY when a trusted package dimension is PRESENT and exceeds the
 * verified limit — missing/untrusted data is `GHN_HEAVY_RATE_ESTIMATION_UNAVAILABLE`
 * (adapter/data limitation), never a carrier rejection (brief §13).
 */
final class GhnPackageLimits
{
    /** SANDBOX_OBSERVED (staging create probe, 2026-09-18) — supersedes docs' 200cm at runtime. */
    public const MAX_DIMENSION_CM = 150;

    /** Reason codes emitted only for PRESENT-and-violating trusted dimensions. */
    public const REASON_PACKAGE_LENGTH_LIMIT_EXCEEDED = 'GHN_PACKAGE_LENGTH_LIMIT_EXCEEDED';
    public const REASON_PACKAGE_WIDTH_LIMIT_EXCEEDED = 'GHN_PACKAGE_WIDTH_LIMIT_EXCEEDED';
    public const REASON_PACKAGE_HEIGHT_LIMIT_EXCEEDED = 'GHN_PACKAGE_HEIGHT_LIMIT_EXCEEDED';

    /** Adapter/data limitation — NOT a carrier rejection (fallback-eligible upstream). */
    public const REASON_HEAVY_RATE_ESTIMATION_UNAVAILABLE = 'GHN_HEAVY_RATE_ESTIMATION_UNAVAILABLE';

    private function __construct()
    {
    }
}
