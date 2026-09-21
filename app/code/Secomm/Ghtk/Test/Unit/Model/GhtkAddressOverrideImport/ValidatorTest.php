<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\GhtkAddressOverrideImport;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Secomm\Ghtk\Model\GhtkAddressOverrideImport\Validator;

/**
 * TASK-7AJ3K8 r1 (DEC-TASK7AJ3K8-002) — override CSV validation: canonical identity checks
 * run through the VietNamAddress unit provider contract (never raw SQL), and the table only
 * accepts exception rows (≥1 override value present).
 */
class ValidatorTest extends TestCase
{
    private const SCHEME = VnSchemes::VN_ADMIN_2025;

    private VnAddressUnitProviderInterface&MockObject $unitProvider;
    private Validator $validator;

    protected function setUp(): void
    {
        $this->unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ([$scheme, $code]) {
                [self::SCHEME, 'VN-01'] => $this->unit('VN-01', 1, 'VN-01'),
                [self::SCHEME, 'VNA25-AAAA'] => $this->unit('VNA25-AAAA', 2, 'VN-01'),
                default => null,
            }
        );
        $this->validator = new Validator($this->unitProvider);
    }

    private function unit(string $code, int $level, string $regionCode): VnAddressUnitInterface&MockObject
    {
        $unit = $this->createMock(VnAddressUnitInterface::class);
        $unit->method('getCode')->willReturn($code);
        $unit->method('getLevel')->willReturn($level);
        $unit->method('getRegionCode')->willReturn($regionCode);

        return $unit;
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function row(array $overrides = ['ghtk_ward' => 'GHTK Text']): array
    {
        return array_merge([
            'scheme_code' => self::SCHEME,
            'province_code' => 'VN-01',
            'ward_code' => 'VNA25-AAAA',
            'ghtk_province' => '',
            'ghtk_district' => '',
            'ghtk_ward' => '',
            'is_active' => '1',
            'note' => '',
        ], $overrides);
    }

    public function testAcceptsValidExceptionRow(): void
    {
        $result = $this->validator->validate([$this->row(['ghtk_province' => 'TP HCM kiểu GHTK', 'note' => 'evidence #1'])]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(0, $result['skipped']);
        $this->assertCount(1, $result['accepted']);
        $this->assertSame(self::SCHEME, $result['accepted'][0]['scheme_code']);
        $this->assertSame('TP HCM kiểu GHTK', $result['accepted'][0]['ghtk_province']);
        $this->assertNull($result['accepted'][0]['ghtk_district']);
        $this->assertNull($result['accepted'][0]['ghtk_ward']);
        $this->assertSame('evidence #1', $result['accepted'][0]['note']);
    }

    public function testRejectsUnknownScheme(): void
    {
        $result = $this->validator->validate([$this->row(['scheme_code' => 'VN_ADMIN_1998'])]);

        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('not a known VN administrative scheme', $result['errors'][0]);
    }

    public function testRejectsProvinceCodeThatIsNotARegionUnit(): void
    {
        // ward code used as province → exists but level 2 → rejected.
        $result = $this->validator->validate([$this->row(['province_code' => 'VNA25-AAAA'])]);

        $this->assertCount(1, $result['errors']);
        $this->assertSame(0, count($result['accepted']));
    }

    public function testRejectsUnknownWardCode(): void
    {
        $result = $this->validator->validate([$this->row(['ward_code' => 'VNA25-GONE'])]);

        $this->assertCount(1, $result['errors']);
        $this->assertSame(0, count($result['accepted']));
    }

    public function testRejectsWardFromAnotherProvince(): void
    {
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ($code) {
                'VN-01' => $this->unit('VN-01', 1, 'VN-01'),
                'VN-79' => $this->unit('VN-79', 1, 'VN-79'),
                'VNA25-BBBB' => $this->unit('VNA25-BBBB', 2, 'VN-79'),
                default => null,
            }
        );

        $result = $this->validator->validate([$this->row(['ward_code' => 'VNA25-BBBB'])]);

        $this->assertCount(1, $result['errors']);
        $this->assertSame(0, count($result['accepted']));
    }

    public function testRejectsRowWithoutAnyOverrideValue(): void
    {
        // The r0 full-dataset semantics are GONE: a row duplicating canonical names is rejected.
        $result = $this->validator->validate([$this->row(['ghtk_ward' => '', 'ghtk_province' => '', 'ghtk_district' => ''])]);

        $this->assertCount(1, $result['errors']);
        $this->assertSame(0, count($result['accepted']));
    }

    public function testRejectsBadIsActive(): void
    {
        $result = $this->validator->validate([$this->row(['is_active' => 'true'])]);

        $this->assertCount(1, $result['errors']);
    }

    public function testDuplicateCanonicalKeySkipsSecondOccurrence(): void
    {
        $result = $this->validator->validate([
            $this->row(['ghtk_ward' => 'First']),
            $this->row(['ghtk_ward' => 'Second']),
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['skipped']);
        $this->assertCount(1, $result['accepted']);
        $this->assertSame('First', $result['accepted'][0]['ghtk_ward']);
    }

    public function testDistrictOnlyOverrideIsAccepted(): void
    {
        $result = $this->validator->validate([$this->row(['ghtk_district' => 'Quận 1'])]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('Quận 1', $result['accepted'][0]['ghtk_district']);
    }
}
