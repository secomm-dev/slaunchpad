<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Api\Data;

/**
 * TASK-MD2BD3 (architecture v10 §35.4) — deterministic curated-primary selection result cho
 * một resolution key (source_scheme + source_code + target_scheme) trên candidate set AMBIGUOUS.
 *
 * Chỉ candidate có curated primary designation (mapping edge `is_primary`) mới được chọn —
 * KHÔNG bao giờ alphabetical/db-order/code-order/fuzzy. MULTIPLE_PRIMARY là DATA_INTEGRITY_DEFECT
 * (fail-closed, không tie-break).
 */
interface VnPrimaryCandidateSelectionInterface
{
    public const STATUS_SELECTED = 'SELECTED';
    public const STATUS_NO_DESIGNATED_PRIMARY = 'NO_DESIGNATED_PRIMARY';
    public const STATUS_MULTIPLE_PRIMARY = 'MULTIPLE_PRIMARY';
    public const STATUS_NOT_APPLICABLE = 'NOT_APPLICABLE';

    /** SELECTED | NO_DESIGNATED_PRIMARY | MULTIPLE_PRIMARY | NOT_APPLICABLE. */
    public function getStatus(): string;

    /** Selected canonical candidate code (target-scheme unit code); null khi không SELECTED. */
    public function getSelectedCode(): ?string;

    /** Số candidate trong tập AMBIGUOUS được xem xét. */
    public function getCandidateCount(): int;
}
