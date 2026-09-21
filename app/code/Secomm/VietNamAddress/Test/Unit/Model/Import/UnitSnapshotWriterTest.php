<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Import\UnitSnapshotWriter;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * BUG-ZTGGYZ (U1) — the unit snapshot MUST carry the complete canonical parent relation:
 * seed rows with an implicit province edge (empty parent_code) are written with
 * parent_code = region_code (the province unit), while explicit seed parents (PRE_2025
 * ward → district) pass through untouched. Level still follows the RAW seed parent.
 * Real dataset rows from VN_ADMIN_2025_import.csv / VN_ADMIN_PRE_2025_SNAPSHOT_2024_import.csv.
 */
class UnitSnapshotWriterTest extends TestCase
{
    private Mysql&MockObject $adapter;

    /** @var array<int, array<int, array<string, mixed>>> captured insertOnDuplicate batches */
    private array $writtenBatches;

    private UnitSnapshotWriter $writer;

    protected function setUp(): void
    {
        $this->writtenBatches = [];

        $this->adapter = $this->createMock(Mysql::class);
        $this->adapter->method('insertOnDuplicate')->willReturnCallback(
            function (string $table, array $row): int {
                $this->writtenBatches[] = $row;

                return count($row);
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => $name);

        $this->writer = new UnitSnapshotWriter($resource);
    }

    public function testRegionRowsStayRoots(): void
    {
        $written = $this->write(
            VnSchemes::VN_ADMIN_2025,
            [['region_code' => 'VN-34', 'name_vi' => 'Vĩnh Long', 'name_en' => 'Vinh Long']],
            []
        );

        $this->assertSame(1, $written);
        $region = $this->flatRows()[0];
        $this->assertSame('VN-34', $region['code']);
        $this->assertNull($region['parent_code']);
        $this->assertSame(1, $region['level']);
    }

    public function test2025WardSynthesizesProvinceParentEdge(): void
    {
        // Real row — Long Vĩnh ward (Vĩnh Long): seed ships parent_code EMPTY.
        $written = $this->write(
            VnSchemes::VN_ADMIN_2025,
            [['region_code' => 'VN-34', 'name_vi' => 'Vĩnh Long', 'name_en' => 'Vinh Long']],
            [['region_code' => 'VN-34', 'code' => 'VNA25-F2118484F0', 'parent_code' => '', 'name_vi' => 'Long Vĩnh', 'name_en' => 'Long Vinh']]
        );

        $this->assertSame(2, $written);
        $ward = $this->flatRows()[1];
        $this->assertSame('VNA25-F2118484F0', $ward['code']);
        $this->assertSame('VN-34', $ward['parent_code'], 'province edge must be synthesised from region_code');
        $this->assertSame(2, $ward['level'], 'a synthesised province edge must not promote the ward to level 3');
        $this->assertSame('Long Vĩnh', $ward['name_vi'], 'display names are never altered');
    }

    public function testPre2025DistrictSynthesizesProvinceParentEdge(): void
    {
        // Real row — district An Phú (An Giang): seed ships parent_code EMPTY (level-2 row).
        $written = $this->write(
            VnSchemes::VN_ADMIN_PRE_2025,
            [['region_code' => 'VN-01', 'name_vi' => 'An Giang', 'name_en' => 'An Giang']],
            [['region_code' => 'VN-01', 'code' => 'VNAP25-B70EDA95D6', 'parent_code' => '', 'name_vi' => 'An Phú', 'name_en' => 'An Phu']]
        );

        $district = $this->flatRows()[1];
        $this->assertSame('VNAP25-B70EDA95D6', $district['code']);
        $this->assertSame('VN-01', $district['parent_code']);
        $this->assertSame(2, $district['level']);
    }

    public function testPre2025WardKeepsExplicitDistrictParent(): void
    {
        // Real row — ward Long Vĩnh (Trà Vinh): seed parent = its district, must pass through.
        $written = $this->write(
            VnSchemes::VN_ADMIN_PRE_2025,
            [['region_code' => 'VN-59', 'name_vi' => 'Trà Vinh', 'name_en' => 'Tra Vinh']],
            [['region_code' => 'VN-59', 'code' => 'VNAP25-01965FA7E0', 'parent_code' => 'VNAP25-C5B8541622', 'name_vi' => 'Long Vĩnh', 'name_en' => 'Long Vinh']]
        );

        $ward = $this->flatRows()[1];
        $this->assertSame('VNAP25-C5B8541622', $ward['parent_code'], 'explicit seed parent is authoritative');
        $this->assertSame(3, $ward['level']);
    }

    public function testUnitCitingUnknownRegionFailsLoudWithoutWrites(): void
    {
        try {
            $this->write(
                VnSchemes::VN_ADMIN_2025,
                [['region_code' => 'VN-34', 'name_vi' => 'Vĩnh Long', 'name_en' => 'Vinh Long']],
                [['region_code' => 'VN-99', 'code' => 'VNA25-AAAAAAAAAA', 'parent_code' => '', 'name_vi' => 'X', 'name_en' => 'X']]
            );
            $this->fail('Expected LocalizedException');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('VN-99', $e->getMessage());
        }

        $this->assertSame([], $this->writtenBatches, 'a rejected dataset must write nothing');
    }

    public function testUnknownSchemeIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->write('VN_ADMIN_2050', [], []);
    }

    // ---------- helpers ----------

    /**
     * @param array<int, array<string, string>> $regionRows
     * @param array<int, array<string, string>> $unitRows
     */
    private function write(string $scheme, array $regionRows, array $unitRows): int
    {
        return $this->writer->write($scheme, $regionRows, $unitRows);
    }

    /**
     * @return array<int, array<string, mixed>> all rows across batches, in write order
     */
    private function flatRows(): array
    {
        return array_merge(...$this->writtenBatches ?: [[]]);
    }
}
