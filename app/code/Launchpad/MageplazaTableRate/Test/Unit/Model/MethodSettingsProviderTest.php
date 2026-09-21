<?php
/*
 * TASK-JZXM66 — City/Area self-service readers on MethodSettingsProvider: region resolution,
 * region-consistency guard, option list for the cascading select.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Model;

use Launchpad\MageplazaTableRate\Model\MethodSettingsProvider;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MethodSettingsProviderTest extends TestCase
{
    private ResourceConnection|MockObject $resourceConnection;

    private AdapterInterface|MockObject $connection;

    private Select|MockObject $select;

    private MethodSettingsProvider $provider;

    protected function setUp(): void
    {
        $this->resourceConnection = $this->getMockBuilder(ResourceConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getTableName'])
            ->getMock();
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'where', 'order', 'limit'])
            ->getMock();

        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnArgument(0);
        foreach (['from', 'where', 'order', 'limit'] as $fluent) {
            $this->select->method($fluent)->willReturnSelf();
        }

        $this->provider = new MethodSettingsProvider($this->resourceConnection);
    }

    public function testCityRegionIdResolvesCode(): void
    {
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->expects($this->once())->method('fetchOne')->willReturn('43');

        $this->assertSame(43, $this->provider->cityRegionId('VNA25-X'));
    }

    public function testCityRegionIdReturnsNullForUnknownCode(): void
    {
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchOne')->willReturn(false);

        $this->assertNull($this->provider->cityRegionId('VNA25-GONE'));
    }

    public function testCityRegionIdShortCircuitsEmptyCode(): void
    {
        $this->connection->expects($this->never())->method('fetchOne');

        $this->assertNull($this->provider->cityRegionId(''));
    }

    public function testCityBelongsToRegionTrue(): void
    {
        $provider = $this->getMockBuilder(MethodSettingsProvider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['cityRegionId'])
            ->getMock();
        $provider->method('cityRegionId')->willReturn(20);

        $this->assertTrue($provider->cityBelongsToRegion('VNA25-X', 20));
    }

    public function testCityBelongsToRegionFalseOnMismatch(): void
    {
        $provider = $this->getMockBuilder(MethodSettingsProvider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['cityRegionId'])
            ->getMock();
        $provider->method('cityRegionId')->willReturn(99);

        $this->assertFalse($provider->cityBelongsToRegion('VNA25-X', 20));
    }

    public function testCityBelongsToRegionFalseOnUnknownCode(): void
    {
        $provider = $this->getMockBuilder(MethodSettingsProvider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['cityRegionId'])
            ->getMock();
        $provider->method('cityRegionId')->willReturn(null);

        $this->assertFalse($provider->cityBelongsToRegion('VNA25-GONE', 20));
    }

    public function testFetchCityOptionsByRegionPrependsWildcardThenCodedNodes(): void
    {
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchAll')->willReturn([
            ['code' => 'VNA25-Z', 'default_name' => 'Phường Z'],
            ['code' => 'VNA25-A', 'default_name' => 'Phường A'],
        ]);

        $options = $this->provider->fetchCityOptionsByRegion(20);

        $this->assertSame(
            [
                ['code' => '', 'label' => MethodSettingsProvider::WILDCARD_OPTION_LABEL],
                ['code' => 'VNA25-Z', 'label' => 'Phường Z'],
                ['code' => 'VNA25-A', 'label' => 'Phường A'],
            ],
            $options
        );
    }

    public function testFetchCityOptionsByRegionWithoutRegionReturnsWildcardOnly(): void
    {
        $this->connection->expects($this->never())->method('fetchAll');

        $this->assertSame(
            [['code' => '', 'label' => MethodSettingsProvider::WILDCARD_OPTION_LABEL]],
            $this->provider->fetchCityOptionsByRegion(0)
        );
    }

    public function testCityLabelResolvesDisplayName(): void
    {
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchOne')->willReturn('Phường Bến Nghé');

        $this->assertSame('Phường Bến Nghé', $this->provider->cityLabel('VNA25-X'));
    }

    public function testCityLabelReturnsNullForUnknownCode(): void
    {
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchOne')->willReturn(false);

        $this->assertNull($this->provider->cityLabel('VNA25-GONE'));
    }
}
