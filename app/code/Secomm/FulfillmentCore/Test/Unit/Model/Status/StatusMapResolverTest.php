<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Test\Unit\Model\Status;

use PHPUnit\Framework\TestCase;
use Secomm\FulfillmentCore\Model\FulfillmentStatusMap;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentStatusMap\Collection;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentStatusMap\CollectionFactory;
use Secomm\FulfillmentCore\Model\Status\StatusMapResolver;

class StatusMapResolverTest extends TestCase
{
    public function testResolveReturnsActiveMap(): void
    {
        $map = $this->createMock(FulfillmentStatusMap::class);
        $map->method('getEntityId')->willReturn(3);
        $map->method('getNormalizedStatus')->willReturn('shipped');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($map);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $resolver = new StatusMapResolver($factory);
        $result = $resolver->resolve('pancake', '2');

        $this->assertSame($map, $result);
        $this->assertSame('shipped', $result->getNormalizedStatus());
    }

    public function testResolveReturnsNullWhenUnmapped(): void
    {
        $empty = $this->createMock(FulfillmentStatusMap::class);
        $empty->method('getEntityId')->willReturn(null);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($empty);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $resolver = new StatusMapResolver($factory);
        $this->assertNull($resolver->resolve('pancake', 'unknown'));
    }
}
