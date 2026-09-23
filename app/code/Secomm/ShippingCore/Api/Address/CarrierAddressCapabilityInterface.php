<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * DEC-FEATYA2C0W-004 (D2/D4) / TASK-AQT7V3 — what a carrier declares about its address needs.
 *
 * Implemented by carrier modules (GHN, GHTK, Ahamove, …); ShippingCore orchestration only READS
 * it. Scheme codes come from the canonical Vietnam scheme catalog (Secomm_VietNamAddress) — this
 * contract never defines its own scheme identities.
 *
 * Deliberately minimal (D10 — no capability matrix): no carrier code (the ShippingContext already
 * carries it in every flow), no provider-ID requirements (provider mapping is carrier-owned),
 * no priority/geocode/legacy flags.
 *
 * @deprecated TASK-Y3X6H5 (architecture v4 §5): address capability is PER-OPERATION now — the
 *             same carrier may need a different scheme/representation for RATE vs CREATE. Use
 *             `CarrierOperationAddressCapabilityInterface` (+ `handoffForOperation()`/
 *             `handoffContextForOperation()`) instead. This per-carrier contract is kept
 *             temporarily so existing carrier modules keep compiling; carriers migrate in the
 *             carrier-adaptation task that follows the ShippingCore freeze.
 */
interface CarrierAddressCapabilityInterface
{
    /**
     * Canonical administrative scheme the carrier expects (e.g. VN_ADMIN_PRE_2025).
     *
     * Must be a known scheme from the Secomm_VietNamAddress scheme catalog — not a
     * ShippingCore-defined constant.
     */
    public function getRequiredScheme(): string;

    /**
     * Whether the carrier can safely continue with textual/current address data when canonical
     * translation cannot be resolved (AMBIGUOUS/UNMAPPED and no external disambiguation).
     *
     * Declaration only — the fallback decision belongs to Phase E-B orchestration; nothing in
     * Phase E-A acts on this value.
     */
    public function supportsTextualFallback(): bool;
}
