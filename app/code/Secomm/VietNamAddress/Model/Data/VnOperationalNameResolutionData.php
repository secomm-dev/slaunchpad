<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

namespace Secomm\VietNamAddress\Model\Data;

use Secomm\VietNamAddress\Api\Data\VnOperationalIdentityInterface;
use Secomm\VietNamAddress\Api\Data\VnOperationalNameResolutionInterface;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface as Statuses;

/**
 * TASK-7AJ3K8 — immutable NAME-based bridge outcome (pattern: VnOperationalResolutionData).
 * Status vocabulary is the canonical one; AMBIGUOUS always carries its candidates.
 */
class VnOperationalNameResolutionData implements VnOperationalNameResolutionInterface
{
    /**
     * @param string[] $candidateCodes
     */
    private function __construct(
        private readonly string $status,
        private readonly ?VnOperationalIdentityInterface $identity,
        private readonly array $candidateCodes,
        private readonly ?string $reason
    ) {
    }

    public static function exact(VnOperationalIdentityInterface $identity): self
    {
        return new self(Statuses::STATUS_EXACT, $identity, [], null);
    }

    /**
     * @param string[] $candidateCodes
     */
    public static function ambiguous(array $candidateCodes): self
    {
        $candidates = array_values(array_unique($candidateCodes));
        sort($candidates);

        return new self(Statuses::STATUS_AMBIGUOUS, null, $candidates, null);
    }

    public static function unmapped(string $reason): self
    {
        return new self(Statuses::STATUS_UNMAPPED, null, [], $reason);
    }

    public function isResolved(): bool
    {
        return $this->status === Statuses::STATUS_EXACT;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getIdentity(): ?VnOperationalIdentityInterface
    {
        return $this->identity;
    }

    public function getCandidateCodes(): array
    {
        return $this->candidateCodes;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
