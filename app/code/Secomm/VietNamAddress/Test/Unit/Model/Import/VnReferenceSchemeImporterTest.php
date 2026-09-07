<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Import\SchemeRegistryUpdater;
use Secomm\VietNamAddress\Model\Import\UnitSnapshotWriter;
use Secomm\VietNamAddress\Model\Import\VnDatasetReader;
use Secomm\VietNamAddress\Model\Import\VnDatasetValidator;
use Secomm\VietNamAddress\Model\Import\VnImportValidationException;
use Secomm\VietNamAddress\Model\Import\VnReferenceSchemeImporter;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-F9XJ5G — reference-only import orchestrator (Cases 1/2/3/5): validate → unit
 * snapshot → registry in ONE transaction; a validation failure writes nothing; there is
 * no purge/re-key/config path at all — the runtime collaborators are absent from the
 * constructor, and no DELETE ever runs through the connection.
 */
class VnReferenceSchemeImporterTest extends TestCase
{
    private const DATASET = [
        'regions' => [
            ['line' => 2, 'region_code' => 'VN-01', 'name_vi' => 'An Giang', 'name_en' => 'An Giang'],
        ],
        'units' => [
            ['line' => 2, 'region_code' => 'VN-01', 'code' => 'VNAP25-0000000001', 'parent_code' => '', 'name_vi' => 'Thị xã Tân Châu', 'name_en' => 'Tan Chau Town'],
        ],
        'header' => 'region_code,region_name_vi,region_name_en,code,parent_code,name_vi,name_en',
    ];

    private Mysql&MockObject $adapter;
    private VnDatasetReader&MockObject $reader;
    private VnDatasetValidator&MockObject $validator;
    private UnitSnapshotWriter&MockObject $unitSnapshotWriter;
    private SchemeRegistryUpdater&MockObject $registryUpdater;

    /** @var array<int, string> transaction call sequence (begin/commit/rollback) */
    private array $transactionCalls = [];
    private int $deleteCalls = 0;

    private VnReferenceSchemeImporter $importer;

    protected function setUp(): void
    {
        $this->transactionCalls = [];
        $this->deleteCalls = 0;

        $this->adapter = $this->createMock(Mysql::class);
        $this->adapter->method('beginTransaction')->willReturnCallback(
            function (): void {
                $this->transactionCalls[] = 'begin';
            }
        );
        $this->adapter->method('commit')->willReturnCallback(
            function (): void {
                $this->transactionCalls[] = 'commit';
            }
        );
        $this->adapter->method('rollBack')->willReturnCallback(
            function (): void {
                $this->transactionCalls[] = 'rollback';
            }
        );
        $this->adapter->method('delete')->willReturnCallback(
            function (): int {
                $this->deleteCalls++;

                return 1;
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);

        $this->reader = $this->createMock(VnDatasetReader::class);
        $this->validator = $this->createMock(VnDatasetValidator::class);
        $this->unitSnapshotWriter = $this->createMock(UnitSnapshotWriter::class);
        $this->registryUpdater = $this->createMock(SchemeRegistryUpdater::class);

        $this->importer = new VnReferenceSchemeImporter(
            $resource,
            $this->reader,
            $this->validator,
            $this->unitSnapshotWriter,
            $this->registryUpdater
        );
    }

    /**
     * Case 1 — reference import of a historical scheme: snapshot + registry written inside
     * one committed transaction, report flags reference-only, no delete ever runs.
     */
    public function testImportWritesSnapshotAndRegistryInOneTransaction(): void
    {
        $this->adapter->expects($this->never())->method('delete');
        $this->reader->method('read')->willReturn(self::DATASET);
        $this->validator->method('validate')->willReturn([]);
        $this->unitSnapshotWriter->expects($this->once())->method('write')->with(
            VnSchemes::VN_ADMIN_PRE_2025,
            self::DATASET['regions'],
            self::DATASET['units']
        )->willReturn(11357);
        $this->registryUpdater->expects($this->once())->method('applyReference')->with(
            VnSchemes::VN_ADMIN_PRE_2025
        )->willReturn(VnSchemes::STATUS_HISTORICAL);

        $report = $this->importer->import(VnSchemes::VN_ADMIN_PRE_2025);

        $this->assertTrue($report->referenceOnly);
        $this->assertFalse($report->dryRun);
        $this->assertSame(VnSchemes::VN_ADMIN_PRE_2025, $report->scheme);
        $this->assertSame(1, $report->regionRowsValidated);
        $this->assertSame(1, $report->unitRowsValidated);
        $this->assertSame(11357, $report->unitsSnapshoted);
        $this->assertSame(VnSchemes::STATUS_HISTORICAL, $report->registryStatus);
        $this->assertSame(['begin', 'commit'], $this->transactionCalls);
    }

    /**
     * Case 2 — repeat import: same upsert behaviour, still no delete, transaction per run.
     */
    public function testRepeatImportIsIdempotentAndNeverDeletes(): void
    {
        $this->adapter->expects($this->never())->method('delete');
        $this->reader->method('read')->willReturn(self::DATASET);
        $this->validator->method('validate')->willReturn([]);
        $this->unitSnapshotWriter->method('write')->willReturn(11357);
        $this->registryUpdater->method('applyReference')->willReturn(VnSchemes::STATUS_HISTORICAL);

        $first = $this->importer->import(VnSchemes::VN_ADMIN_PRE_2025);
        $second = $this->importer->import(VnSchemes::VN_ADMIN_PRE_2025);

        $this->assertSame($first->unitsSnapshoted, $second->unitsSnapshoted);
        $this->assertSame($first->registryStatus, $second->registryStatus);
        $this->assertSame(0, $this->deleteCalls);
        $this->assertSame(['begin', 'commit', 'begin', 'commit'], $this->transactionCalls);
    }

    /**
     * Case 3 — dataset validation failure: nothing written (STOP_ON_ERROR before any
     * transaction), no snapshot, no registry call, no runtime side effect.
     */
    public function testValidationFailureWritesNothing(): void
    {
        $this->reader->method('read')->willReturn(self::DATASET);
        $this->validator->method('validate')->willReturn(['Line 3: empty code.']);
        $this->unitSnapshotWriter->expects($this->never())->method('write');
        $this->registryUpdater->expects($this->never())->method('applyReference');

        try {
            $this->importer->import(VnSchemes::VN_ADMIN_PRE_2025);
            $this->fail('Expected VnImportValidationException.');
        } catch (VnImportValidationException $e) {
            $this->assertSame(['Line 3: empty code.'], $e->getErrors());
        }

        $this->assertSame([], $this->transactionCalls);
        $this->assertSame(0, $this->deleteCalls);
    }

    /**
     * A snapshot fault rolls the whole transaction back — no partial snapshot, no registry row.
     */
    public function testSnapshotFailureRollsBackAndSkipsRegistry(): void
    {
        $this->reader->method('read')->willReturn(self::DATASET);
        $this->validator->method('validate')->willReturn([]);
        $this->unitSnapshotWriter->method('write')->willThrowException(new \RuntimeException('db fault'));
        $this->registryUpdater->expects($this->never())->method('applyReference');

        $this->expectException(\RuntimeException::class);
        try {
            $this->importer->import(VnSchemes::VN_ADMIN_PRE_2025);
        } finally {
            $this->assertSame(['begin', 'rollback'], $this->transactionCalls);
        }
    }

    /**
     * Reader fault (missing/unreadable file): fails before any write.
     */
    public function testReaderFailurePropagatesWithoutWrites(): void
    {
        $this->reader->method('read')->willThrowException(
            new \Magento\Framework\Exception\LocalizedException(__('Dataset file not readable.'))
        );
        $this->unitSnapshotWriter->expects($this->never())->method('write');
        $this->registryUpdater->expects($this->never())->method('applyReference');

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->importer->import(VnSchemes::VN_ADMIN_PRE_2025);
        $this->assertSame([], $this->transactionCalls);
    }

    /**
     * --dry-run combination: validation only, no transaction, no writes.
     */
    public function testDryRunValidatesWithoutWrites(): void
    {
        $this->reader->method('read')->willReturn(self::DATASET);
        $this->validator->method('validate')->willReturn([]);
        $this->unitSnapshotWriter->expects($this->never())->method('write');
        $this->registryUpdater->expects($this->never())->method('applyReference');

        $report = $this->importer->dryRun(VnSchemes::VN_ADMIN_PRE_2025);

        $this->assertTrue($report->referenceOnly);
        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->regionRowsValidated);
        $this->assertSame(1, $report->unitRowsValidated);
        $this->assertSame([], $this->transactionCalls);
        $this->assertSame(0, $this->deleteCalls);
    }

    /**
     * Case 5 basis — an unknown scheme is rejected before any read/write.
     */
    public function testUnknownSchemeIsRejected(): void
    {
        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->importer->import('VN_ADMIN_2030');
    }
}
