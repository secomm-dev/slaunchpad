<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Mapping;

use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;

/**
 * TASK-6TNKDH — immutable canonical unit view over the Secomm_VietNamAddress dataset CSV rows
 * (authoring-side only; no runtime/DB coupling).
 */
final class CanonicalUnit implements VnAddressUnitInterface
{
    public function __construct(
        private readonly string $schemeCode,
        private readonly string $code,
        private readonly ?string $parentCode,
        private readonly string $regionCode,
        private readonly int $level,
        private readonly string $nameVi,
        private readonly string $nameEn
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getSchemeCode(): string
    {
        return $this->schemeCode;
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getParentCode(): ?string
    {
        return $this->parentCode;
    }

    /**
     * @inheritDoc
     */
    public function getRegionCode(): string
    {
        return $this->regionCode;
    }

    /**
     * @inheritDoc
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @inheritDoc
     */
    public function getNameVi(): string
    {
        return $this->nameVi;
    }

    /**
     * @inheritDoc
     */
    public function getNameEn(): string
    {
        return $this->nameEn;
    }
}
