<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Rate;

use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;

/**
 * TASK-8MQHJX (Phase C, architecture v10 §35) — the smallest provider-neutral seam for the
 * execution service to invoke ONE carrier's realtime RATE path and get the shared
 * {@see CarrierRateOutcomeInterface} semantics back.
 *
 * Carrier modules implement this (there is deliberately NO default implementation — a default
 * here would be a reverse ShippingCore → carrier dependency) and close over their own runtime
 * context: quote/rate request data (weight, subtotal, store), provider clients, mapping and
 * configuration. ShippingCore never sees provider data — §6 of the task: no GHN district_id,
 * no GHTK payloads, no candidate arrays, no is_primary metadata crosses this boundary.
 *
 * Input is the FINAL carrier-facing handoff (already gated by AddressResolutionPolicy): for
 * PICK_PRIMARY the carrier receives ONLY the selected canonical destination — never the
 * candidate list, its order, or any ranking. The handoff's textual-fallback eligibility and
 * AMBIGUOUS/UNMAPPED distinction remain readable so carrier-side textual strategies keep
 * working exactly as in the pre-execution-service flow.
 *
 * The return contract is the shared outcome domain (SUCCESS / UNAVAILABLE / TECHNICAL_FAILURE
 * + optional failure reason) — no exceptions, no Magento rate objects, no raw provider shapes.
 */
interface RealtimeCarrierRateContributorInterface
{
    public function contribute(
        string $carrierCode,
        CarrierAddressHandoffInterface $handoff
    ): CarrierRateOutcomeInterface;
}
