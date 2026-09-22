<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Import\HierarchyParentBackfill;

/**
 * BUG-ZTGGYZ (U1) — existing-DB repair: backfill parent_code = region_code for level-2
 * units with NULL parent, validated against level-1 region units of the same scheme.
 * Idempotent by construction (only NULL parents are touched); orphans are reported, never guessed.
 */
class HierarchyParentBackfillTest extends TestCase
{
    private Mysql&MockObject $adapter;

    /** @var array<int, array{sql: string, bind: array}> */
    private array $executedStatements;

    /** @var array<int, mixed> queued fetchOne results (remainingNull, orphan) */
    private array $fetchOneQueue;

    private HierarchyParentBackfill $backfill;

    protected function setUp(): void
    {
        $this->executedStatements = [];
        $this->fetchOneQueue = [];

        $this->adapter = $this->createMock(Mysql::class);
        $this->adapter->method('query')->willReturnCallback(
            function (string $sql, array $bind = []) {
                $this->executedStatements[] = ['sql' => $sql, 'bind' => $bind];

                return new class {
                    public function rowCount(): int
                    {
                        return 3321;
                    }
                };
            }
        );
        $this->adapter->method('fetchOne')->willReturnCallback(
            function (): mixed {
                return array_shift($this->fetchOneQueue) ?? 0;
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => $name);

        $this->backfill = new HierarchyParentBackfill($resource);
    }

    public function testBackfillUpdatesOnlyNullParentLevel2RowsValidatedAgainstRegionUnits(): void
    {
        $this->fetchOneQueue = [0, 0]; // remainingNull, orphan after the update

        $report = $this->backfill->backfill('VN_ADMIN_2025');

        $this->assertCount(1, $this->executedStatements);
        $statement = $this->executedStatements[0];
        $this->assertStringContainsString('SET u.parent_code = u.region_code', $statement['sql']);
        $this->assertStringContainsString('u.level = 2', $statement['sql']);
        $this->assertStringContainsString('u.parent_code IS NULL', $statement['sql']);
        $this->assertStringContainsString('r.level = 1', $statement['sql'], 'region unit must validate the target');
        $this->assertSame(['VN_ADMIN_2025'], $statement['bind']);
        $this->assertSame(['backfilled' => 3321, 'remainingNull' => 0, 'orphan' => 0], $report);
    }

    public function testRemainingNullsAreReportedAsFailureSignal(): void
    {
        $this->fetchOneQueue = [3, 3]; // 3 rows left NULL, all of them orphans

        $report = $this->backfill->backfill('VN_ADMIN_PRE_2025');

        $this->assertSame(3, $report['remainingNull']);
        $this->assertSame(3, $report['orphan'], 'orphans are counted, never silently guessed');
    }

    public function testUnknownSchemeIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->backfill->backfill('VN_ADMIN_2050');
    }
}
