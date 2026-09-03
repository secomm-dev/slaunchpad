<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Data;

use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;

/**
 * TASK-J9AVGK — immutable resolution result DTO.
 */
class VnAddressResolutionData implements VnAddressResolutionInterface
{
    public function __construct(
        private readonly string $sourceScheme,
        private readonly string $sourceCode,
        private readonly string $targetScheme,
        private readonly string $status,
        private readonly ?string $resolvedCode,
        private readonly ?string $relationType,
        private readonly array $candidateCodes,
        private readonly ?string $reason
    ) {
    }

    public function getSourceScheme(): string
    {
        return $this->sourceScheme;
    }

    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    public function getTargetScheme(): string
    {
        return $this->targetScheme;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getResolvedCode(): ?string
    {
        return $this->resolvedCode;
    }

    public function getRelationType(): ?string
    {
        return $this->relationType;
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
