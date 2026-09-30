<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Zone;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\ShippingCore\Model\Zone\Validator;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — canonical validation rejects invalid data before
 * persistence and returns the NORMALIZED zone on success.
 */
class ValidatorTest extends TestCase
{
    private ZoneResource $zoneResource;

    private AdapterInterface $connection;

    private VnAddressUnitProviderInterface $unitProvider;

    private Validator $validator;

    private int $existingCodeCount = 0;

    /** @var array<int, array{0: string, 1: mixed}> */
    private array $whereCaptures = [];

    protected function setUp(): void
    {
        $this->zoneResource = $this->createMock(ZoneResource::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->whereCaptures = [];
        $whereCaptures = &$this->whereCaptures;
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(
            static function (string $predicate, $value) use (&$whereCaptures, $select) {
                $whereCaptures[] = [$predicate, $value];

                return $select;
            }
        );
        $this->connection->method('select')->willReturn($select);
        $this->zoneResource->method('getConnection')->willReturn($this->connection);
        $this->zoneResource->method('getMainTable')->willReturn('secomm_shipping_zone');
        $this->unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);

        $knownUnits = [
            'VN-79' => $this->unit('VN-79', 1, 'VN-79'),
            'VN-01' => $this->unit('VN-01', 1, 'VN-01'),
            'VNA25-AAA' => $this->unit('VNA25-AAA', 2, 'VN-79'),
            'VNA25-BBB' => $this->unit('VNA25-BBB', 2, 'VN-01'),
        ];
        $this->unitProvider->method('getUnit')->willReturnCallback(
            static function (string $scheme, string $code) use ($knownUnits): ?VnAddressUnitInterface {
                return $knownUnits[$code] ?? null;
            }
        );
        $this->connection->method('fetchOne')->willReturnCallback(
            function (): int {
                return $this->existingCodeCount;
            }
        );
        $this->validator = new Validator($this->zoneResource, $this->unitProvider);
    }

    private function zoneStub(string $code, string $label = 'Label'): CanonicalZoneInterface
    {
        $stub = $this->createMock(CanonicalZoneInterface::class);
        $stub->method('getCode')->willReturn($code);
        $stub->method('getLabel')->willReturn($label);
        $stub->method('isEnabled')->willReturn(true);
        $stub->method('getIncludeProvinceCodes')->willReturn([]);
        $stub->method('getIncludeWardCodes')->willReturn([]);
        $stub->method('getExcludeWardCodes')->willReturn([]);

        return $stub;
    }

    private function unit(string $code, int $level, string $regionCode): VnAddressUnitInterface
    {
        $unit = $this->createMock(VnAddressUnitInterface::class);
        $unit->method('getCode')->willReturn($code);
        $unit->method('getLevel')->willReturn($level);
        $unit->method('getRegionCode')->willReturn($regionCode);

        return $unit;
    }

    private function zone(array $overrides = []): CanonicalZone
    {
        return new CanonicalZone(
            $overrides['code'] ?? 'HCM_INNER',
            $overrides['label'] ?? 'Nội thành TP.HCM',
            $overrides['enabled'] ?? true,
            $overrides['include_province_codes'] ?? ['VN-79'],
            $overrides['include_ward_codes'] ?? ['VNA25-AAA'],
            $overrides['exclude_ward_codes'] ?? []
        );
    }

    public function testValidZoneReturnsNormalizedZone(): void
    {
        $zone = $this->zone([
            'code' => ' hcm_inner ',
            'include_province_codes' => [' VN-79 ', ' VN-01 '],
            'include_ward_codes' => ['VNA25-AAA', 'VNA25-AAA', ''],
        ]);

        $normalized = $this->validator->validate($zone);

        $this->assertSame('HCM_INNER', $normalized->getCode());
        $this->assertSame(['VN-79', 'VN-01'], $normalized->getIncludeProvinceCodes());
        $this->assertSame(['VNA25-AAA'], $normalized->getIncludeWardCodes());
    }

    public function testEmptyCodeRejected(): void
    {
        // Interface mock bypasses the VO constructor guard so the validator's own defense
        // (whitespace-only code reaching validation) is exercised directly.
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Zone code is required');
        $this->validator->validate($this->zoneStub('   '));
    }

    public function testInvalidCodeCharactersRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('may only contain A-Z, 0-9');
        $this->validator->validate($this->zone(['code' => 'HCM INNER']));
    }

    public function testEmptyLabelRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Zone label is required');
        $this->validator->validate($this->zoneStub('HCM_INNER', ''));
    }

    public function testUnknownProvinceRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not a canonical VN_ADMIN_2025 province code');
        $this->validator->validate($this->zone(['include_province_codes' => ['VN-99']]));
    }

    public function testWardCodeUsedAsProvinceRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not a canonical VN_ADMIN_2025 province code');
        $this->validator->validate($this->zone(['include_province_codes' => ['VNA25-AAA']]));
    }

    public function testUnknownWardRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not a canonical VN_ADMIN_2025 ward code');
        $this->validator->validate($this->zone(['include_ward_codes' => ['VNA25-XYZ']]));
    }

    public function testIncludeWardOutsideIncludedProvinceRejected(): void
    {
        // VNA25-BBB belongs to VN-01; only VN-79 is included — an unreachable include ward.
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('does not belong to any included province');
        $this->validator->validate($this->zone(['include_ward_codes' => ['VNA25-BBB']]));
    }

    public function testExcludeWardFromOtherProvinceAllowed(): void
    {
        $normalized = $this->validator->validate(
            $this->zone(['exclude_ward_codes' => ['VNA25-BBB']])
        );

        $this->assertSame(['VNA25-BBB'], $normalized->getExcludeWardCodes());
    }

    public function testWardsAllowedWithoutProvinceConstraint(): void
    {
        $normalized = $this->validator->validate(
            $this->zone(['include_province_codes' => [], 'include_ward_codes' => ['VNA25-BBB']])
        );

        $this->assertSame(['VNA25-BBB'], $normalized->getIncludeWardCodes());
    }

    public function testDuplicateCodeRejected(): void
    {
        $this->existingCodeCount = 1;
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('already in DB');
        $this->validator->validate($this->zone());
    }

    public function testUpdateExcludesSelfFromUniquenessCheck(): void
    {
        // existingCodeCount simulates the count AFTER the self-exclusion predicate (below)
        // is applied: another row keeps the code, but the zone's own row does not count.
        $this->existingCodeCount = 0;
        $normalized = $this->validator->validate($this->zone(), 7);

        $this->assertSame('HCM_INNER', $normalized->getCode());
        $selfExclusion = array_filter(
            $this->whereCaptures,
            static fn (array $capture): bool => $capture[0] === 'zone_id <> ?' && $capture[1] === 7
        );
        $this->assertNotEmpty($selfExclusion, 'Uniqueness check must exclude the updated zone id.');
    }

    public function testDisabledZoneIsValidData(): void
    {
        $normalized = $this->validator->validate($this->zone(['enabled' => false]));

        $this->assertFalse($normalized->isEnabled());
    }
}
