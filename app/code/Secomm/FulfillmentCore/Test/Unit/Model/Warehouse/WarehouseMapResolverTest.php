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
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentWarehouseMap\Collection;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentWarehouseMap\CollectionFactory;
use Secomm\FulfillmentCore\Model\Warehouse\WarehouseMapResolver;

class WarehouseMapResolverTest extends TestCase
{
    public function testResolveReturnsActiveMap(): void
    {
        $map = $this->createMock(FulfillmentWarehouseMap::class);
        $map->method('getEntityId')->willReturn(3);
        $map->method('getExternalWarehouseId')->willReturn('wh-1');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($map);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $resolver = new WarehouseMapResolver($factory);
        $result = $resolver->resolve('pancake', 'hcm');

        $this->assertSame($map, $result);
        $this->assertSame('wh-1', $result->getExternalWarehouseId());
    }

    public function testResolveReturnsNullWhenUnmapped(): void
    {
        $empty = $this->createMock(FulfillmentWarehouseMap::class);
        $empty->method('getEntityId')->willReturn(null);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($empty);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $resolver = new WarehouseMapResolver($factory);
        $this->assertNull($resolver->resolve('pancake', 'unknown'));
    }
}
