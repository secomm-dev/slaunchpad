<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Model\Data;

use Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface;

/**
 * TASK-MD2BD3 — immutable primary selection result VO;
 * @see VnPrimaryCandidateSelectionInterface.
 *
 * TASK-KQCX3A — provenance: status + selected code + candidate count + selection reason
 * (frozen semantics theo directive 2026-09-25).
 */
final class VnPrimaryCandidateSelection implements VnPrimaryCandidateSelectionInterface
{
    private const STATUSES = [
        self::STATUS_SELECTED,
        self::STATUS_NO_DESIGNATED_PRIMARY,
        self::STATUS_MULTIPLE_PRIMARY,
        self::STATUS_NOT_APPLICABLE,
    ];

    private const REASONS = [
        self::REASON_SOLE_CANDIDATE,
        self::REASON_CURATED_PRIMARY,
        self::REASON_DETERMINISTIC_FIRST_NO_PRIMARY,
        self::REASON_DETERMINISTIC_FIRST_MULTIPLE_PRIMARY,
    ];

    /**
     * @param string $status STATUS_*
     * @param string|null $selectedCode selected canonical candidate (target-scheme code); null trừ SELECTED
     * @param int $candidateCount số candidate trong tập được xem xét
     * @param string|null $selectionReason REASON_*; null khi không SELECTED
     * @throws \InvalidArgumentException status/reason lạ hoặc SELECTED thiếu selectedCode
     */
    public function __construct(
        private readonly string $status,
        private readonly ?string $selectedCode = null,
        private readonly int $candidateCount = 0,
        private readonly ?string $selectionReason = null
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Unknown primary candidate selection status "%s".', $status)
            );
        }
        if ($status === self::STATUS_SELECTED && ($selectedCode === null || trim($selectedCode) === '')) {
            throw new \InvalidArgumentException('SELECTED selection requires a non-empty selected code.');
        }
        if ($status !== self::STATUS_SELECTED && $selectedCode !== null) {
            throw new \InvalidArgumentException('Only a SELECTED selection carries a selected code.');
        }
        if ($selectionReason !== null && !in_array($selectionReason, self::REASONS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Unknown primary candidate selection reason "%s".', $selectionReason)
            );
        }
        if ($status === self::STATUS_SELECTED && $selectionReason === null) {
            throw new \InvalidArgumentException('SELECTED selection requires a selection reason.');
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSelectedCode(): ?string
    {
        return $this->selectedCode;
    }

    public function getCandidateCount(): int
    {
        return $this->candidateCount;
    }

    public function getSelectionReason(): ?string
    {
        return $this->selectionReason;
    }
}
