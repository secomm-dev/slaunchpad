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
 * TASK-F9XJ5G — applyReference(): HISTORICAL upsert that never demotes any CURRENT row.
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
        // applyReference() chains limit(1) onto the status lookup — keep the fluent stub complete.
        $select->method('limit')->willReturnSelf();

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

    // ---------------------------------------------------------- TASK-F9XJ5G applyReference()

    /**
     * Missing registry row (fresh environment, Case 5): upsert HISTORICAL — the reference
     * import never activates a scheme — and no demote UPDATE runs at all.
     */
    public function testApplyReferenceWritesHistoricalForMissingRow(): void
    {
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn(false);
        $this->adapter->expects($this->never())->method('update');

        $status = $this->updater->applyReference(VnSchemes::VN_ADMIN_PRE_2025);

        $this->assertSame(VnSchemes::STATUS_HISTORICAL, $status);
        $this->assertCount(1, $this->upserts);
        $row = $this->upserts[0]['data'];
        $this->assertSame(VnSchemes::VN_ADMIN_PRE_2025, $row['scheme_code']);
        $this->assertSame('vn_admin_pre_2025', $row['profile_code']);
        $this->assertSame(3, $row['level_count']);
        $this->assertSame(VnSchemes::STATUS_HISTORICAL, $row['status']);
        $this->assertSame([], $this->updates);
    }

    /**
     * A CURRENT row keeps CURRENT (metadata refresh only) — a reference import never
     * demotes the active runtime scheme, not even itself.
     */
    public function testApplyReferenceKeepsCurrentStatus(): void
    {
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn(VnSchemes::STATUS_CURRENT);
        $this->adapter->expects($this->never())->method('update');

        $status = $this->updater->applyReference(VnSchemes::VN_ADMIN_2025);

        $this->assertSame(VnSchemes::STATUS_CURRENT, $status);
        $this->assertSame(VnSchemes::STATUS_CURRENT, $this->upserts[0]['data']['status']);
    }

    /**
     * An existing HISTORICAL row stays HISTORICAL (idempotent re-import).
     */
    public function testApplyReferenceKeepsHistoricalForHistoricalRow(): void
    {
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn(VnSchemes::STATUS_HISTORICAL);
        $this->adapter->expects($this->never())->method('update');

        $status = $this->updater->applyReference(VnSchemes::VN_ADMIN_PRE_2025);

        $this->assertSame(VnSchemes::STATUS_HISTORICAL, $status);
        $this->assertSame(VnSchemes::STATUS_HISTORICAL, $this->upserts[0]['data']['status']);
        $this->assertSame([], $this->updates);
    }

    /**
     * A registry fault rolls back — no partial upsert.
     */
    public function testApplyReferenceRollsBackOnFault(): void
    {
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn(false);
        $this->adapter->expects($this->once())->method('beginTransaction');
        $this->adapter->expects($this->never())->method('commit');
        $this->adapter->expects($this->once())->method('rollBack');
        $this->adapter->method('insertOnDuplicate')->willThrowException(new \RuntimeException('db fault'));

        $this->expectException(\RuntimeException::class);
        $this->updater->applyReference(VnSchemes::VN_ADMIN_PRE_2025);
    }
}
