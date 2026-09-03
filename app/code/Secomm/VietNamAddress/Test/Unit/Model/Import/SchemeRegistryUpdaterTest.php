<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Import\SchemeRegistryUpdater;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-9394A9 — registry transitions: imported scheme → CURRENT, previously current →
 * HISTORICAL (identity never changes), FUTURE untouched, upsert idempotent.
 */
class SchemeRegistryUpdaterTest extends TestCase
{
    private Mysql&MockObject $adapter;

    /** @var array<int, array{table: string, data: array, condition: string}> */
    private array $updates = [];
    /** @var array<int, array{table: string, data: array}> */
    private array $upserts = [];

    private SchemeRegistryUpdater $updater;

    protected function setUp(): void
    {
        $this->updates = [];
        $this->upserts = [];

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $this->adapter = $this->createMock(Mysql::class);
        $this->adapter->method('select')->willReturn($select);
        $this->adapter->method('quoteInto')->willReturnCallback(
            static fn (string $text, mixed $value): string => 'q(' . $text . ')'
        );
        $this->adapter->method('update')->willReturnCallback(
            function (string $table, array $data, array|string $cond): int {
                $this->updates[] = ['table' => $table, 'data' => $data, 'condition' => (string)$cond];

                return 1;
            }
        );
        $this->adapter->method('insertOnDuplicate')->willReturnCallback(
            function (string $table, array $data, array $fields = []): int {
                $this->upserts[] = ['table' => $table, 'data' => $data];

                return 1;
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => $name);

        $this->updater = new SchemeRegistryUpdater($resource);
    }

    public function testDemotesPreviousCurrentAndPromotesImportedScheme(): void
    {
        $this->updater->apply(VnSchemes::VN_ADMIN_PRE_2025);

        // 1) every other CURRENT row -> HISTORICAL (the mocked quoteInto keeps the
        //    placeholders; the real adapter substitutes the values)
        $this->assertCount(1, $this->updates);
        $this->assertSame(VnSchemes::STATUS_HISTORICAL, $this->updates[0]['data']['status']);
        $this->assertStringContainsString('status = ?', $this->updates[0]['condition']);
        $this->assertStringContainsString('scheme_code <> ?', $this->updates[0]['condition']);

        // 2) imported scheme upserted as CURRENT with catalog metadata (single-row upsert)
        $this->assertCount(1, $this->upserts);
        $row = $this->upserts[0]['data'];
        $this->assertSame(VnSchemes::VN_ADMIN_PRE_2025, $row['scheme_code']);
        $this->assertSame('vn_admin_pre_2025', $row['profile_code']);
        $this->assertSame(VnSchemes::STATUS_CURRENT, $row['status']);
        $this->assertSame(3, $row['level_count']);
    }

    public function testApplyIsTransactionalAndIdempotentByUpsert(): void
    {
        $this->adapter->expects($this->exactly(2))->method('beginTransaction');
        $this->adapter->expects($this->exactly(2))->method('commit');

        $this->updater->apply(VnSchemes::VN_ADMIN_2025);
        $this->updater->apply(VnSchemes::VN_ADMIN_2025);

        $this->assertCount(2, $this->upserts);
    }
}
