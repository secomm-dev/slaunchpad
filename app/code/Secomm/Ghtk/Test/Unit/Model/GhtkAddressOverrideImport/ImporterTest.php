<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\GhtkAddressOverrideImport;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Secomm\Ghtk\Model\Address\GhtkAddressAdapter;
use Secomm\Ghtk\Model\GhtkAddressOverrideImport\CsvReader;
use Secomm\Ghtk\Model\GhtkAddressOverrideImport\Importer;
use Secomm\Ghtk\Model\GhtkAddressOverrideImport\Validator;

/**
 * TASK-7AJ3K8 r1 — the override replace-all importer: canonical-keyed rows, all-or-nothing,
 * adapter cache invalidation.
 */
class ImporterTest extends TestCase
{
    private const SCHEME = VnSchemes::VN_ADMIN_2025;

    private VnAddressUnitProviderInterface&MockObject $unitProvider;
    private array $paths = [];

    protected function setUp(): void
    {
        $this->unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ([$scheme, $code]) {
                [self::SCHEME, 'VN-01'] => $this->unit('VN-01', 1, 'VN-01'),
                [self::SCHEME, 'VNA25-AAAA'] => $this->unit('VNA25-AAAA', 2, 'VN-01'),
                [self::SCHEME, 'VNA25-BBBB'] => $this->unit('VNA25-BBBB', 2, 'VN-01'),
                default => null,
            }
        );
    }

    private function unit(string $code, int $level, string $regionCode): VnAddressUnitInterface&MockObject
    {
        $unit = $this->createMock(VnAddressUnitInterface::class);
        $unit->method('getCode')->willReturn($code);
        $unit->method('getLevel')->willReturn($level);
        $unit->method('getRegionCode')->willReturn($regionCode);

        return $unit;
    }

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

        return new Importer(
            new CsvReader(),
            new Validator($this->unitProvider),
            $resource,
            $this->createMock(CacheInterface::class),
            $this->createMock(LoggerInterface::class)
        );
    }

    private function validHeader(): string
    {
        return "scheme_code,province_code,ward_code,ghtk_province,ghtk_district,ghtk_ward,is_active,note\n";
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
            . self::SCHEME . ",VN-01,VNA25-AAAA,,,GHTK Ward A,1,e1\n"
            . self::SCHEME . ",VN-01,VNA25-BBBB,GHTK Province,,,1,e2\n");

        $summary = $importer->import($csv, 'tester');

        $this->assertTrue($summary->isCommitted());
        $this->assertSame(2, $summary->getInserted());
        $this->assertSame(0, $summary->getUpdated());
        $this->assertSame(0, $summary->getRemoved());
        $this->assertFalse($summary->hasErrors());
    }

    public function testReplaceAllRemovesRowsNotInCsv(): void
    {
        $existing = [['map_id' => 10, 'scheme_code' => self::SCHEME, 'province_code' => 'VN-01', 'ward_code' => 'VNA25-GONE']];
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('delete');
        $adapter->expects($this->once())->method('insertOnDuplicate');
        $adapter->expects($this->once())->method('commit');

        $importer = $this->importer($existing, $adapter);
        $csv = $this->writeCsv($this->validHeader()
            . self::SCHEME . ",VN-01,VNA25-AAAA,,,GHTK Ward A,1,e1\n");

        $summary = $importer->import($csv, 'tester');

        $this->assertTrue($summary->isCommitted());
        $this->assertSame(1, $summary->getInserted());
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
        $importer = new Importer(new CsvReader(), new Validator($this->unitProvider), $resource, $this->createMock(CacheInterface::class), $this->createMock(LoggerInterface::class));

        $csv = $this->writeCsv("bad,header\nx,y\n");
        $summary = $importer->import($csv, 'tester');

        $this->assertFalse($summary->isCommitted());
        $this->assertTrue($summary->hasErrors());
        $this->assertGreaterThanOrEqual(1, $summary->getFailed());
    }

    public function testCanonicalValidationFailureAbortsBeforeAnyDbWrite(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->never())->method('commit');
        $adapter->expects($this->never())->method('insertOnDuplicate');

        $importer = $this->importer([], $adapter);
        // Unknown ward code — canonical validation via the unit provider fails.
        $csv = $this->writeCsv($this->validHeader()
            . self::SCHEME . ",VN-01,VNA25-GONE,,,GHTK Ward,1,e\n");

        $summary = $importer->import($csv, 'tester');

        $this->assertFalse($summary->isCommitted());
        $this->assertTrue($summary->hasErrors());
    }
}
