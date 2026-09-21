<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address\Mapping;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Address\Mapping\GhnLocation;
use Secomm\Ghn\Model\Address\Mapping\GhnMappingResolver;
use Secomm\Ghn\Model\Cache\MappingCache;
use Secomm\Ghn\Model\Exception\GhnMappingNotFoundException;
use Secomm\Ghn\Model\ResourceModel\AddressMapping;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;

/**
 * TASK-MZ2TCB / AC-B4 — runtime resolver: fail-closed on miss/DISABLED, PRE_2025 ward yields the
 * complete legacy triple via parent walk, 2025 ward yields verbatim names, cache only stores hits.
 */
class GhnMappingResolverTest extends TestCase
{
    private AddressMapping&MockObject $mappingResource;

    private AddressUnit&MockObject $unitResource;

    private MappingCache&MockObject $cache;

    /** Payload served by the cache mock ('' = miss). */
    private string $cachePayload = '';

    private GhnMappingResolver $resolver;

    protected function setUp(): void
    {
        $this->mappingResource = $this->createMock(AddressMapping::class);
        $this->unitResource = $this->createMock(AddressUnit::class);
        $this->cache = $this->createMock(MappingCache::class);
        $this->cache->method('load')->willReturnCallback(fn (): string|false => $this->cachePayload === '' ? false : $this->cachePayload);
        $this->resolver = new GhnMappingResolver($this->mappingResource, $this->unitResource, $this->cache);
    }

    public function testMissingMappingThrows(): void
    {
        $this->mappingResource->method('findApproved')->willReturn(null);
        $this->cache->expects($this->never())->method('save');

        $this->expectException(GhnMappingNotFoundException::class);
        $this->resolver->resolve('VN_ADMIN_PRE_2025', 'VNAP25-0000000001');
    }

    public function testDisabledUnitThrowsAndIsNotCached(): void
    {
        $this->mappingResource->method('findApproved')->willReturn($this->mappingRow(7));
        $this->unitResource->method('fetchUnit')->willReturn($this->wardRow(status: 'DISABLED'));
        $this->cache->expects($this->never())->method('save');

        $this->expectException(GhnMappingNotFoundException::class);
        $this->resolver->resolve('VN_ADMIN_PRE_2025', 'VNAP25-0000000001');
    }

    public function testPre2025WardResolvesCompleteLegacyTriple(): void
    {
        $this->mappingResource->method('findApproved')->willReturn($this->mappingRow(7));
        $this->unitResource->method('fetchUnit')->willReturnCallback(
            fn (int $id): ?array => match ($id) {
                7 => $this->wardRow(),
                5 => $this->districtRow(),
                2 => $this->provinceRow(),
                default => null,
            }
        );

        $location = $this->resolver->resolve('VN_ADMIN_PRE_2025', 'VNAP25-0000000001');

        $this->assertTrue($location->hasCompleteLegacyTriple());
        $this->assertSame('201', $location->getProvinceId());
        $this->assertSame('1442', $location->getDistrictId());
        $this->assertSame('90733', $location->getWardCode());
        $this->assertSame('TP. Hồ Chí Minh', $location->getProvinceName());
        $this->assertSame('Phường Bến Nghé', $location->getWardName());
    }

    public function testNewModelWardResolvesVerbatimNames(): void
    {
        $this->mappingResource->method('findApproved')->willReturn($this->mappingRow(21));
        $this->unitResource->method('fetchUnit')->willReturnCallback(
            fn (int $id): ?array => match ($id) {
                21 => [
                    'entity_id' => 21, 'scheme_code' => 'GHN_ADMIN_2025', 'provider_key' => '33',
                    'provider_id' => '33', 'provider_code' => null, 'parent_id' => 3, 'depth' => 2,
                    'name' => 'Phường Bến Nghé', 'status' => 'ACTIVE',
                ],
                3 => [
                    'entity_id' => 3, 'scheme_code' => 'GHN_ADMIN_2025', 'provider_key' => '1',
                    'provider_id' => '1', 'provider_code' => null, 'parent_id' => null, 'depth' => 1,
                    'name' => 'Tp. Hồ Chí Minh', 'status' => 'ACTIVE',
                ],
                default => null,
            }
        );

        $location = $this->resolver->resolve('VN_ADMIN_2025', 'VNA25-A000000001');

        $this->assertTrue($location->hasNewAddressNames());
        $this->assertSame('Tp. Hồ Chí Minh', $location->getProvinceName());
        $this->assertSame('Phường Bến Nghé', $location->getWardName());
        $this->assertFalse($location->hasCompleteLegacyTriple());
    }

    public function testHitIsCachedAndCacheHitSkipsDb(): void
    {
        $location = new GhnLocation('VN_ADMIN_2025', 'VNA25-A000000001', provinceName: 'Tp. Hồ Chí Minh', wardName: 'Phường Bến Nghé');
        $this->cachePayload = (string) json_encode($location->toArray(), JSON_UNESCAPED_UNICODE);
        $this->mappingResource->expects($this->never())->method('findApproved');
        $this->unitResource->expects($this->never())->method('fetchUnit');

        $resolved = $this->resolver->resolve('VN_ADMIN_2025', 'VNA25-A000000001');

        $this->assertSame('Phường Bến Nghé', $resolved->getWardName());
    }

    public function testSuccessIsCached(): void
    {
        $this->mappingResource->method('findApproved')->willReturn($this->mappingRow(7));
        $this->unitResource->method('fetchUnit')->willReturnCallback(
            fn (int $id): ?array => match ($id) {
                7 => $this->wardRow(),
                5 => $this->districtRow(),
                2 => $this->provinceRow(),
                default => null,
            }
        );
        $this->cache->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(static fn (string $json): bool => str_contains($json, '90733')),
                $this->stringContains('VN_ADMIN_PRE_2025'),
                $this->anything()
            );

        $this->resolver->resolve('VN_ADMIN_PRE_2025', 'VNAP25-0000000001');
    }

    /**
     * @return array<string, mixed>
     */
    private function mappingRow(int $unitId): array
    {
        return [
            'entity_id' => 100,
            'secomm_scheme_code' => 'VN_ADMIN_PRE_2025',
            'secomm_unit_code' => 'VNAP25-0000000001',
            'ghn_address_unit_id' => $unitId,
            'mapping_method' => 'EXACT_NAME',
            'mapping_status' => 'APPROVED',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wardRow(string $status = 'ACTIVE'): array
    {
        return [
            'entity_id' => 7, 'scheme_code' => 'GHN_ADMIN_PRE_2025', 'provider_key' => '90733',
            'provider_id' => null, 'provider_code' => '90733', 'parent_id' => 5, 'depth' => 3,
            'name' => 'Phường Bến Nghé', 'status' => $status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function districtRow(): array
    {
        return [
            'entity_id' => 5, 'scheme_code' => 'GHN_ADMIN_PRE_2025', 'provider_key' => '1442',
            'provider_id' => '1442', 'provider_code' => null, 'parent_id' => 2, 'depth' => 2,
            'name' => 'Quận 1', 'status' => 'ACTIVE',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function provinceRow(): array
    {
        return [
            'entity_id' => 2, 'scheme_code' => 'GHN_ADMIN_PRE_2025', 'provider_key' => '201',
            'provider_id' => '201', 'provider_code' => null, 'parent_id' => null, 'depth' => 1,
            'name' => 'TP. Hồ Chí Minh', 'status' => 'ACTIVE',
        ];
    }
}
