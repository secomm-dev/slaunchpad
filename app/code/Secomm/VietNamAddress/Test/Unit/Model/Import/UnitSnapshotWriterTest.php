<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Import\UnitSnapshotWriter;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-9394A9 — historical unit snapshot: regions stored as level-1 units, wards as
 * level 2/3 by parent presence, batched upsert with immutable codes (name-only update).
 */
class UnitSnapshotWriterTest extends TestCase
{
    private Mysql&MockObject $adapter;

    /** @var array<int, array{table: string, rows: array, fields: array}> */
    private array $upserts = [];

    private UnitSnapshotWriter $writer;

    protected function setUp(): void
    {
        $this->upserts = [];

        $this->adapter = $this->createMock(Mysql::class);
        $this->adapter->method('insertOnDuplicate')->willReturnCallback(
            function (string $table, array $rows, array $fields = []): int {
                $this->upserts[] = ['table' => $table, 'rows' => $rows, 'fields' => $fields];

                return count($rows);
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => $name);

        $this->writer = new UnitSnapshotWriter($resource);
    }

    public function testWritesRegionsAsLevelOneAndUnitsByParentPresence(): void
    {
        $regions = [
            ['line' => 2, 'region_code' => '32', 'name_vi' => 'An Giang', 'name_en' => 'An Giang'],
        ];
        $units = [
            ['line' => 3, 'region_code' => '32', 'code' => 'VNA25-3D6A6CF4D0', 'parent_code' => '', 'name_vi' => 'An Biên', 'name_en' => 'An Bien'],
            ['line' => 4, 'region_code' => '89', 'code' => 'VNAP25-515AFEF59D', 'parent_code' => 'VNAP25-B70EDA95D6', 'name_vi' => 'An Phú', 'name_en' => 'An Phu'],
        ];

        $count = $this->writer->write(VnSchemes::VN_ADMIN_2025, $regions, $units);

        $this->assertSame(3, $count);
        $this->assertCount(1, $this->upserts);
        $rows = $this->upserts[0]['rows'];

        // Region row: level 1, code = region code, self region_code, no parent.
        $this->assertSame('32', $rows[0]['code']);
        $this->assertSame(1, $rows[0]['level']);
        $this->assertNull($rows[0]['parent_code']);
        $this->assertSame(VnSchemes::VN_ADMIN_2025, $rows[0]['scheme_code']);

        // Depth-1 unit: level 2, NULL parent.
        $this->assertSame(2, $rows[1]['level']);
        $this->assertNull($rows[1]['parent_code']);

        // Depth-2 unit: level 3, parent code set.
        $this->assertSame(3, $rows[2]['level']);
        $this->assertSame('VNAP25-B70EDA95D6', $rows[2]['parent_code']);

        // Codes immutable: upsert updates display data only.
        $this->assertSame(['parent_code', 'region_code', 'level', 'name_vi', 'name_en'], $this->upserts[0]['fields']);
    }

    public function testBatchesLargeDatasets(): void
    {
        $units = [];
        for ($i = 0; $i < 1200; $i++) {
            $units[] = ['line' => $i + 2, 'region_code' => '01', 'code' => sprintf('VNA25-%010X', $i), 'parent_code' => '', 'name_vi' => 'X', 'name_en' => 'X'];
        }

        $count = $this->writer->write(VnSchemes::VN_ADMIN_2025, [], $units);

        $this->assertSame(1200, $count);
        $this->assertCount(3, $this->upserts); // 500 + 500 + 200
    }
}
