<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Config;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — generic per-carrier destination-scope configuration
 * reader (`carriers/<code>/destination_scope|allowed_zone_codes`). Carriers pass the values
 * into `CarrierRateExecutionRequest` — the domain evaluator NEVER reads Magento config
 * (architecture §35.2 separation). GHN is the first consumer; future carriers reuse the same
 * paths without ShippingCore knowing any carrier identity.
 */
interface CarrierDestinationScopeConfigInterface
{
    /**
     * DestinationScope::ALL (default — missing/empty config) | DestinationScope::SELECTED_ZONES |
     * DestinationScope::ALL_EXCEPT_SELECTED_ZONES (TASK-R8WR1R — serve everywhere except the
     * listed zones; empty list ≡ ALL).
     * A persisted-but-unrecognized value is returned VERBATIM (never coerced to any valid scope)
     * with a warning diagnostic — CarrierEligibilityEvaluator's unknown-scope branch then fails
     * the carrier closed (TASK-R8WR1R r2: invalid coverage config must never read as ALL).
     */
    public function getDestinationScope(string $carrierCode, ?int $storeId = null): string;

    /**
     * Configured zone codes (trim + dedupe, input order). When the resolved scope is
     * SELECTED_ZONES or ALL_EXCEPT_SELECTED_ZONES, unknown and disabled references are surfaced
     * as warning diagnostics (§16/§22) — the codes are still returned so the evaluator applies
     * its own deterministic semantics (no-match / no-exclude respectively).
     *
     * @return string[]
     */
    public function getAllowedZoneCodes(string $carrierCode, ?int $storeId = null): array;
}
