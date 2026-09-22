<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Secomm\ShippingCore\Api\Address\CanonicalResolutionSnapshotInterface;

/**
 * TASK-Y3X6H5 — immutable, self-guarding canonical resolution snapshot VO;
 * @see CanonicalResolutionSnapshotInterface.
 *
 * Impossible states are unconstructible: RESOLVED always carries a canonical 2025 identity with
 * failure class NONE; UNRESOLVED always carries one of the three failure classes (never NONE);
 * an EXTERNAL_RESOLVER source always names its resolver (provenance is the point of shift-left).
 */
final class CanonicalResolutionSnapshot implements CanonicalResolutionSnapshotInterface
{
    private const FAILURE_CLASSES_UNRESOLVED = [
        self::FAILURE_CLASS_AMBIGUOUS,
        self::FAILURE_CLASS_UNMAPPED,
        self::FAILURE_CLASS_TECHNICAL,
    ];

    /**
     * @param string $canonical2025Scheme canonical 2025 scheme code (VN_ADMIN_2025)
     * @param string $canonical2025UnitCode canonical 2025 ward-level unit code
     * @param string $status STATUS_*
     * @param string $failureClass FAILURE_CLASS_*
     * @param string $source SOURCE_*
     * @param string|null $pre2025ProvinceUnitCode Secomm canonical PRE-2025 province unit code
     * @param string|null $pre2025DistrictUnitCode Secomm canonical PRE-2025 district unit code
     * @param string|null $pre2025WardUnitCode Secomm canonical PRE-2025 ward unit code
     * @param string|null $provenanceResolver external resolver identity (required for EXTERNAL_RESOLVER)
     * @param string|null $provenanceTimestamp persistence timestamp
     * @param string|null $provenanceMappingVersion mapping dataset version
     * @throws \LogicException on any impossible combination
     */
    public function __construct(
        private readonly string $canonical2025Scheme,
        private readonly string $canonical2025UnitCode,
        private readonly string $status,
        private readonly string $failureClass,
        private readonly string $source,
        private readonly ?string $pre2025ProvinceUnitCode = null,
        private readonly ?string $pre2025DistrictUnitCode = null,
        private readonly ?string $pre2025WardUnitCode = null,
        private readonly ?string $provenanceResolver = null,
        private readonly ?string $provenanceTimestamp = null,
        private readonly ?string $provenanceMappingVersion = null,
        private readonly ?string $selectionPolicy = null,
        private readonly ?string $selectionReason = null,
        private readonly ?int $candidateCount = null
    ) {
        if (!in_array($status, [self::STATUS_RESOLVED, self::STATUS_UNRESOLVED], true)) {
            throw new \LogicException(sprintf('Unknown canonical resolution snapshot status "%s".', $status));
        }
        if (!in_array($failureClass, [
            self::FAILURE_CLASS_NONE,
            self::FAILURE_CLASS_AMBIGUOUS,
            self::FAILURE_CLASS_UNMAPPED,
            self::FAILURE_CLASS_TECHNICAL,
        ], true)) {
            throw new \LogicException(sprintf('Unknown snapshot failure class "%s".', $failureClass));
        }
        if (!in_array($source, [self::SOURCE_LOCAL_MAPPING, self::SOURCE_EXTERNAL_RESOLVER], true)) {
            throw new \LogicException(sprintf('Unknown snapshot source "%s".', $source));
        }

        if ($status === self::STATUS_RESOLVED) {
            if ($failureClass !== self::FAILURE_CLASS_NONE) {
                throw new \LogicException('A RESOLVED snapshot must have failure class NONE.');
            }
            if (trim($canonical2025UnitCode) === '') {
                throw new \LogicException('A RESOLVED snapshot must carry a canonical 2025 unit code.');
            }
        } elseif ($failureClass === self::FAILURE_CLASS_NONE) {
            throw new \LogicException('An UNRESOLVED snapshot must not use failure class NONE.');
        }

        if ($source === self::SOURCE_EXTERNAL_RESOLVER && ($provenanceResolver === null || trim($provenanceResolver) === '')) {
            throw new \LogicException('An externally resolved snapshot must name its resolver (provenance).');
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getFailureClass(): string
    {
        return $this->failureClass;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getCanonical2025Scheme(): string
    {
        return $this->canonical2025Scheme;
    }

    public function getCanonical2025UnitCode(): string
    {
        return $this->canonical2025UnitCode;
    }

    public function getPre2025ProvinceUnitCode(): ?string
    {
        return $this->pre2025ProvinceUnitCode;
    }

    public function getPre2025DistrictUnitCode(): ?string
    {
        return $this->pre2025DistrictUnitCode;
    }

    public function getPre2025WardUnitCode(): ?string
    {
        return $this->pre2025WardUnitCode;
    }

    public function getProvenanceResolver(): ?string
    {
        return $this->provenanceResolver;
    }

    public function getProvenanceTimestamp(): ?string
    {
        return $this->provenanceTimestamp;
    }

    public function getProvenanceMappingVersion(): ?string
    {
        return $this->provenanceMappingVersion;
    }
    public function getSelectionPolicy(): ?string
    {
        return $this->selectionPolicy;
    }

    public function getSelectionReason(): ?string
    {
        return $this->selectionReason;
    }

    public function getCandidateCount(): ?int
    {
        return $this->candidateCount;
    }
}
