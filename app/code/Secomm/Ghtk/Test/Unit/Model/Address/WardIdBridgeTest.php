<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Address;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Model\Address\WardIdBridge;

class WardIdBridgeTest extends TestCase
{
    private function bridge(AdapterInterface $adapter, array $fetchColResults): WardIdBridge
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $adapter->method('select')->willReturn($select);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        // First fetchCol call resolves the ward id.
        $adapter->method('fetchCol')->willReturnOnConsecutiveCalls(...$fetchColResults);

        return new WardIdBridge($resource, $this->createMock(LoggerInterface::class));
    }

    public function testResolvesSingleMatch(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $bridge = $this->bridge($adapter, [[5]]);

        $this->assertSame(5, $bridge->resolveWardId(1157, 'Hoan Kiem Ward'));
    }

    public function testReturnsNullWhenNotFound(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $bridge = $this->bridge($adapter, [[]]);

        $this->assertNull($bridge->resolveWardId(1157, 'Nope'));
    }

    public function testMultipleMatchesUsesFirstAndLogs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('fetchCol')->willReturn([5, 6]);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $bridge = new WardIdBridge($resource, $logger);

        $this->assertSame(5, $bridge->resolveWardId(1157, 'Ambiguous'));
    }
}
