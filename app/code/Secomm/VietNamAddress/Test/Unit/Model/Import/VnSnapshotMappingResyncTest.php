<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Framework\DB\Select;
use Secomm\VietNamAddress\Model\Import\VnMappingImporter;
use Secomm\VietNamAddress\Model\Import\VnSnapshotMappingResync;

/**
 * TASK-SEC-1.2/1.3 — EXACT snapshot resync. Proves convergence semantics against a simulated
 * old-baseline DB (via statement-level assertions, since the logic is plain SQL statements):
 * upsert of the full snapshot, deletion LIMITED to the recorded bundled-ownership keyset,
 * custom edges untouched, content-identity checksums enforced loudly, and repeat-run
 * idempotence (the same bounded statements, which are no-ops once the DB has converged).
 */
class VnSnapshotMappingResyncTest extends TestCase
{
    private const FILES_DIR = __DIR__ . '/../../../../Files/';

    private VnMappingImporter|MockObject $importer;

    private ComponentRegistrarInterface&MockObject $registrar;

    private ResourceConnection&MockObject $resourceConnection;

    private AdapterInterface&MockObject $connection;

    /** @var array<int, array<string, mixed>> captured delete conditions */
    private array $deleteCalls = [];

    /** @var array<int, array<string, mixed>|false> per-fetchRow queued current-row contents */
    private array $stubRows = [];

    /** @var array<int, array<string, mixed>> captured select conditions */
    private array $fetchRowCalls = [];

    /** @var array<int, array<string, mixed>> captured select conditions per built select */
    private array $selects = [];

    private int $fetchIndex = 0;

    private function service(?string $filesDir = null): VnSnapshotMappingResync
    {
        $this->importer = $this->createMock(VnMappingImporter::class);
        $this->importer->method('import')->willReturn(['edges' => 10418]);
        $this->registrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->registrar->method('getPath')->willReturn($filesDir ?? dirname(self::FILES_DIR));
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnArgument(0);
        $this->connection->method('delete')->willReturnCallback(
            function (string $table, array $where): int {
                $this->deleteCalls[] = $where;

                return 1;
            }
        );
        // Old-value guard stubs: default DB rows carry the exact bundled old value
        // (MERGED_INTO / is_primary 0) → all manifest keys are deletable unless queued.
        $this->connection->method('select')->willReturnCallback(
            function (): Select {
                $conds = [];
                $select = $this->createMock(Select::class);
                $select->method('from')->willReturnSelf();
                $select->method('where')->willReturnCallback(
                    function (string $cond, mixed $value) use ($select, &$conds): Select {
                        $conds[$cond] = $value;

                        return $select;
                    }
                );
                $this->selects[] = $conds;

                return $select;
            }
        );
        // The service queries manifest keys in manifest order — serve each queried key its own
        // OLD bundled value (a queued stubRow wins first, for per-test conflict fixtures).
        $this->connection->method('fetchRow')->willReturnCallback(
            function (): array|false {
                $this->fetchRowCalls[] = array_pop($this->selects) ?? [];
                if ($this->stubRows !== []) {
                    return (array) array_shift($this->stubRows);
                }

                $manifest = json_decode(
                    (string) file_get_contents(self::FILES_DIR . 'VN_ADMIN_PRE_2025_SNAPSHOT_2024_mapping_manifest.json'),
                    true
                );
                $key = $manifest['removed_keys'][$this->fetchIndex++] ?? null;

                return $key === null
                    ? ['relation_type' => 'MERGED_INTO', 'is_primary' => 0]
                    : ['relation_type' => $key['relation_type'], 'is_primary' => $key['old_is_primary']];
            }
        );

        return new VnSnapshotMappingResync($this->importer, $this->registrar, $this->resourceConnection);
    }

    public function testUpsertsFullSnapshotThenRemovesExactlyTheRecordedStaleKeyset(): void
    {
        $service = $this->service();
        $this->connection->expects($this->exactly(41))->method('delete');
        $this->importer->expects($this->once())->method('import')->with(
            $this->stringEndsWith('Files/VN_ADMIN_PRE_2025_TO_2025_SNAPSHOT_2024_mapping.csv'),
            false
        );

        $report = $service->sync();

        $this->assertSame(10418, $report['upserted_snapshot_edges']);
        $this->assertSame(41, $report['removed_stale_keys']);
        // Every removal is bounded to the exact bundled scheme pair and the exact key.
        foreach ($this->deleteCalls as $where) {
            $this->assertSame('VN_ADMIN_PRE_2025', $where['source_scheme = ?']);
            $this->assertSame('VN_ADMIN_2025', $where['target_scheme = ?']);
            $this->assertArrayHasKey('source_code = ?', $where);
            $this->assertArrayHasKey('target_code = ?', $where);
        }
        // Spot-check one known removed key belongs to the recorded keyset, not merchant space.
        $manifest = json_decode(
            (string) file_get_contents(self::FILES_DIR . 'VN_ADMIN_PRE_2025_SNAPSHOT_2024_mapping_manifest.json'),
            true
        );
        $first = $manifest['removed_keys'][0];
        $expectedWhere = [
            'source_scheme = ?' => 'VN_ADMIN_PRE_2025',
            'source_code = ?' => $first['source_code'],
            'target_scheme = ?' => 'VN_ADMIN_2025',
            'target_code = ?' => $first['target_code'],
        ];
        $this->assertContains($expectedWhere, $this->deleteCalls);
    }

    public function testSecondRunConvergesWithTheSameBoundedStatements(): void
    {
        $service = $this->service();
        $service->sync();
        $firstRunDeletes = $this->deleteCalls;
        $this->deleteCalls = [];
        $this->fetchIndex = 0; // start the second run with a clean capture

        $report = $service->sync();

        // Same bounded statements (idempotent against a converged DB — each delete now
        // matches 0 rows); no NEW keys, no growth, no scheme-wide wipe.
        $this->assertSame($firstRunDeletes, $this->deleteCalls);
        $this->assertSame(41, $report['removed_stale_keys']);
    }

    public function testCustomEdgesAreNeverInDeletionScope(): void
    {
        // The deletion scope is exactly the manifest keyset — a merchant edge whose key is
        // outside both bundled datasets cannot appear in it by construction.
        $manifest = json_decode(
            (string) file_get_contents(self::FILES_DIR . 'VN_ADMIN_PRE_2025_SNAPSHOT_2024_mapping_manifest.json'),
            true
        );
        $service = $this->service();
        $service->sync();

        // The service walks the manifest in order and deletes one bounded condition set per
        // key: scope == exactly the recorded keyset (no other key can ever be targeted).
        $this->assertCount(count($manifest['removed_keys']), $this->deleteCalls);
        foreach ($this->deleteCalls as $i => $where) {
            $expected = $manifest['removed_keys'][$i];
            $this->assertSame($expected['source_code'], $where['source_code = ?']);
            $this->assertSame($expected['target_code'], $where['target_code = ?']);
        }
    }

    public function testChecksumMismatchFailsLoudlyBeforeAnyWrite(): void
    {
        // Tampered manifest: the recorded snapshot sha256 no longer matches the bundled file.
        $tmpDir = sys_get_temp_dir() . '/vn_resync_' . uniqid('', true);
        mkdir($tmpDir . '/Files', 0777, true);
        copy(self::FILES_DIR . 'VN_ADMIN_PRE_2025_TO_2025_SNAPSHOT_2024_mapping.csv', $tmpDir . '/Files/VN_ADMIN_PRE_2025_TO_2025_SNAPSHOT_2024_mapping.csv');
        $manifest = json_decode(
            (string) file_get_contents(self::FILES_DIR . 'VN_ADMIN_PRE_2025_SNAPSHOT_2024_mapping_manifest.json'),
            true
        );
        $manifest['snapshot_sha256'] = str_repeat('0', 64);
        file_put_contents($tmpDir . '/Files/VN_ADMIN_PRE_2025_SNAPSHOT_2024_mapping_manifest.json', json_encode($manifest));

        $importer = $this->createMock(VnMappingImporter::class);
        $importer->expects($this->never())->method('import');
        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPath')->willReturn($tmpDir);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->createMock(AdapterInterface::class));

        try {
            (new VnSnapshotMappingResync($importer, $registrar, $resource))->sync();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('checksum mismatch', $exception->getMessage());
            $this->assertStringContainsString(str_repeat('0', 64), $exception->getMessage());
        } finally {
            @unlink($tmpDir . '/Files/VN_ADMIN_PRE_2025_SNAPSHOT_2024_mapping_manifest.json');
            @rmdir($tmpDir . '/Files');
            @rmdir($tmpDir);
        }
    }

    public function testDryRunEquivalenceImportFailsBeforeAnyDeletion(): void
    {
        // Loud-failure ordering: a broken dataset aborts the upsert BEFORE any delete runs —
        // a failed migration can never leave a half-resynced reference layer.
        $service = $this->service();
        $this->importer->method('import')->willThrowException(
            new \Magento\Framework\Exception\LocalizedException(__('Mapping validation failed.'))
        );
        $this->connection->expects($this->never())->method('delete');

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $service->sync();
    }
}
