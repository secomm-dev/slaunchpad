<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * TASK-Y3X6H5 (address-shipping architecture Revision v4 §5.1 — "Contract P1 đã chốt") — the
 * canonical resolution snapshot persisted at the address entry (shift-left) and read by the
 * RATE path. This is the CONTRACT only; the storage/persistence decision is implementation-owned.
 *
 * CANONICAL BOUNDARY (hard rule): every unit code on this snapshot is a SECOMM canonical code
 * (VN_ADMIN_2025 `VNA25-*` / VN_ADMIN_PRE_2025 `VNAP25-*`). Provider-specific IDs — GHN
 * district_id/ward_code, GHTK values, … — are Stage-2, carrier-owned, and MUST NOT be persisted
 * here or added to this contract.
 *
 * FAILURE_CLASS drives money: AMBIGUOUS/UNMAPPED → CarrierRateOutcome::UNAVAILABLE (business,
 * no fallback); TECHNICAL → CarrierRateOutcome::TECHNICAL_FAILURE (fallback eligible). Never
 * collapse the three into a generic UNRESOLVED at consumption time.
 */
interface CanonicalResolutionSnapshotInterface
{
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_UNRESOLVED = 'UNRESOLVED';

    public const FAILURE_CLASS_NONE = 'NONE';
    public const FAILURE_CLASS_AMBIGUOUS = 'AMBIGUOUS';
    public const FAILURE_CLASS_UNMAPPED = 'UNMAPPED';
    public const FAILURE_CLASS_TECHNICAL = 'TECHNICAL';

    public const SOURCE_LOCAL_MAPPING = 'LOCAL_MAPPING';
    public const SOURCE_EXTERNAL_RESOLVER = 'EXTERNAL_RESOLVER';

    /** STATUS_RESOLVED | STATUS_UNRESOLVED. */
    public function getStatus(): string;

    /**
     * FAILURE_CLASS_NONE only when RESOLVED; one of AMBIGUOUS|UNMAPPED|TECHNICAL when
     * UNRESOLVED. Diagnostic per class, orchestration per class (see failure-class semantics).
     */
    public function getFailureClass(): string;

    /** SOURCE_LOCAL_MAPPING | SOURCE_EXTERNAL_RESOLVER. */
    public function getSource(): string;

    /** Canonical 2025 scheme code (VN_ADMIN_2025) — for CREATE. */
    public function getCanonical2025Scheme(): string;

    /** Canonical 2025 ward-level unit code — for CREATE. Non-empty when RESOLVED. */
    public function getCanonical2025UnitCode(): string;

    /** Secomm canonical PRE-2025 province unit code (nullable — resolution may be partial). */
    public function getPre2025ProvinceUnitCode(): ?string;

    /** Secomm canonical PRE-2025 district unit code (nullable). */
    public function getPre2025DistrictUnitCode(): ?string;

    /** Secomm canonical PRE-2025 ward unit code (nullable). */
    public function getPre2025WardUnitCode(): ?string;

    /** External resolver identity when source = EXTERNAL_RESOLVER; null for LOCAL_MAPPING. */
    public function getProvenanceResolver(): ?string;

    /** Persistence timestamp (creation context); null when not recorded. */
    public function getProvenanceTimestamp(): ?string;

    /** Mapping dataset version the resolution was made against; null when not recorded. */
    public function getProvenanceMappingVersion(): ?string;

    /**
     * TASK-MD2BD3 v10 — AddressResolutionPolicy được áp dụng cho snapshot này khi picker path
     * chạy (hiện chỉ `PICK_PRIMARY`); null khi policy không/ chưa được áp dụng.
     */
    public function getSelectionPolicy(): ?string;

    /**
     * TASK-MD2BD3 v10 — lý do selection (CURATED_PRIMARY | NO_DESIGNATED_PRIMARY | …);
     * null khi selector không chạy. Audit-only: AMBIGUOUS gốc vẫn là AMBIGUOUS — selection
     * không rewrite resolution history.
     */
    public function getSelectionReason(): ?string;

    /** TASK-MD2BD3 v10 — số candidate trong tập AMBIGUOUS được xem xét; null khi không chạy selector. */
    public function getCandidateCount(): ?int;
}
