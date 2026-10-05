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
 *   fail-closed at CREATE by GhnPhysicalParcelInterpreter — frozen, GhnPhysicalLimitTest pins
 *   the getter). TASK-FXFMJ0 (DEC-TASKFXFMJ0-001) removed the RATE pre-gate: Calculate Fee
 *   has no 50kg bound (DOCUMENTED + SANDBOX_OBSERVED 2026-09-30). TASK-WNQCRW (2026-10-01,
 *   DEC-TASKWNQCRW-001) makes this constant ALSO the default of the merchant-tunable RATE
 *   display gate (carriers/secomm_ghn/max_package_weight_g via
 *   Config::getMaxPackageWeightG → QuoteParcelEstimate::findHardLimitViolation) so checkout
 *   display matches the CREATE cap; weight still selects service_type_id by TOTAL quote
 *   weight via the 20kg boundary (RATE type is total-weight-only since TASK-WNQCRW).
 *
 * MAX_SIDE_CM = 200 cm — DOCUMENTED (Create contract per-side cap; enforced at CREATE by
 *   GhnPhysicalParcelInterpreter). TASK-ZS2B41 rev. 2026-10-01: this is ALSO the shared RATE
 *   default — RATE and CREATE read the SAME three config paths
 *   (carriers/secomm_ghn/max_{length,width,height}_cm via Config::get{Max}Cm()); this constant
 *   is the fallback when config is empty/non-positive. The former separate RATE default 150 —
 *   SANDBOX_OBSERVED (staging create probe 2026-09-18: provider 400 "Kích thước (dài) vượt quá
 *   mức cho phép: 150") — is SUPERSEDED as the default by user decision 2026-10-01; it stays
 *   documented as merchant tuning guidance (a GHN account that enforces 150 lowers the config
 *   in admin instead).
 */
final class GhnShipmentConstraints
{
    public const TYPE_2_MAX_WEIGHT_G = 20000;

    public const TYPE_5_MAX_WEIGHT_G = 50000;

    public const MAX_SIDE_CM = 200;

    private function __construct()
    {
    }
}
