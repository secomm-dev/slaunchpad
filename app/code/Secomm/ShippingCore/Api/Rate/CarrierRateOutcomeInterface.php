<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Rate;

/**
 * TASK-NAT3YV (Phase E-C1, SPIKE-YH439T failure model; r1 — shared reasons centralized) — the
 * common domain outcome a realtime carrier integration reports for one rate request, replacing
 * the ad-hoc false/null/throw/fake-rate/log-and-continue patterns.
 *
 * The core question ShippingCore answers from outcomes: "is there a usable realtime rate — and
 * if not, is this normal/business unavailability or a TEMPORARY technical failure?" Only
 * TECHNICAL_FAILURE may later contribute to emergency service-level fallback eligibility;
 * UNAVAILABLE never does on its own.
 *
 * Failure reasons reference the ONE shared ShippingCore owner
 * ({@see \Secomm\ShippingCore\Api\Failure\ShippingFailureReason}) — this contract declares no
 * duplicate constants: provider mapping missing → PROVIDER_MAPPING_MISSING (UNAVAILABLE),
 * service rejected/not usable → SERVICE_UNAVAILABLE (UNAVAILABLE), temporary technical issue →
 * TECHNICAL_ERROR (TECHNICAL_FAILURE). A reason is OPTIONAL on non-SUCCESS outcomes, and
 * carrier-specific detailed codes (e.g. GHN_LOCATION_NOT_FOUND, AHAMOVE_OUTSIDE_COVERAGE) are
 * provider-owned free-form strings — deliberately not centralized.
 *
 * ORCHESTRATION RULE (r1): the STATUS drives runtime behavior; the reason string is diagnostic
 * context only. Future orchestration must never infer fallback eligibility from reason strings
 * — an UNAVAILABLE outcome carrying TECHNICAL_ERROR remains UNAVAILABLE (never
 * fallback-triggering). Auth/configuration failures are UNAVAILABLE by rule, never
 * TECHNICAL_FAILURE.
 */
interface CarrierRateOutcomeInterface
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_UNAVAILABLE = 'UNAVAILABLE';
    public const STATUS_TECHNICAL_FAILURE = 'TECHNICAL_FAILURE';

    /** STATUS_* — SUCCESS | UNAVAILABLE | TECHNICAL_FAILURE (no fourth top-level status). */
    public function getStatus(): string;

    /** The realtime rate for SUCCESS; null for UNAVAILABLE/TECHNICAL_FAILURE. */
    public function getRate(): ?CarrierRateInterface;

    /**
     * A shared {@see \Secomm\ShippingCore\Api\Failure\ShippingFailureReason} or a carrier-owned
     * detailed code; null when there is nothing to report (always null for SUCCESS — an empty
     * string is normalized to null). Diagnostic only — status is the orchestration semantic.
     */
    public function getFailureReason(): ?string;

    /** Convenience hard-guard: true ⇔ SUCCESS (mirror of ResolvedShippingAddressInterface::isResolved()). */
    public function isSuccessful(): bool;
}
