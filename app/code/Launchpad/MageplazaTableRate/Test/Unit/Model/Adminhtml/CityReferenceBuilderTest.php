<?php
/*
 * TASK-JZXM66 — City Reference CSV builder tests: column contract, parent resolution,
 * uncoded-row skip, region filter, escaping/UTF-8.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Model\Adminhtml;

use Launchpad\MageplazaTableRate\Model\Adminhtml\CityReferenceBuilder;
use Launchpad\MageplazaTableRate\Model\Adminhtml\CsvBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CityReferenceBuilderTest extends TestCase
{
    private ResourceConnection|MockObject $resourceConnection;

    private AdapterInterface|MockObject $connection;

    private Select|MockObject $select;

    private CityReferenceBuilder $builder;

    protected function setUp(): void
    {
        $this->resourceConnection = $this->getMockBuilder(ResourceConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getTableName'])
            ->getMock();
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'joinLeft', 'where', 'order'])
            ->getMock();

        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnArgument(0);
        foreach (['from', 'joinLeft', 'where', 'order'] as $fluent) {
            $this->select->method($fluent)->willReturnSelf();
        }
        $this->connection->method('select')->willReturn($this->select);

        $this->builder = new CityReferenceBuilder($this->resourceConnection, new CsvBuilder());
    }

    private function dbRow(array $overrides = []): array
    {
        return array_merge([
            'country_code' => 'VN',
            'region_code' => 'VN-43',
            'region_name' => 'Tp. Hồ Chí Minh',
            'city_code' => 'VNA25-X',
            'city_name' => 'Phường Bến Nghé',
            'parent_city_code' => null,
            'parent_city_name' => null,
        ], $overrides);
    }

    public function testToCsvRowsUsesExactColumnContract(): void
    {
        $rows = $this->builder->toCsvRows([$this->dbRow()]);

        $this->assertSame(CityReferenceBuilder::CSV_COLUMNS, $rows[0]);
        $this->assertSame(
            ['VN', 'VN-43', 'Tp. Hồ Chí Minh', 'VNA25-X', 'Phường Bến Nghé', '', ''],
            $rows[1]
        );
    }

    public function testToCsvRowsResolvesParentChain(): void
    {
        $rows = $this->builder->toCsvRows([
            $this->dbRow(['parent_city_code' => 'VNA25-P', 'parent_city_name' => 'Quận 1']),
        ]);

        $this->assertSame('VNA25-P', $rows[1][5]);
        $this->assertSame('Quận 1', $rows[1][6]);
    }

    public function testToCsvRowsSkipsRowsWithoutStableCode(): void
    {
        $rows = $this->builder->toCsvRows([
            $this->dbRow(['city_code' => null]),
            $this->dbRow(['city_code' => '']),
            $this->dbRow(['city_code' => 'VNA25-KEEP']),
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('VNA25-KEEP', $rows[1][3]);
    }

    public function testToCsvStringEscapesCommasQuotesAndKeepsUtf8(): void
    {
        $csv = (new CsvBuilder())->toCsvString([
            CityReferenceBuilder::CSV_COLUMNS,
            ['VN', 'VN-43', 'Quận "1", HCM', 'VNA25-X', 'Phường Bến Nghé, Quận 1', '', ''],
        ]);

        $this->assertStringStartsWith(CsvBuilder::UTF8_BOM, $csv);
        $this->assertStringContainsString('"Quận ""1"", HCM"', $csv);
        $this->assertStringContainsString('"Phường Bến Nghé, Quận 1"', $csv);
        $this->assertStringContainsString('Phường', $csv);
    }

    public function testBuildAppliesRegionFilterAndSkipsUncodedRows(): void
    {
        $wheres = [];
        $this->select->method('where')->willReturnCallback(
            function (string $condition, $value = null) use (&$wheres) {
                $wheres[] = [$condition, $value];

                return $this->select;
            }
        );
        $this->connection->method('fetchAll')->willReturn([
            $this->dbRow(),
            $this->dbRow(['city_code' => null]),
        ]);

        $csv = $this->builder->build(20);

        $this->assertContains(['city.region_id = ?', 20], $wheres);

        $lines = explode("\n", rtrim(substr($csv, strlen(CsvBuilder::UTF8_BOM)), "\n"));
        $this->assertCount(2, $lines);
        $this->assertSame(implode(',', CityReferenceBuilder::CSV_COLUMNS), $lines[0]);
        $this->assertStringContainsString('VNA25-X', $lines[1]);
    }

    public function testBuildWithoutRegionDoesNotFilterByRegion(): void
    {
        $wheres = [];
        $this->select->method('where')->willReturnCallback(
            function (string $condition, $value = null) use (&$wheres) {
                $wheres[] = [$condition, $value];

                return $this->select;
            }
        );
        $this->connection->method('fetchAll')->willReturn([]);

        $this->builder->build(null);

        $this->assertNotContains('city.region_id = ?', array_column($wheres, 0));
    }
}
