<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;

/**
 * TASK-T78YH6 (Phase E-C0, SPIKE-YH439T §4 Option B-minimal) — the clean, carrier-facing
 * outcome of Stage-1 canonical address resolution. Carriers read this instead of constructing
 * resolution contexts, catching ShippingCore exceptions, or interpreting EXACT/MAPPED/
 * AMBIGUOUS/UNMAPPED themselves.
 *
 * Minimal by design: "resolved" is derived (`getResolvedAddress() !== null`); no unresolved
 * result accessor (no carrier behavior needs it yet); no recipient PII; no provider-stage
 * reasons — provider mapping/API failures happen in Stage 2 inside the carrier and are NEVER
 * translated back into the canonical failure reason (Stage boundary, SPIKE-YH439T §9).
 *
 * Failure reasons reference the ONE shared ShippingCore owner
 * ({@see ShippingFailureReason}) — this contract declares no duplicate constants:
 * non-VN → ShippingFailureReason::UNSUPPORTED_DESTINATION; AMBIGUOUS/UNMAPPED without a usable
 * resolution → ShippingFailureReason::CANONICAL_UNRESOLVED.
 *
 * Textual fallback: this contract only communicates ELIGIBILITY (the capability was read by
 * ShippingCore) — the carrier builds and executes its own provider-specific textual payload.
 */
interface CarrierAddressHandoffInterface
{
    /** False only for non-VN destinations (ShippingCore translated the internal exception). */
    public function isApplicable(): bool;

    /** Canonical result for EXACT/MAPPED; null for unresolved states (never a picked candidate). */
    public function getResolvedAddress(): ?ResolvedShippingAddressInterface;

    /**
     * ALLOWED to attempt carrier-side textual fallback (canonical unresolved + the carrier's
     * capability declares textual fallback support). Executing the fallback is carrier-owned.
     */
    public function isTextualFallbackEligible(): bool;

    /**
     * A shared {@see ShippingFailureReason} when the handoff carries no resolved address or is
     * not applicable; null when resolved. Diagnostic — the applicability/resolution state above
     * is the orchestration semantic.
     */
    public function getFailureReason(): ?string;

    /**
     * TASK-7AJ3K8 — AMBIGUOUS candidate target codes (empty for every other state): the
     * carrier-side disambiguation/fallback POLICY needs to distinguish AMBIGUOUS (never send
     * a request) from UNMAPPED (textual fallback allowed). Diagnostic input only — candidates
     * are NEVER a pick list (DEC-FEATYA2C0W-004 D9).
     *
     * @return string[]
     */
    public function getCandidateCodes(): array;

    /**
     * TASK-Y3X6H5 (architecture v4 §6) — representation CATEGORIES the operation accepts
     * (AddressRepresentation::* — UNIT_ID | TEXT_NAME), read from the carrier's
     * per-operation capability. The concrete provider values are rendered carrier-side
     * (Stage 2). Empty for legacy per-carrier handoff paths.
     *
     * @return string[]
     */
    public function getSupportedRepresentations(): array;
}
