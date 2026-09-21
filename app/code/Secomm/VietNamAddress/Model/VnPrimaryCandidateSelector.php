<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Model;

use Magento\Framework\App\ResourceConnection;
use Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface;
use Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface as Selection;
use Secomm\VietNamAddress\Api\VnPrimaryCandidateSelectorInterface;
use Secomm\VietNamAddress\Model\Data\VnPrimaryCandidateSelection;

/**
 * TASK-MD2BD3 (architecture v10 §35.4) — deterministic curated-primary selector;
 * @see VnPrimaryCandidateSelectorInterface.
 *
 * Query CHỈ theo hướng resolution (source_scheme + source_code → target_scheme) với
 * `is_primary = 1` — primary trên edge ngược không bao giờ được kế thừa (directive §1.1).
 * Candidate order trong $candidateCodes không ảnh hưởng kết quả (designation-based selection).
 */
final class VnPrimaryCandidateSelector implements VnPrimaryCandidateSelectorInterface
{
    private const TABLE = 'secomm_vietnam_address_mapping';

    public function __construct(
        private readonly ResourceConnection $resource
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

        if ($candidateCount < 2) {
            // Selector chỉ có ý nghĩa trên tập AMBIGUOUS — tập duy nhất không cần selector.
            return new VnPrimaryCandidateSelection(
                Selection::STATUS_NOT_APPLICABLE,
                null,
                $candidateCount
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

        $status = match (count($curatedPrimaries)) {
            0 => Selection::STATUS_NO_DESIGNATED_PRIMARY,
            1 => Selection::STATUS_SELECTED,
            default => Selection::STATUS_MULTIPLE_PRIMARY,
        };

        return new VnPrimaryCandidateSelection(
            $status,
            $status === Selection::STATUS_SELECTED ? $curatedPrimaries[0] : null,
            $candidateCount
        );
    }
}
