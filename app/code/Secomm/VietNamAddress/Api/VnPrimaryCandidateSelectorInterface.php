<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Api;

use Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface;

/**
 * TASK-MD2BD3 (architecture v10 §35.4, DEC-FEATYA2C0W-006 amendment) — deterministic curated
 * primary candidate selector cho một resolution key AMBIGUOUS.
 *
 * Directional: chỉ mapping edge theo ĐÚNG hướng resolution (source_scheme + source_code →
 * target_scheme) được xét primary designation. Primary trên edge ngược KHÔNG có hiệu lực.
 *
 * Deterministic: cùng canonical input + cùng mapping dataset version → cùng selected candidate.
 * KHÔNG alphabetical/db-row/code-order/fuzzy selection; >1 curated primary = DATA_INTEGRITY_DEFECT
 * (fail-closed, không tie-break).
 */
interface VnPrimaryCandidateSelectorInterface
{
    /**
     * @param string $sourceScheme canonical scheme của source identity
     * @param string $sourceCode canonical source unit code
     * @param string $targetScheme canonical scheme đích cần resolve tới
     * @param string[] $candidateCodes tập candidate unit codes trong $targetScheme (AMBIGUOUS set)
     */
    public function selectPrimary(
        string $sourceScheme,
        string $sourceCode,
        string $targetScheme,
        array $candidateCodes
    ): VnPrimaryCandidateSelectionInterface;
}
