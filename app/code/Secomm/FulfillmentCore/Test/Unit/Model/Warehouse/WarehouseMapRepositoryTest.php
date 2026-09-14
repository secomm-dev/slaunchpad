<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Test\Unit\Model\Warehouse;

use PHPUnit\Framework\TestCase;
use Secomm\FulfillmentCore\Model\FulfillmentWarehouseMap;
use Secomm\FulfillmentCore\Model\FulfillmentWarehouseMapFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentWarehouseMap as WarehouseMapResource;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentWarehouseMap\Collection;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentWarehouseMap\CollectionFactory;
use Secomm\FulfillmentCore\Model\Warehouse\WarehouseMapConflictException;
use Secomm\FulfillmentCore\Model\Warehouse\WarehouseMapRepository;

class WarehouseMapRepositoryTest extends TestCase
{
    public function testSaveThrowsOnSourceConflict(): void
    {
        $map = $this->createMock(FulfillmentWarehouseMap::class);
        $map->method('getEntityId')->willReturn(null);
        $map->method('getServiceCode')->willReturn('pancake');
        $map->method('getMagentoSourceCode')->willReturn('hcm');
        $map->method('getExternalWarehouseId')->willReturn('wh-new');

        $conflictCollection = $this->createMock(Collection::class);
        $conflictCollection->method('addFieldToFilter')->willReturnSelf();
        $conflictCollection->method('getSize')->willReturn(1);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($conflictCollection);

        $repo = new WarehouseMapRepository(
            $this->createMock(FulfillmentWarehouseMapFactory::class),
            $this->createMock(WarehouseMapResource::class),
            $collectionFactory
        );

        $this->expectException(WarehouseMapConflictException::class);
        $repo->save($map);
    }

    public function testSavePersistsWhenNoConflict(): void
    {
        $map = $this->createMock(FulfillmentWarehouseMap::class);
        $map->method('getEntityId')->willReturn(null);
        $map->method('getServiceCode')->willReturn('pancake');
        $map->method('getMagentoSourceCode')->willReturn('hcm');
        $map->method('getExternalWarehouseId')->willReturn('wh-1');

        $emptyCollection = $this->createMock(Collection::class);
        $emptyCollection->method('addFieldToFilter')->willReturnSelf();
        $emptyCollection->method('getSize')->willReturn(0);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($emptyCollection);

        $resource = $this->createMock(WarehouseMapResource::class);
        $resource->expects($this->once())->method('save')->with($map);

        $repo = new WarehouseMapRepository(
            $this->createMock(FulfillmentWarehouseMapFactory::class),
            $resource,
            $collectionFactory
        );

        $this->assertSame($map, $repo->save($map));
    }
}
