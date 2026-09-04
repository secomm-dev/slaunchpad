<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Data;

use Secomm\VietNamAddress\Api\Data\VnOperationalIdentityInterface;

/**
 * TASK-Q4B98P — immutable bridge identity (pattern: VnAddressUnitData).
 */
class VnOperationalIdentityData implements VnOperationalIdentityInterface
{
    public function __construct(
        private readonly string $schemeCode,
        private readonly string $unitCode,
        private readonly ?int $level = null,
        private readonly ?string $regionCode = null,
        private readonly ?string $parentUnitCode = null,
        private readonly ?int $regionId = null,
        private readonly ?int $cityId = null
    ) {
    }

    public function getSchemeCode(): string
    {
        return $this->schemeCode;
    }

    public function getUnitCode(): string
    {
        return $this->unitCode;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function getRegionCode(): ?string
    {
        return $this->regionCode;
    }

    public function getParentUnitCode(): ?string
    {
        return $this->parentUnitCode;
    }

    public function getRegionId(): ?int
    {
        return $this->regionId;
    }

    public function getCityId(): ?int
    {
        return $this->cityId;
    }
}
