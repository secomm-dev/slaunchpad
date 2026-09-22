<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;

/**
 * TASK-AQT7V3 — immutable, self-guarding result VO.
 *
 * All ResolvedShippingAddressInterface invariants are enforced HERE, in the constructor, so no
 * orchestration code can ever hand a carrier a malformed outcome: an unresolved status can never
 * carry a unit code, AMBIGUOUS can never lose candidates, and a candidate is never silently
 * exposed as the resolved code.
 */
final class ResolvedShippingAddress implements ResolvedShippingAddressInterface
{
    private const RESOLVED_STATUSES = [
        VnAddressResolutionInterface::STATUS_EXACT,
        VnAddressResolutionInterface::STATUS_MAPPED,
    ];

    private array $candidateCodes = [];

    /**
     * @param string $status one of VnAddressResolutionInterface::STATUS_*
     * @param string $schemeCode scheme the resolution is expressed in (non-empty)
     * @param string|null $unitCode required non-empty for EXACT/MAPPED; must be null for AMBIGUOUS/UNMAPPED
     * @param string[] $candidateCodes required non-empty for AMBIGUOUS (order preserved); ignored for other statuses
     * @throws \LogicException on any contract violation
     */
    public function __construct(
        private readonly string $status,
        private readonly string $schemeCode,
        private readonly ?string $unitCode,
        array $candidateCodes = []
    ) {
        if (!in_array($status, $this->knownStatuses(), true)) {
            throw new \LogicException(
                sprintf('Unknown shipping address resolution status "%s".', $status)
            );
        }
        if (trim($schemeCode) === '') {
            throw new \LogicException('Shipping address resolution requires a non-empty scheme code.');
        }

        if (in_array($status, self::RESOLVED_STATUSES, true)) {
            if ($unitCode === null || trim($unitCode) === '') {
                throw new \LogicException(
                    sprintf('Status %s requires a resolved unit code.', $status)
                );
            }
            $candidateCodes = [];
        } else {
            if ($unitCode !== null) {
                throw new \LogicException(
                    sprintf('Status %s must not carry a unit code — unresolved candidates are never exposed as resolved.', $status)
                );
            }
            if ($status === VnAddressResolutionInterface::STATUS_AMBIGUOUS) {
                $candidateCodes = $this->assertCandidateList($candidateCodes);
            } else {
                $candidateCodes = [];
            }
        }

        $this->candidateCodes = $candidateCodes;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSchemeCode(): string
    {
        return $this->schemeCode;
    }

    public function getUnitCode(): ?string
    {
        return $this->unitCode;
    }

    public function getCandidateCodes(): array
    {
        return $this->candidateCodes;
    }

    public function isResolved(): bool
    {
        return in_array($this->status, self::RESOLVED_STATUSES, true);
    }

    private function knownStatuses(): array
    {
        return [
            VnAddressResolutionInterface::STATUS_EXACT,
            VnAddressResolutionInterface::STATUS_MAPPED,
            VnAddressResolutionInterface::STATUS_AMBIGUOUS,
            VnAddressResolutionInterface::STATUS_UNMAPPED,
        ];
    }

    /**
     * @param array $candidateCodes
     * @return string[] list-normalized (integer keys, order + values preserved)
     */
    private function assertCandidateList(array $candidateCodes): array
    {
        if ($candidateCodes === []) {
            throw new \LogicException('Status AMBIGUOUS requires at least one candidate code.');
        }
        $candidates = [];
        foreach ($candidateCodes as $candidateCode) {
            if (!is_string($candidateCode) || trim($candidateCode) === '') {
                throw new \LogicException('Candidate codes must be non-empty strings.');
            }
            $candidates[] = $candidateCode;
        }

        return $candidates;
    }
}
