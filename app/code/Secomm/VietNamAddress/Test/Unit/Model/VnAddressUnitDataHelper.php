<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model;

use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Model\Data\VnAddressUnitData;

/**
 * Shared test factory for unit DTOs.
 */
class VnAddressUnitDataHelper
{
    public static function unit(
        string $scheme,
        string $code,
        ?string $parentCode = null,
        string $regionCode = '01',
        int $level = 2,
        string $nameVi = 'X',
        string $nameEn = 'X'
    ): VnAddressUnitInterface {
        return new VnAddressUnitData($scheme, $code, $parentCode, $regionCode, $level, $nameVi, $nameEn);
    }
}
