<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Model\Data;

use Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface;

/**
 * TASK-MD2BD3 — immutable curated-primary selection result VO;
 * @see VnPrimaryCandidateSelectionInterface.
 */
final class VnPrimaryCandidateSelection implements VnPrimaryCandidateSelectionInterface
{
    private const STATUSES = [
        self::STATUS_SELECTED,
        self::STATUS_NO_DESIGNATED_PRIMARY,
        self::STATUS_MULTIPLE_PRIMARY,
        self::STATUS_NOT_APPLICABLE,
    ];

    /**
     * @param string $status STATUS_*
     * @param string|null $selectedCode selected canonical candidate (target-scheme code); null trừ SELECTED
     * @param int $candidateCount số candidate trong tập AMBIGUOUS được xem xét
     * @throws \InvalidArgumentException status lạ hoặc SELECTED thiếu selectedCode
     */
    public function __construct(
        private readonly string $status,
        private readonly ?string $selectedCode = null,
        private readonly int $candidateCount = 0
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
}
