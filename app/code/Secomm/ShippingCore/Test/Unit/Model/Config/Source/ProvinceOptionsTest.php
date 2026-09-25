<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Config\Source;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Config\Source\ProvinceOptions;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 — province options come straight from the VietNamAddress
 * canonical reference layer (VN_ADMIN_2025 level 1): identity = code, label "name (VN-XX)",
 * no hardcoded geography.
 */
class ProvinceOptionsTest extends TestCase
{
    public function testOptionsFromCanonicalLevel1WithSortedNameCodeLabels(): void
    {
        $province = function (string $code, string $nameVi) {
            $unit = $this->createMock(VnAddressUnitInterface::class);
            $unit->method('getCode')->willReturn($code);
            $unit->method('getNameVi')->willReturn($nameVi);

            return $unit;
        };
        $unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $unitProvider->expects($this->once())->method('getByLevel')->with('VN_ADMIN_2025', 1)->willReturn([
            $province('VN-15', 'Hồ Chí Minh'),
            $province('VN-01', 'An Giang'),
        ]);

        $options = (new ProvinceOptions($unitProvider))->toOptionArray();

        $this->assertSame([
            ['value' => 'VN-01', 'label' => 'An Giang (VN-01)'],
            ['value' => 'VN-15', 'label' => 'Hồ Chí Minh (VN-15)'],
        ], $options);
    }

    public function testEmptyReferenceLayerYieldsNoOptions(): void
    {
        $unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $unitProvider->method('getByLevel')->willReturn([]);

        $this->assertSame([], (new ProvinceOptions($unitProvider))->toOptionArray());
    }
}
