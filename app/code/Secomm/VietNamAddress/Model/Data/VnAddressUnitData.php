<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Data;

use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;

/**
 * TASK-9394A9 — DTO for a historical unit reference row.
 */
class VnAddressUnitData implements VnAddressUnitInterface
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

    public function getSchemeCode(): string
    {
        return $this->schemeCode;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getParentCode(): ?string
    {
        return $this->parentCode;
    }

    public function getRegionCode(): string
    {
        return $this->regionCode;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getNameVi(): string
    {
        return $this->nameVi;
    }

    public function getNameEn(): string
    {
        return $this->nameEn;
    }
}
