<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRepositoryInterface;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\Address\PersistentCanonicalZoneRegistry;
use Secomm\ShippingCore\Cache\Type\Zone as ZoneCacheType;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — persisted-wins-over-DI registry precedence, lazy load,
 * cache round-trip (plain-array payload), duplicate fail-fast within a source, zero-state.
 */
class PersistentCanonicalZoneRegistryTest extends TestCase
{
    private function repositoryReturning(array $zones): CanonicalZoneRepositoryInterface
    {
        $repository = $this->createMock(CanonicalZoneRepositoryInterface::class);
        $repository->method('getAll')->willReturn($zones);

        return $repository;
    }

    private function cache(): ZoneCacheType
    {
        $cache = $this->createMock(ZoneCacheType::class);
        $cache->method('load')->willReturn(false);
        $cache->method('save')->willReturn(true);

        return $cache;
    }

    public function testZeroZonesInBothSourcesIsValid(): void
    {
        $registry = new PersistentCanonicalZoneRegistry([], $this->repositoryReturning([]), $this->cache());

        $this->assertSame([], $registry->getAll());
        $this->assertSame([], $registry->getEnabled());
        $this->assertNull($registry->getByCode('HCM_INNER'));
    }

    public function testPersistedZoneIsAuthoritative(): void
    {
        $persisted = new CanonicalZone('HCM_INNER', 'Persisted definition', true, ['VN-79'], ['VNA25-AAA'], []);
        $registry = new PersistentCanonicalZoneRegistry(
            [new CanonicalZone('HCM_INNER', 'DI definition', true, ['VN-01'], [], [])],
            $this->repositoryReturning([$persisted]),
            $this->cache()
        );

        $this->assertSame($persisted, $registry->getByCode('HCM_INNER'));
    }

    public function testDiZoneFallsBackWhenNoPersistedAuthority(): void
    {
        $diZone = new CanonicalZone('BOOTSTRAP', 'DI fallback', true, ['VN-01'], [], []);
        $persisted = new CanonicalZone('HCM_INNER', 'Persisted', true, ['VN-79'], [], []);
        $registry = new PersistentCanonicalZoneRegistry(
            [$diZone],
            $this->repositoryReturning([$persisted]),
            $this->cache()
        );

        $this->assertSame($diZone, $registry->getByCode('BOOTSTRAP'));
        $this->assertSame($persisted, $registry->getByCode('HCM_INNER'));
        $this->assertCount(2, $registry->getAll());
    }

    public function testGetEnabledFiltersDisabledFromBothSources(): void
    {
        $persistedDisabled = new CanonicalZone('P_DISABLED', 'Persisted disabled', false, [], [], []);
        $diDisabled = new CanonicalZone('D_DISABLED', 'DI disabled', false, [], [], []);
        $persistedEnabled = new CanonicalZone('P_ENABLED', 'Persisted enabled', true, [], [], []);
        $repository = $this->repositoryReturning([$persistedDisabled, $persistedEnabled]);
        $registry = new PersistentCanonicalZoneRegistry([$diDisabled], $repository, $this->cache());

        $enabled = $registry->getEnabled();
        $this->assertSame([$persistedEnabled], $enabled);
    }

    public function testDuplicateDiCodesFailFastAtConstruction(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Duplicate canonical zone code');

        new PersistentCanonicalZoneRegistry(
            [new CanonicalZone('X', 'First'), new CanonicalZone('X', 'Second')],
            $this->repositoryReturning([]),
            $this->cache()
        );
    }

    public function testDuplicatePersistedCodesFailFastAtLoad(): void
    {
        $repository = $this->repositoryReturning([
            new CanonicalZone('X', 'First'),
            new CanonicalZone('X', 'Second'),
        ]);
        $registry = new PersistentCanonicalZoneRegistry([], $repository, $this->cache());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Duplicate canonical zone code');
        $registry->getAll();
    }

    public function testCacheRoundTripPreservesZoneShape(): void
    {
        $captured = null;
        $cache = $this->createMock(ZoneCacheType::class);
        $cache->method('load')->willReturn(false);
        $cache->method('save')->willReturnCallback(
            static function (string $data, string $identifier, array $tags) use (&$captured): bool {
                $captured = ['data' => $data, 'identifier' => $identifier, 'tags' => $tags];

                return true;
            }
        );
        $zone = new CanonicalZone('HCM_INNER', 'Nội thành', true, ['VN-79'], ['VNA25-AAA'], ['VNA25-BBB']);
        $repository = $this->createMock(CanonicalZoneRepositoryInterface::class);
        $repository->method('getAll')->willReturn([$zone]);
        $secondCall = $this->once();
        $repository->expects($secondCall)->method('getAll');

        $registry = new PersistentCanonicalZoneRegistry([], $repository, $cache);
        $registry->getAll();

        $this->assertNotNull($captured);
        $this->assertSame('secomm_shippingcore_zones_all', $captured['identifier']);
        $this->assertSame(['secomm_shippingcore_zones'], $captured['tags']);

        // Round-trip: feed the serialized payload back as a cache hit — zones must rebuild.
        $hitCache = $this->createMock(ZoneCacheType::class);
        $hitCache->method('load')->willReturn($captured['data']);
        $emptyRepository = $this->createMock(CanonicalZoneRepositoryInterface::class);
        $emptyRepository->expects($this->never())->method('getAll');
        $warmRegistry = new PersistentCanonicalZoneRegistry([], $emptyRepository, $hitCache);
        $loaded = $warmRegistry->getAll();

        $this->assertCount(1, $loaded);
        $this->assertSame('HCM_INNER', $loaded[0]->getCode());
        $this->assertSame(['VN-79'], $loaded[0]->getIncludeProvinceCodes());
        $this->assertSame(['VNA25-BBB'], $loaded[0]->getExcludeWardCodes());
        $this->assertTrue($loaded[0]->isEnabled());
    }

    public function testRepositoryConsultedOnlyOncePerInstance(): void
    {
        $repository = $this->createMock(CanonicalZoneRepositoryInterface::class);
        $repository->expects($this->once())->method('getAll')->willReturn([]);
        $registry = new PersistentCanonicalZoneRegistry([], $repository, $this->cache());

        $registry->getAll();
        $registry->getEnabled();
        $registry->getByCode('ANY');
        $this->assertSame([], $registry->getAll());
    }
}
