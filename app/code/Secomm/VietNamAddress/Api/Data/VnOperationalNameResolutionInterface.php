<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

namespace Secomm\VietNamAddress\Api\Data;

use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface as Statuses;

/**
 * DEC-FEATYA2C0W-004 (D5 name-entry) / TASK-7AJ3K8 — outcome of one NAME-based bridge call
 * (region-scoped ward name → canonical identity). Statuses are REUSED from the canonical
 * vocabulary ({@see Statuses}) — no parallel constant set:
 *
 * - EXACT     exactly one unit in the region matches the name → identity populated.
 * - AMBIGUOUS more than one unit matches → candidate unit codes, NEVER auto-picked (D9).
 * - UNMAPPED  no unit matches (or the region/active scheme preconditions fail) → reason.
 */
interface VnOperationalNameResolutionInterface
{
    /** Canonical vocabulary, aliased — no parallel status set exists. */
    public const STATUS_EXACT = VnAddressResolutionInterface::STATUS_EXACT;
    public const STATUS_AMBIGUOUS = VnAddressResolutionInterface::STATUS_AMBIGUOUS;
    public const STATUS_UNMAPPED = VnAddressResolutionInterface::STATUS_UNMAPPED;

    /** Region not found / not a Vietnam region. */
    public const REASON_NOT_VN_REGION = 'not_vn_region';

    /** Active scheme unavailable (config/registry drift or nothing installed). */
    public const REASON_SCHEME_NOT_ACTIVE = 'scheme_not_active';

    /** No unit of the active scheme in the region matches the given name. */
    public const REASON_NAME_NOT_MATCHED = 'name_not_matched';

    /** Canonical unit known but has no runtime row (post-swap drift). */
    public const REASON_RUNTIME_ROW_MISSING = VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING;

    public function isResolved(): bool;

    /** STATUS_EXACT | STATUS_AMBIGUOUS | STATUS_UNMAPPED (canonical vocabulary). */
    public function getStatus(): string;

    /** Canonical identity of the single match (EXACT only); null otherwise. */
    public function getIdentity(): ?VnOperationalIdentityInterface;

    /**
     * Candidate canonical unit codes (AMBIGUOUS; deterministically sorted); empty otherwise.
     *
     * @return string[]
     */
    public function getCandidateCodes(): array;

    /** One of the REASON_* constants when UNMAPPED, null otherwise. */
    public function getReason(): ?string;
}
