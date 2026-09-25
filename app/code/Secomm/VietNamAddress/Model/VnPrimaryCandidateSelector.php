<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Model;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface;
use Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface as Selection;
use Secomm\VietNamAddress\Api\VnPrimaryCandidateSelectorInterface;
use Secomm\VietNamAddress\Model\Data\VnPrimaryCandidateSelection;

/**
 * TASK-MD2BD3 (architecture v10 §35.4) — deterministic primary selector;
 * @see VnPrimaryCandidateSelectorInterface.
 *
 * TASK-KQCX3A — FROZEN selection semantics (directive 2026-09-25):
 *
 *   0 candidate  → NOT_APPLICABLE (fail closed — no-candidate failure)
 *   1 candidate  → SELECTED sole candidate, KHÔNG inspect is_primary (fast path — §15)
 *   >1, 1 primary    → SELECTED primary (CURATED_PRIMARY)
 *   >1, 0 primary    → SELECTED deterministic first (DETERMINISTIC_FIRST_NO_PRIMARY)
 *   >1, multiple primary → SELECTED deterministic first (DETERMINISTIC_FIRST_MULTIPLE_PRIMARY)
 *                          + diagnostic MULTIPLE_PRIMARY_CANDIDATES (KHÔNG fail rate flow)
 *
 * Deterministic first = candidate code sort ASC ("source_code ASC") — NEVER raw DB /
 * insertion / mapping_id order. Curated primary vẫn thắng auto-first khi tồn tại đúng 1.
 *
 * Query is_primary CHỈ theo hướng resolution (source_scheme + source_code → target_scheme)
 * — primary trên edge ngược không bao giờ được kế thừa (directive §1.1). Candidate order
 * trong $candidateCodes không bao giờ ảnh hưởng kết quả (§14).
 */
final class VnPrimaryCandidateSelector implements VnPrimaryCandidateSelectorInterface
{
    private const TABLE = 'secomm_vietnam_address_mapping';

    /** TASK-KQCX3A §16 — diagnostic cho curation defect (logging only, không fail flow). */
    private const DIAGNOSTIC_MULTIPLE_PRIMARY = 'MULTIPLE_PRIMARY_CANDIDATES';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function selectPrimary(
        string $sourceScheme,
        string $sourceCode,
        string $targetScheme,
        array $candidateCodes
    ): VnPrimaryCandidateSelectionInterface {
        $candidates = array_values(array_unique(array_map(
            static fn ($code): string => trim((string) $code),
            $candidateCodes
        )));
        $candidateCount = count($candidates);

        if ($candidateCount === 0) {
            // Case A (§5) — fail closed theo no-candidate contract hiện có.
            return new VnPrimaryCandidateSelection(
                Selection::STATUS_NOT_APPLICABLE,
                null,
                $candidateCount
            );
        }

        if ($candidateCount === 1) {
            // Case B (§5/§15) — sole candidate: select NGAY, không inspect is_primary
            // (fast path trước mọi DB query — không có ambiguity tồn tại).
            return new VnPrimaryCandidateSelection(
                Selection::STATUS_SELECTED,
                $candidates[0],
                $candidateCount,
                Selection::REASON_SOLE_CANDIDATE
            );
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $select = $connection->select()
            ->from(['m' => $table], ['m.target_code'])
            ->where('m.source_scheme = ?', $sourceScheme)
            ->where('m.source_code = ?', $sourceCode)
            ->where('m.target_scheme = ?', $targetScheme)
            ->where('m.target_code IN (?)', $candidates)
            ->where('m.is_primary = ?', 1);
        $curatedPrimaries = array_values(array_unique($connection->fetchCol($select)));

        if (count($curatedPrimaries) === 1) {
            // Case C (§5) — curated primary thắng mọi fallback.
            return new VnPrimaryCandidateSelection(
                Selection::STATUS_SELECTED,
                $curatedPrimaries[0],
                $candidateCount,
                Selection::REASON_CURATED_PRIMARY
            );
        }

        // Deterministic first — explicit stable order (code ASC), độc lập input order (§14).
        $sorted = $candidates;
        sort($sorted, SORT_STRING);

        if (count($curatedPrimaries) > 1) {
            // Case E (§5/§16) — multiple primary là curation defect: KHÔNG fail rate flow,
            // chỉ emit diagnostic (safe payload — codes/counts only, không PII).
            $this->logger->warning(self::DIAGNOSTIC_MULTIPLE_PRIMARY, [
                'source_scheme' => $sourceScheme,
                'source_code' => $sourceCode,
                'target_scheme' => $targetScheme,
                'candidate_count' => $candidateCount,
                'primary_count' => count($curatedPrimaries),
                'selected_source_code' => $sorted[0],
                'selection_policy' => 'deterministic_first',
            ]);

            return new VnPrimaryCandidateSelection(
                Selection::STATUS_SELECTED,
                $sorted[0],
                $candidateCount,
                Selection::REASON_DETERMINISTIC_FIRST_MULTIPLE_PRIMARY
            );
        }

        // Case D (§5) — zero primary: business approximation cho merged administrative wards.
        return new VnPrimaryCandidateSelection(
            Selection::STATUS_SELECTED,
            $sorted[0],
            $candidateCount,
            Selection::REASON_DETERMINISTIC_FIRST_NO_PRIMARY
        );
    }
}
