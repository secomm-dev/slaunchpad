<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Helper;

use Magento\Customer\Api\Data\RegionInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Helper\Data;
use Secomm\AddressDropdown\Model\OptionSource\SourceItemLocale;
use Secomm\AddressDropdown\Model\Region;
use Secomm\AddressDropdown\Model\RegionFactory;
use Secomm\AddressDropdown\Model\RegionModel;
use Secomm\AddressDropdown\Model\ResourceModel\CityResource as CityResourceModel;
use Secomm\AddressDropdown\Model\CityModelFactory;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel as RegionResourceModel;
use Magento\Framework\Locale\Config;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;

/**
 * TASK-SEC-A1 — regression for the region-name lookup SQLi fix: the id is cast at the
 * boundary, non-positive ids never touch the database, and the query uses a bound
 * parameter with an explicit column list (never SELECT *).
 */
class DataTest extends TestCase
{
    private ResourceConnection&MockObject $resourceConnection;
    private AdapterInterface&MockObject $adapter;
    private Data $helper;
    /** @var array<int, array{0: string, 1: mixed}> captured where() bindings */
    private array $whereCalls = [];
    /** @var string[]|null captured from() column list */
    private ?array $fromColumns = null;

    protected function setUp(): void
    {
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->adapter = $this->createMock(AdapterInterface::class);
        $this->resourceConnection->method('getConnection')->willReturn($this->adapter);
        $this->adapter->method('getTableName')->willReturnArgument(0);
        $this->whereCalls = [];

        $this->helper = new Data(
            $this->resourceConnection,
            $this->createMock(Config::class),
            $this->createMock(SourceItemLocale::class),
            $this->createMock(CountryCollectionFactory::class),
            $this->createMock(RegionResourceModel::class),
            $this->createMock(RegionFactory::class),
            $this->createMock(CityResourceModel::class),
            $this->createMock(CityModelFactory::class),
            $this->createMock(Context::class)
        );
    }

    public function testValidRegionBuildsBoundParameterizedSelect(): void
    {
        $select = $this->makeSelect();
        $this->adapter->method('select')->willReturn($select);
        $this->adapter->method('fetchAll')->with($select)->willReturn([['region_id' => 5, 'locale' => 'en_US', 'name' => 'Name']]);

        $result = $this->helper->getAllRegionNamesByRegionId(5);

        $this->assertSame([['region_id' => 5, 'locale' => 'en_US', 'name' => 'Name']], $result);
        // Explicit column list — never SELECT *.
        $this->assertSame(['region_id', 'locale', 'name'], $this->fromColumns);
        $this->assertSame('region_id = ?', $this->whereCalls[0][0]);
        $this->assertSame(5, $this->whereCalls[0][1]);
    }

    public function testMaliciousPayloadIsNeutralizedToInteger(): void
    {
        $this->adapter->method('select')->willReturn($this->makeSelect());
        $this->adapter->method('fetchAll')->willReturn([]);
        $this->adapter->expects($this->once())->method('fetchAll');

        // "1 OR 1=1" casts to 1 — the query can only ever reference region 1.
        $this->helper->getAllRegionNamesByRegionId('1 OR 1=1');

        $this->assertSame(1, $this->whereCalls[0][1]);
    }

    /**
     * @dataProvider nonPositiveIdProvider
     */
    public function testNonPositiveIdsNeverTouchTheDatabase(mixed $input): void
    {
        $this->adapter->expects($this->never())->method('select');
        $this->adapter->expects($this->never())->method('fetchAll');

        $this->assertSame([], $this->helper->getAllRegionNamesByRegionId($input));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public function nonPositiveIdProvider(): array
    {
        return [
            'non-numeric string' => ['abc'],
            'zero' => [0],
            'negative' => [-3],
            'null' => [null],
            'empty string' => [''],
        ];
    }

    private function makeSelect(): Select
    {
        $this->whereCalls = [];
        $this->fromColumns = null;
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnCallback(
            function (string $table, array $columns) use ($select): Select {
                $this->assertSame('directory_country_region_name', $table);
                $this->fromColumns = $columns;

                return $select;
            }
        );
        $select->method('where')->willReturnCallback(
            function (string $cond, mixed $value) use ($select): Select {
                $this->whereCalls[] = [$cond, $value];

                return $select;
            }
        );
        $select->method('order')->willReturnSelf();

        return $select;
    }
}
