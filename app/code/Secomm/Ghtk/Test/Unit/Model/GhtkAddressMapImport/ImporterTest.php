<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\GhtkAddressMapImport;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Model\Address\DestinationAddressResolver;
use Secomm\Ghtk\Model\GhtkAddressMapImport\CsvReader;
use Secomm\Ghtk\Model\GhtkAddressMapImport\Importer;
use Secomm\Ghtk\Model\GhtkAddressMapImport\Validator;

class ImporterTest extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        $this->paths = [];
    }

    private function writeCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ghtk_imp') . '.csv';
        file_put_contents($path, $content);
        $this->paths[] = $path;

        return $path;
    }

    private function importer(array $existingRows, AdapterInterface $adapter): Importer
    {
        $adapter->method('select')->willReturn($this->createMock(Select::class));
        $adapter->method('fetchAll')->willReturn($existingRows);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $cache = $this->createMock(CacheInterface::class);

        return new Importer(
            new CsvReader(),
            new Validator(),
            $resource,
            $cache,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function validHeader(): string
    {
        return "country_id,region_id,ward_id,ghtk_province,ghtk_district,ghtk_ward,is_active\n";
    }

    public function testValidImportInsertsAndCommits(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('insertOnDuplicate');
        $adapter->expects($this->once())->method('commit');
        $adapter->expects($this->never())->method('delete');
        $adapter->expects($this->never())->method('rollBack');

        $importer = $this->importer([], $adapter);
        $csv = $this->writeCsv($this->validHeader()
            . "VN,1,1,Hà Nội,Hoàn Kiếm,Phường A,1\n"
            . "VN,1,2,Hà Nội,Ba Đình,Phường B,1\n");

        $summary = $importer->import($csv, 'tester');

        $this->assertTrue($summary->isCommitted());
        $this->assertSame(2, $summary->getInserted());
        $this->assertSame(0, $summary->getUpdated());
        $this->assertSame(0, $summary->getRemoved());
        $this->assertFalse($summary->hasErrors());
    }

    public function testReplaceAllRemovesRowsNotInCsv(): void
    {
        $existing = [['map_id' => 10, 'country_id' => 'VN', 'region_id' => '1', 'ward_id' => '99']];
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('delete');
        $adapter->expects($this->once())->method('insertOnDuplicate');
        $adapter->expects($this->once())->method('commit');

        $importer = $this->importer($existing, $adapter);
        $csv = $this->writeCsv($this->validHeader()
            . "VN,1,1,Hà Nội,,Phường A,1\n"
            . "VN,1,2,Hà Nội,,Phường B,1\n");

        $summary = $importer->import($csv, 'tester');

        $this->assertTrue($summary->isCommitted());
        $this->assertSame(2, $summary->getInserted());
        $this->assertSame(1, $summary->getRemoved());
    }

    public function testBadHeaderAbortsBeforeAnyDbWrite(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->never())->method('beginTransaction');
        $adapter->expects($this->never())->method('commit');
        $adapter->expects($this->never())->method('insertOnDuplicate');
        $adapter->expects($this->never())->method('delete');
        $adapter->method('select')->willReturn($this->createMock(Select::class));
        $adapter->method('fetchAll')->willReturn([]);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);
        $importer = new Importer(new CsvReader(), new Validator(), $resource, $this->createMock(CacheInterface::class), $this->createMock(LoggerInterface::class));

        $csv = $this->writeCsv("bad,header\nVN,1\n");
        $summary = $importer->import($csv, 'tester');

        $this->assertFalse($summary->isCommitted());
        $this->assertTrue($summary->hasErrors());
        $this->assertGreaterThanOrEqual(1, $summary->getFailed());
    }

    public function testMissingRequiredFieldAbortsBeforeAnyDbWrite(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->never())->method('commit');
        $adapter->expects($this->never())->method('insertOnDuplicate');

        $importer = $this->importer([], $adapter);
        $csv = $this->writeCsv($this->validHeader() . "VN,1,1,,Hoàn Kiếm,Phường A,1\n"); // missing ghtk_province

        $summary = $importer->import($csv, 'tester');

        $this->assertFalse($summary->isCommitted());
        $this->assertTrue($summary->hasErrors());
    }
}
