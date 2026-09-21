<?php
/*
 * TASK-JZXM66 — import template builder tests: header == importer superset + city_name,
 * example row prefilled from a real city, wildcard demonstration row, fallback behavior.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Model\Adminhtml;

use Launchpad\MageplazaTableRate\Model\Adminhtml\CsvBuilder;
use Launchpad\MageplazaTableRate\Model\Adminhtml\ImportTemplateBuilder;
use Launchpad\MageplazaTableRate\Model\MptablerateImport;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImportTemplateBuilderTest extends TestCase
{
    private ResourceConnection|MockObject $resourceConnection;

    private AdapterInterface|MockObject $connection;

    private Select|MockObject $select;

    private ImportTemplateBuilder $builder;

    protected function setUp(): void
    {
        $this->resourceConnection = $this->getMockBuilder(ResourceConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getTableName'])
            ->getMock();
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'joinLeft', 'where', 'order', 'limit'])
            ->getMock();

        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnArgument(0);
        foreach (['from', 'joinLeft', 'where', 'order', 'limit'] as $fluent) {
            $this->select->method($fluent)->willReturnSelf();
        }
        $this->connection->method('select')->willReturn($this->select);

        $this->builder = new ImportTemplateBuilder($this->resourceConnection, new CsvBuilder());
    }

    public function testBuildRowsPrefillExampleCityForRequestedRegion(): void
    {
        $wheres = [];
        $this->select->method('where')->willReturnCallback(
            function (string $condition, $value = null) use (&$wheres) {
                $wheres[] = [$condition, $value];

                return $this->select;
            }
        );
        $this->connection->method('fetchRow')->willReturn([
            'city_code' => 'VNA25-X',
            'city_name' => 'Phường Bến Nghé',
            'region_id' => '20',
            'country_id' => 'VN',
        ]);

        $rows = $this->builder->buildRows(20);

        $this->assertContains(['city.region_id = ?', 20], $wheres);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertCount(count(MptablerateImport::templateColumns()), $row);
        }

        $this->assertSame('Example rate', $rows[0][0]);
        $this->assertSame('VN', $rows[0][1]);
        $this->assertSame('20', $rows[0][2]);
        $this->assertSame('0', $rows[0][3]);       // weight_from
        $this->assertSame('10', $rows[0][4]);      // weight_to
        $this->assertSame('999999999', $rows[0][6]);
        $this->assertSame('2', $rows[0][13]);      // delivery
        $this->assertSame('', $rows[0][14]);       // postcode
        $this->assertSame('', $rows[0][17]);       // shipping_group
        $this->assertSame('VNA25-X', $rows[0][18]); // city_code
        $this->assertSame('Phường Bến Nghé', $rows[0][19]); // city_name

        $this->assertSame('Example rate (wildcard)', $rows[1][0]);
        $this->assertSame('*', $rows[1][1]);
        $this->assertSame('*', $rows[1][2]);
        $this->assertSame('', $rows[1][18]);
        $this->assertSame('', $rows[1][19]);
    }

    public function testBuildRowsDefaultsToFirstVnRegionWhenNoRegionGiven(): void
    {
        $wheres = [];
        $this->select->method('where')->willReturnCallback(
            function (string $condition, $value = null) use (&$wheres) {
                $wheres[] = [$condition, $value];

                return $this->select;
            }
        );
        $this->connection->method('fetchRow')->willReturn([
            'city_code' => 'VNA25-Y',
            'city_name' => 'Phường Y',
            'region_id' => '53',
            'country_id' => 'VN',
        ]);

        $rows = $this->builder->buildRows(null);

        $this->assertContains(['region.country_id = ?', 'VN'], $wheres);
        $this->assertSame('VNA25-Y', $rows[0][18]);
    }

    public function testBuildRowsFallsBackToWildcardOnlyWhenNoCodedCitiesExist(): void
    {
        $this->connection->method('fetchRow')->willReturn(false);

        $rows = $this->builder->buildRows(20);

        $this->assertCount(1, $rows);
        $this->assertSame('*', $rows[0][2]);
        $this->assertSame('', $rows[0][18]);
    }

    public function testBuildPrependsImporterTemplateHeader(): void
    {
        $this->connection->method('fetchRow')->willReturn(false);

        $csv = $this->builder->build(null);

        $this->assertStringStartsWith(CsvBuilder::UTF8_BOM, $csv);
        $body = rtrim(substr($csv, strlen(CsvBuilder::UTF8_BOM)), "\n");
        $header = explode("\n", $body)[0];

        $this->assertSame(implode(',', MptablerateImport::templateColumns()), $header);
        $this->assertSame('city_name', explode(',', $header)[19]);
    }
}
