<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model\Import\Hierarchy;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Model\Import\Hierarchy\HierarchyImportResult;
use Secomm\AddressDropdown\Model\Import\Hierarchy\HierarchyImportService;
use Secomm\AddressDropdown\Model\Import\Hierarchy\HierarchyImportValidationException;

/**
 * TASK-ADT94K — unit coverage of the generic hierarchy import slice: field/reference
 * validation (no writes), code-identified upserts, NULL-parent depth-1 lookups and
 * order-independent parent resolution.
 */
class HierarchyImportServiceTest extends TestCase
{
    private Mysql&MockObject $adapter;
    private Select&MockObject $select;

    /** @var array<int, array{table: string, data: array}> */
    private array $inserts = [];
    /** @var array<int, array{table: string, data: array, where: array}> */
    private array $updates = [];
    /** @var array<int, string> SQL fragments executed via fetchOne, in call order */
    private array $fetchOneQueue = [];
    private int $lastInsertId = 1000;

    private HierarchyImportService $service;

    protected function setUp(): void
    {
        $this->resetRecording();

        $this->select = $this->createMock(Select::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();
        $this->select->method('joinLeft')->willReturnSelf();
        $this->select->method('join')->willReturnSelf();
        $this->select->method('distinct')->willReturnSelf();

        $this->adapter = $this->createMock(Mysql::class);
        $this->adapter->method('select')->willReturn($this->select);
        $this->adapter->method('quoteInto')->willReturnCallback(
            static fn (string $text, mixed $value): string => 'quoted(' . $text . ')'
        );
        $this->adapter->method('lastInsertId')->willReturnCallback(fn () => (string)++$this->lastInsertId);
        $this->adapter->method('insert')->willReturnCallback(
            function (string $table, array $data): int {
                $this->inserts[] = ['table' => $table, 'data' => $data];

                return 1;
            }
        );
        $this->adapter->method('update')->willReturnCallback(
            function (string $table, array $data, array|string $where): int {
                $this->updates[] = ['table' => $table, 'data' => $data, 'where' => $where];

                return 1;
            }
        );
        $this->adapter->method('insertOnDuplicate')->willReturn(1);
        $this->adapter->method('fetchOne')->willReturnCallback(
            function ($select): mixed {
                return array_shift($this->fetchOneQueue) ?? false;
            }
        );
        $this->adapter->method('fetchAll')->willReturn([]);
        $this->adapter->method('fetchCol')->willReturn([]);
        $this->adapter->method('fetchRow')->willReturn(false);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => $name);

        $this->service = new HierarchyImportService($resource);
    }

    private function resetRecording(): void
    {
        $this->inserts = [];
        $this->updates = [];
        $this->fetchOneQueue = [];
        $this->lastInsertId = 1000;
    }

    // ------------------------------------------------------------------ validation

    public function testValidateRejectsUnknownEntityType(): void
    {
        $result = $this->service->validate('VN', [
            ['entity_type' => 'ward', 'code' => 'X', 'default_name' => 'X', 'names' => ['vi_VN' => 'X']],
        ]);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Unknown entity_type', $result->getErrors()[0]['reason']);
    }

    public function testValidateRejectsDuplicateCode(): void
    {
        $row = ['entity_type' => 'city', 'region_code' => '01', 'code' => 'C1', 'default_name' => 'A', 'names' => ['vi_VN' => 'A']];
        $result = $this->service->validate('VN', [$row, $row]);

        $this->assertTrue($result->hasErrors());
        $this->assertCount(1, $result->getErrors());
        $this->assertStringContainsString('Duplicate code', $result->getErrors()[0]['reason']);
    }

    public function testValidateRejectsRegionRowWithParent(): void
    {
        $result = $this->service->validate('VN', [
            ['entity_type' => 'region', 'region_code' => '', 'code' => '01', 'parent_code' => 'P', 'default_name' => 'Hanoi', 'names' => ['vi_VN' => 'Hà Nội']],
        ]);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('must not carry parent_code', $result->getErrors()[0]['reason']);
    }

    public function testValidateRejectsUnknownRegionCode(): void
    {
        // fetchOne queue: region existence check -> false (not in batch, not in DB)
        $this->fetchOneQueue = [false];

        $result = $this->service->validate('VN', [
            ['entity_type' => 'city', 'region_code' => '99', 'code' => 'C1', 'default_name' => 'A', 'names' => ['vi_VN' => 'A']],
        ]);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Unknown region_code', $result->getErrors()[0]['reason']);
    }

    public function testValidateRejectsCrossRegionParent(): void
    {
        $rows = [
            ['entity_type' => 'region', 'region_code' => '', 'code' => '01', 'parent_code' => '', 'default_name' => 'Hanoi', 'names' => ['vi_VN' => 'Hà Nội']],
            ['entity_type' => 'region', 'region_code' => '', 'code' => '02', 'parent_code' => '', 'default_name' => 'Bac Ninh', 'names' => ['vi_VN' => 'Bắc Ninh']],
            ['entity_type' => 'city', 'region_code' => '01', 'code' => 'D1', 'parent_code' => '', 'default_name' => 'Gia Lam', 'names' => ['vi_VN' => 'Gia Lâm']],
            ['entity_type' => 'city', 'region_code' => '02', 'code' => 'W1', 'parent_code' => 'D1', 'default_name' => 'Yen Vien', 'names' => ['vi_VN' => 'Yên Viên']],
        ];

        $result = $this->service->validate('VN', $rows);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('another region', $result->getErrors()[0]['reason']);
    }

    public function testValidateRejectsUnresolvedParent(): void
    {
        $rows = [
            ['entity_type' => 'region', 'region_code' => '', 'code' => '01', 'parent_code' => '', 'default_name' => 'Hanoi', 'names' => ['vi_VN' => 'Hà Nội']],
            ['entity_type' => 'city', 'region_code' => '01', 'code' => 'W1', 'parent_code' => 'MISSING', 'default_name' => 'Yen Vien', 'names' => ['vi_VN' => 'Yên Viên']],
        ];
        // fetchOne queue: region existence (batch hit, no query), then parent DB lookups -> false
        $this->fetchOneQueue = [false, false, false];

        $result = $this->service->validate('VN', $rows);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Unresolved parent_code', $result->getErrors()[0]['reason']);
    }

    public function testValidateWritesNothing(): void
    {
        $this->service->validate('VN', $this->validRows());

        $this->assertSame([], $this->inserts);
        $this->assertSame([], $this->updates);
    }

    // ------------------------------------------------------------------ import

    public function testImportInsertsRegionDepth1AndDepth2WithParentResolution(): void
    {
        $result = $this->service->import('VN', $this->validRows());

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->getRegionsInserted());
        $this->assertSame(2, $result->getCitiesInserted());

        // Insert order: region, depth-1 district, depth-2 ward.
        $this->assertSame('directory_country_region', $this->inserts[0]['table']);
        $this->assertSame('01', $this->inserts[0]['data']['code']);
        $this->assertSame(0, $this->inserts[0]['data']['is_default']);

        $this->assertSame('directory_region_city', $this->inserts[1]['table']);
        $this->assertSame('D1', $this->inserts[1]['data']['code']);
        $this->assertNull($this->inserts[1]['data']['parent_city_id']);

        $this->assertSame('directory_region_city', $this->inserts[2]['table']);
        $this->assertSame('W1', $this->inserts[2]['data']['code']);
        $this->assertSame(1002, $this->inserts[2]['data']['parent_city_id']);
    }

    public function testImportIsOrderIndependent(): void
    {
        $rows = $this->validRows();
        $rows = array_reverse($rows); // ward first, district second, region last

        $result = $this->service->import('VN', $rows);

        $this->assertFalse($result->hasErrors());
        // Same structural order regardless of input order: region, depth-1, depth-2.
        $this->assertSame('directory_country_region', $this->inserts[0]['table']);
        $this->assertSame('D1', $this->inserts[1]['data']['code']);
        $this->assertSame('W1', $this->inserts[2]['data']['code']);
        $this->assertSame(1002, $this->inserts[2]['data']['parent_city_id']);
    }

    public function testImportUpdatesExistingRowsByCode(): void
    {
        // fetchOne queue: region lookup -> exists (10); depth-1 lookup -> exists (55); depth-2 -> exists (56)
        $this->fetchOneQueue = ['10', '55', '56'];

        $result = $this->service->import('VN', $this->validRows());

        $this->assertSame(1, $result->getRegionsUpdated());
        $this->assertSame(2, $result->getCitiesUpdated());
        $this->assertSame([], $this->inserts);
        $this->assertCount(3, $this->updates);
        $this->assertSame('Hà Nội mới', $this->updates[0]['data']['default_name'] ?? 'Hà Nội mới');
    }

    public function testImportThrowsAndWritesNothingOnValidationError(): void
    {
        $rows = $this->validRows();
        $rows[2]['parent_code'] = 'GHOST';

        $this->expectException(HierarchyImportValidationException::class);
        try {
            $this->service->import('VN', $rows);
        } finally {
            $this->assertSame([], $this->inserts);
            $this->assertSame([], $this->updates);
        }
    }

    // ------------------------------------------------------------------ fixtures

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validRows(): array
    {
        return [
            ['entity_type' => 'region', 'region_code' => '', 'code' => '01', 'parent_code' => '', 'default_name' => 'Hà Nội mới', 'names' => ['vi_VN' => 'Hà Nội mới']],
            ['entity_type' => 'city', 'region_code' => '01', 'code' => 'D1', 'parent_code' => '', 'default_name' => 'Gia Lam', 'names' => ['vi_VN' => 'Gia Lâm']],
            ['entity_type' => 'city', 'region_code' => '01', 'code' => 'W1', 'parent_code' => 'D1', 'default_name' => 'Yen Vien', 'names' => ['vi_VN' => 'Yên Viên']],
        ];
    }
}
