<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Zone;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRepositoryInterface;
use Secomm\ShippingCore\Cache\Type\Zone as ZoneCacheType;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\ShippingCore\Model\ResourceModel\Zone\Collection;
use Secomm\ShippingCore\Model\ResourceModel\Zone\CollectionFactory;
use Secomm\ShippingCore\Model\Zone as ZoneModel;
use Secomm\ShippingCore\Model\Zone\Validator;
use Secomm\ShippingCore\Model\Zone\ZoneRepository;
use Secomm\ShippingCore\Model\ZoneFactory;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — repository: real Validator composed in (final class —
 * no mock), validation-gated save, cache-type flush on EVERY mutation, VO mapping with
 * deterministic code-ASC order, NoSuchEntity on unknown ids.
 */
class ZoneRepositoryTest extends TestCase
{
    private ZoneFactory $zoneFactory;

    private ZoneResource $zoneResource;

    private Collection $collection;

    private TypeListInterface $cacheTypeList;

    private ZoneRepository $repository;

    private int $existingCodeCount;

    protected function setUp(): void
    {
        $this->zoneFactory = $this->createMock(ZoneFactory::class);
        $this->zoneResource = $this->createMock(ZoneResource::class);
        $this->collection = $this->createMock(Collection::class);
        $this->collection->method('addFieldToFilter')->willReturnSelf();
        $this->collection->method('setPageSize')->willReturnSelf();
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($this->collection);
        $this->cacheTypeList = $this->createMock(TypeListInterface::class);

        $connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection->method('select')->willReturn($select);
        $this->existingCodeCount = 0;
        $connection->method('fetchOne')->willReturnCallback(
            function (): int {
                return $this->existingCodeCount;
            }
        );
        $this->zoneResource->method('getConnection')->willReturn($connection);
        $this->zoneResource->method('getMainTable')->willReturn('secomm_shipping_zone');

        $unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $knownUnits = [
            'VN-79' => $this->unit('VN-79', 1, 'VN-79'),
            'VNA25-AAA' => $this->unit('VNA25-AAA', 2, 'VN-79'),
        ];
        $unitProvider->method('getUnit')->willReturnCallback(
            static function (string $scheme, string $code) use ($knownUnits): ?VnAddressUnitInterface {
                return $knownUnits[$code] ?? null;
            }
        );

        $this->repository = new ZoneRepository(
            $this->zoneFactory,
            $this->zoneResource,
            $collectionFactory,
            new Validator($this->zoneResource, $unitProvider),
            $this->cacheTypeList
        );
    }

    private function unit(string $code, int $level, string $regionCode): VnAddressUnitInterface
    {
        $unit = $this->createMock(VnAddressUnitInterface::class);
        $unit->method('getCode')->willReturn($code);
        $unit->method('getLevel')->willReturn($level);
        $unit->method('getRegionCode')->willReturn($regionCode);

        return $unit;
    }

    private function validZone(string $code = 'HCM_INNER', bool $enabled = true): CanonicalZone
    {
        return new CanonicalZone(
            $code,
            'Nội thành TP.HCM',
            $enabled,
            ['VN-79'],
            ['VNA25-AAA'],
            []
        );
    }

    private function modelStub(int $id, string $code, bool $enabled = true): ZoneModel
    {
        $model = $this->createMock(ZoneModel::class);
        $model->method('getId')->willReturn($id);
        $model->method('getCode')->willReturn($code);
        $model->method('getLabel')->willReturn('Label ' . $code);
        $model->method('isEnabled')->willReturn($enabled);
        $model->method('getIncludeProvinceCodes')->willReturn([]);
        $model->method('getIncludeWardCodes')->willReturn([]);
        $model->method('getExcludeWardCodes')->willReturn([]);

        return $model;
    }

    public function testSaveValidatesThenPersistsThenFlushesCache(): void
    {
        $model = $this->createMock(ZoneModel::class);
        $this->zoneFactory->method('create')->willReturn($model);

        $order = [];
        $this->zoneResource->expects($this->once())->method('save')->willReturnCallback(
            static function () use (&$order): void {
                $order[] = 'save';
            }
        );
        $this->cacheTypeList->expects($this->once())->method('cleanType')->willReturnCallback(
            static function (string $typeId) use (&$order): void {
                $order[] = 'clean:' . $typeId;
            }
        );

        $this->repository->save($this->validZone());

        $this->assertSame(['save', 'clean:secomm_shippingcore_zones'], $order);
    }

    public function testSaveRejectsOnValidationFailureWithoutPersisting(): void
    {
        $this->zoneResource->expects($this->never())->method('save');
        $this->cacheTypeList->expects($this->never())->method('cleanType');

        $this->expectException(LocalizedException::class);
        // 'VN-99' is unknown in the reference-layer fixture — validation must reject.
        $this->repository->save(new CanonicalZone('BAD', 'Bad zone', true, ['VN-99'], [], []));
    }

    public function testSaveUpdateUsesLoadedModelAndExcludesSelfInValidation(): void
    {
        $model = $this->createMock(ZoneModel::class);
        $model->method('getId')->willReturn(7);
        $this->zoneFactory->method('create')->willReturn($model);
        $this->zoneResource->method('load')->willReturnCallback(
            static function ($model, $id): void {
                $model->setData('zone_id', $id);
            }
        );
        $this->existingCodeCount = 0;
        $this->zoneResource->expects($this->once())->method('save');
        $this->cacheTypeList->expects($this->once())->method('cleanType');

        $zoneId = $this->repository->save($this->validZone(), 7);

        $this->assertSame(7, $zoneId);
    }

    public function testSaveUpdateUnknownIdThrowsNoSuchEntity(): void
    {
        $model = $this->createMock(ZoneModel::class);
        $model->method('getId')->willReturn(null);
        $this->zoneFactory->method('create')->willReturn($model);
        $this->zoneResource->method('load');

        $this->expectException(NoSuchEntityException::class);
        $this->repository->save($this->validZone(), 99);
    }

    public function testSetEnabledFlipsFlagWithoutValidationAndFlushesCache(): void
    {
        $model = $this->createMock(ZoneModel::class);
        $model->method('getId')->willReturn(7);
        $this->zoneFactory->method('create')->willReturn($model);
        $this->zoneResource->method('load')->willReturnCallback(
            static function ($model, $id): void {
                $model->setData('zone_id', $id);
            }
        );
        $model->expects($this->once())->method('setEnabled')->with(false);
        $this->zoneResource->expects($this->once())->method('save');
        $this->cacheTypeList->expects($this->once())->method('cleanType');

        $this->repository->setEnabled(7, false);
    }

    public function testDeleteFlushesCache(): void
    {
        $model = $this->createMock(ZoneModel::class);
        $model->method('getId')->willReturn(3);
        $this->zoneFactory->method('create')->willReturn($model);
        $this->zoneResource->method('load')->willReturnCallback(
            static function ($model, $id): void {
                $model->setData('zone_id', $id);
            }
        );
        $this->zoneResource->expects($this->once())->method('delete');
        $this->cacheTypeList->expects($this->once())->method('cleanType');

        $this->repository->deleteById(3);
    }

    public function testGetAllMapsVosSortedByCode(): void
    {
        $this->collection->method('getItems')->willReturn([
            $this->modelStub(2, 'ZZ_LAST'),
            $this->modelStub(1, 'AA_FIRST', false),
        ]);
        $zones = $this->repository->getAll();

        $this->assertSame('AA_FIRST', $zones[0]->getCode());
        $this->assertSame('ZZ_LAST', $zones[1]->getCode());
        $this->assertFalse($zones[0]->isEnabled());
    }

    public function testGetEnabledByCodesPreservesInputOrderAndSkipsDisabled(): void
    {
        $this->collection->method('getItems')->willReturn([
            $this->modelStub(1, 'AAA'),
            $this->modelStub(2, 'BBB', false),
            $this->modelStub(3, 'CCC'),
        ]);

        $zones = $this->repository->getEnabledByCodes(['CCC', 'BBB', 'AAA', 'UNKNOWN']);

        $this->assertSame(
            ['CCC', 'AAA'],
            array_map(static fn ($zone) => $zone->getCode(), $zones)
        );
    }

    public function testGetByCodeReturnsNullWhenUnknown(): void
    {
        $emptyItem = $this->createMock(ZoneModel::class);
        $emptyItem->method('getId')->willReturn(null);
        $this->collection->method('getFirstItem')->willReturn($emptyItem);

        $this->assertNull($this->repository->getByCode('NOPE'));
    }

    public function testZoneCacheTypeIdIsStable(): void
    {
        $this->assertSame('secomm_shippingcore_zones', ZoneCacheType::TYPE_ID);
    }
}
