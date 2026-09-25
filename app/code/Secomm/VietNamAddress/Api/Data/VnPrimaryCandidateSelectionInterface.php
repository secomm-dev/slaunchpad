<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Api\Data;

/**
 * TASK-MD2BD3 (architecture v10 §35.4) — deterministic primary selection result cho
 * một resolution key (source_scheme + source_code + target_scheme) trên candidate set.
 *
 * TASK-KQCX3A — FROZEN selection semantics (directive 2026-09-25):
 * - 0 candidate  → NOT_APPLICABLE (fail closed — no-candidate failure theo contract hiện có)
 * - 1 candidate  → SELECTED sole candidate, KHÔNG inspect is_primary (SOLE_CANDIDATE)
 * - >1, đúng 1 primary → SELECTED primary (CURATED_PRIMARY)
 * - >1, 0 primary → SELECTED deterministic first (DETERMINISTIC_FIRST_NO_PRIMARY)
 * - >1, multiple primary → SELECTED deterministic first (DETERMINISTIC_FIRST_MULTIPLE_PRIMARY)
 *   + operational diagnostic — KHÔNG fail rate flow.
 *
 * Deterministic first = candidate code sort ASC (source_code ASC) — KHÔNG BAO GIỜ raw
 * DB/insertion order. STATUS_NO_DESIGNATED_PRIMARY / STATUS_MULTIPLE_PRIMARY giữ cho BC —
 * default implementation không còn trả về chúng (chỉ implementation khác có thể).
 */
interface VnPrimaryCandidateSelectionInterface
{
    public const STATUS_SELECTED = 'SELECTED';
    public const STATUS_NO_DESIGNATED_PRIMARY = 'NO_DESIGNATED_PRIMARY';
    public const STATUS_MULTIPLE_PRIMARY = 'MULTIPLE_PRIMARY';
    public const STATUS_NOT_APPLICABLE = 'NOT_APPLICABLE';

    public const REASON_SOLE_CANDIDATE = 'SOLE_CANDIDATE';
    public const REASON_CURATED_PRIMARY = 'CURATED_PRIMARY';
    public const REASON_DETERMINISTIC_FIRST_NO_PRIMARY = 'DETERMINISTIC_FIRST_NO_PRIMARY';
    public const REASON_DETERMINISTIC_FIRST_MULTIPLE_PRIMARY = 'DETERMINISTIC_FIRST_MULTIPLE_PRIMARY';

    /** SELECTED | NO_DESIGNATED_PRIMARY | MULTIPLE_PRIMARY | NOT_APPLICABLE. */
    public function getStatus(): string;

    /** Selected canonical candidate code (target-scheme unit code); null khi không SELECTED. */
    public function getSelectedCode(): ?string;

    /** Số candidate trong tập được xem xét. */
    public function getCandidateCount(): int;

    /**
     * TASK-KQCX3A — selection provenance: SOLE_CANDIDATE | CURATED_PRIMARY |
     * DETERMINISTIC_FIRST_NO_PRIMARY | DETERMINISTIC_FIRST_MULTIPLE_PRIMARY; null khi
     * không có selection (NOT_APPLICABLE) hoặc implementation chưa cung cấp.
     */
    public function getSelectionReason(): ?string;
}
