<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Api\Data;

/**
 * DEC-FEATYA2C0W-003 / TASK-J9AVGK — directional administrative mapping resolution result.
 * Statuses are contract constants; AMBIGUOUS never auto-picks a candidate.
 */
interface VnAddressResolutionInterface
{
    public const STATUS_EXACT = 'EXACT';
    public const STATUS_MAPPED = 'MAPPED';
    public const STATUS_AMBIGUOUS = 'AMBIGUOUS';
    public const STATUS_UNMAPPED = 'UNMAPPED';

    public const REASON_UNKNOWN_SOURCE_UNIT = 'unknown_source_unit';
    public const REASON_NO_MAPPING = 'no_mapping';

    public function getSourceScheme(): string;

    public function getSourceCode(): string;

    public function getTargetScheme(): string;

    /** EXACT | MAPPED | AMBIGUOUS | UNMAPPED */
    public function getStatus(): string;

    /** Deterministic result code (EXACT/MAPPED); null for AMBIGUOUS/UNMAPPED. */
    public function getResolvedCode(): ?string;

    /** Relation type of the mapped edge (MAPPED only); null otherwise. */
    public function getRelationType(): ?string;

    /**
     * All candidate target codes (AMBIGUOUS; deterministically sorted).
     * Empty for EXACT/MAPPED/UNMAPPED (use getResolvedCode there).
     *
     * @return string[]
     */
    public function getCandidateCodes(): array;

    /** UNMAPPED reason (unknown_source_unit | no_mapping); null otherwise. */
    public function getReason(): ?string;
}
