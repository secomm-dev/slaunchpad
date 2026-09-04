<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Serialize;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\HierarchyAddressImportInterface;
use Secomm\AddressDropdown\Model\AddressProfileResolver;
use Secomm\AddressDropdown\Model\Import\Hierarchy\HierarchyImportResult;
use Secomm\AddressDropdown\Model\Import\Hierarchy\HierarchyImportValidationException;
use Secomm\VietNamAddress\Model\Import\CurrentDatasetRekeyMatcher;
use Secomm\VietNamAddress\Model\Import\SchemeRegistryUpdater;
use Secomm\VietNamAddress\Model\Import\UnitSnapshotWriter;
use Secomm\VietNamAddress\Model\Import\VnAddressSchemeImporter;
use Secomm\VietNamAddress\Model\Import\VnDatasetReader;
use Secomm\VietNamAddress\Model\Import\VnDatasetValidator;
use Secomm\VietNamAddress\Model\Import\VnImportValidationException;
use Secomm\VietNamAddress\Api\DirectoryReferenceGuardInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-003 — orchestrator behaviour: alias-aware scheme gate (--swap),
 * destructive purge + DI external-reference guards (DEC-FEATYA2C0W-004 D7), re-key bridge,
 * historical snapshot + registry hooks, membership reseed, config flip (active_scheme +
 * profile mapping), dry-run isolation and validation STOP_ON_ERROR.
 */
class VnAddressSchemeImporterTest extends TestCase
{
    private ResourceConnection&MockObject $resource;
    private Mysql&MockObject $adapter;
    private VnDatasetReader&MockObject $reader;
    private VnDatasetValidator&MockObject $validator;
    private CurrentDatasetRekeyMatcher&MockObject $matcher;
    private HierarchyAddressImportInterface&MockObject $hierarchyImport;
    private ConfigInterface&MockObject $configWriter;
    private Serialize&MockObject $serializer;
    private CacheManager&MockObject $cacheManager;
    private SchemeRegistryUpdater&MockObject $registryUpdater;
    private UnitSnapshotWriter&MockObject $unitSnapshotWriter;

    /** @var array<int, mixed> queued fetchCol results */
    private array $fetchColQueue = [];
    /** @var array<int, mixed> queued fetchOne results */
    private array $fetchOneQueue = [];
    /** @var array<int, mixed> queued fetchAll results */
    private array $fetchAllQueue = [];
    private int $deleteCalls = 0;
    private int $writeCalls = 0;
    /** @var array<int, array> captured hierarchy import rows */
    private array $hierarchyRows = [];
    /** @var array<int, array{table: string, data: array}> captured update() calls */
    private array $updateCalls = [];
    /** @var array<int, string> transaction call sequence (begin/commit/rollback) */
    private array $transactionCalls = [];

    private VnAddressSchemeImporter $importer;

    protected function setUp(): void
    {
        $this->resetRecording();

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('distinct')->willReturnSelf();

        $this->adapter = $this->createMock(Mysql::class);
        $this->adapter->method('select')->willReturn($select);
        $this->adapter->method('quoteInto')->willReturnCallback(
            static fn (string $text, mixed $value): string => 'q(' . $text . ')'
        );
        $this->adapter->method('fetchCol')->willReturnCallback(function () {
            return array_shift($this->fetchColQueue) ?? [];
        });
        $this->adapter->method('fetchOne')->willReturnCallback(function () {
            return array_shift($this->fetchOneQueue) ?? false;
        });
        $this->adapter->method('fetchAll')->willReturnCallback(function () {
            return array_shift($this->fetchAllQueue) ?? [];
        });
        $this->adapter->method('isTableExists')->willReturn(true);
        $this->adapter->method('delete')->willReturnCallback(function (): int {
            $this->deleteCalls++;

            return 1;
        });
        $this->adapter->method('insert')->willReturnCallback(function (): int {
            $this->writeCalls++;

            return 1;
        });
        $this->adapter->method('insertOnDuplicate')->willReturnCallback(function (): int {
            $this->writeCalls++;

            return 1;
        });
        $this->adapter->method('update')->willReturnCallback(function (string $table, array $data): int {
            $this->writeCalls++;
            $this->updateCalls[] = ['table' => $table, 'data' => $data];

            return 1;
        });
        $this->adapter->method('beginTransaction')->willReturnCallback(function (): void {
            $this->transactionCalls[] = 'begin';
        });
        $this->adapter->method('commit')->willReturnCallback(function (): void {
            $this->transactionCalls[] = 'commit';
        });
        $this->adapter->method('rollBack')->willReturnCallback(function (): void {
            $this->transactionCalls[] = 'rollback';
        });

        $resource = $this->createMock(ResourceConnection::class);
        $this->resource = $resource;
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => $name);

        $this->reader = $this->createMock(VnDatasetReader::class);
        $this->validator = $this->createMock(VnDatasetValidator::class);
        $this->matcher = $this->createMock(CurrentDatasetRekeyMatcher::class);
        $this->hierarchyImport = $this->createMock(HierarchyAddressImportInterface::class);
        $this->configWriter = $this->createMock(ConfigInterface::class);
        $this->serializer = $this->createMock(Serialize::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->registryUpdater = $this->createMock(SchemeRegistryUpdater::class);
        $this->unitSnapshotWriter = $this->createMock(UnitSnapshotWriter::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->serializer->method('unserialize')->willReturn(['VN' => 'vn_current']);
        $this->serializer->method('serialize')->willReturn('NEWSERIALIZED');

        $this->importer = $this->makeImporter([], $logger);
    }

    /**
     * TASK-Q4B98P: guards are injected (DEC-FEATYA2C0W-004 D7) — tests stub them as fakes.
     */
    private function makeImporter(array $guards, ?LoggerInterface $logger = null): VnAddressSchemeImporter
    {
        return new VnAddressSchemeImporter(
            $this->resource,
            $this->reader,
            $this->validator,
            $this->matcher,
            $this->hierarchyImport,
            $this->configWriter,
            $this->serializer,
            $this->cacheManager,
            $this->registryUpdater,
            $this->unitSnapshotWriter,
            $logger ?? $this->createMock(LoggerInterface::class),
            $guards
        );
    }

    private function guardStub(string $name, ?LocalizedException $throws = null): DirectoryReferenceGuardInterface
    {
        return new class($name, $throws) implements DirectoryReferenceGuardInterface {
            public function __construct(
                private readonly string $name,
                private readonly ?LocalizedException $throws
            ) {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function assertSafe(array $regionIds, array $cityIds): void
            {
                if ($this->throws !== null) {
                    throw $this->throws;
                }
            }
        };
    }

    private function resetRecording(): void
    {
        $this->fetchColQueue = [];
        $this->fetchOneQueue = [];
        $this->fetchAllQueue = [];
        $this->deleteCalls = 0;
        $this->writeCalls = 0;
        $this->hierarchyRows = [];
        $this->updateCalls = [];
        $this->transactionCalls = [];
    }

    // ------------------------------------------------------------------ scenarios

    public function testSameSchemeRefreshViaAliasRunsUpsertFlowAndHealsConfig(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_2025, []);
        $this->arrangeHierarchyResult(0, 1, 0, 2);
        $this->unitSnapshotWriter->method('write')->willReturn(3);
        // fetchCol: gate detect (alias), gate hasVnRegion, switchesScheme hasVnRegion + detect,
        // cleanup regions, cleanup stray regions, reseed regions, reseed cityIds
        $this->fetchColQueue = [['vn_current'], ['11'], ['11'], ['vn_current'], ['11'], [], ['11'], []];
        // fetchOne: stale COUNT, default-scope profile mapping raw
        $this->fetchOneQueue = ['0', 'SERIALIZED'];
        $this->fetchAllQueue = [[], []]; // re-key: nothing to re-key (regions, cities)

        $report = $this->importer->import(VnSchemes::VN_ADMIN_2025);

        $this->assertFalse($report->hasErrors());
        $this->assertFalse($report->swapPerformed);
        $this->assertSame(1, $report->regionRowsValidated);
        $this->assertSame(2, $report->unitRowsValidated);
        $this->assertSame(3, $report->unitsSnapshoted);
        $this->assertSame(1, $report->membershipRows);
        $this->assertTrue($report->configUpdated); // vn_current -> vn_admin_2025 healed
        // Orphan-claim sweep + membership reseed delete.
        $this->assertSame(2, $this->deleteCalls);
        // The whole write phase ran inside one committed transaction.
        $this->assertSame(['begin', 'commit'], $this->transactionCalls);

        // Snapshot + registry hooks ran between cleanup and membership.
        $this->unitSnapshotWriter->expects($this->any())->method('write');
        $this->registryUpdater->expects($this->any())->method('apply');

        // The generic import received synthesized region + city rows.
        $this->assertNotEmpty($this->hierarchyRows);
        $this->assertSame('region', $this->hierarchyRows[0]['entity_type']);
        $this->assertSame('VN-32', $this->hierarchyRows[0]['code']);
        $this->assertSame('city', $this->hierarchyRows[1]['entity_type']);
        $this->assertSame('VNA25-3D6A6CF4D0', $this->hierarchyRows[1]['code']);
    }

    public function testSchemeSwitchWithoutSwapFlagIsRefused(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_PRE_2025, []);
        $this->fetchColQueue = [['vn_current'], ['11']];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('--swap');
        $this->importer->import(VnSchemes::VN_ADMIN_PRE_2025);

        $this->assertSame(0, $this->deleteCalls);
        $this->assertSame(0, $this->writeCalls);
    }

    public function testSwapPurgesThenImportsAndFlipsBothConfigs(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_PRE_2025, []);
        $this->arrangeHierarchyResult(63, 0, 11294, 0);
        // fetchCol: gate detect, gate hasVnRegion, swap-if hasVnRegion, swap-if detect,
        // purge regions, purge cityIds, cleanup regions (none left), reseed regions, reseed cityIds
        $this->fetchColQueue = [
            ['vn_current'], ['11'], ['11'], ['vn_current'], ['11'], ['55'], [], ['201'], [],
        ];
        // fetchOne: guards are DI fakes now (no COUNT here) — profile mapping raw (absent).
        $this->fetchOneQueue = [false];

        $captured = [];
        $this->configWriter->method('saveConfig')->willReturnCallback(
            function (string $path, string $value, string $scope, int $scopeId) use (&$captured): void {
                $captured[] = [$path, $value, $scope, $scopeId];
            }
        );

        $report = $this->importer->import(VnSchemes::VN_ADMIN_PRE_2025, true);

        $this->assertTrue($report->swapPerformed);
        $this->assertSame(1, $report->purgedRegions);
        $this->assertSame(1, $report->purgedCities);
        $this->assertSame(63, $report->regionsInserted);
        $this->assertTrue($report->configUpdated);
        // Purge (2 deletes: membership + regions) + orphan sweep (1) + membership reseed (1).
        $this->assertSame(4, $this->deleteCalls);

        $paths = array_map(static fn (array $call): string => $call[0], $captured);
        $this->assertContains(AddressProfileResolver::XML_PATH_PROFILE_MAPPING, $paths);
        $this->assertContains(VnSchemes::XML_PATH_ACTIVE_SCHEME, $paths);
    }

    public function testSwapAbortsWhenExternalReferenceGuardBlocks(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_PRE_2025, []);
        $this->fetchColQueue = [['vn_current'], ['11'], ['11'], ['vn_current'], ['11'], ['55']];

        $importer = $this->makeImporter([
            $this->guardStub(
                'Secomm_Ghtk (secomm_ghtk_address_map)',
                new LocalizedException(
                    __('7 row(s) in secomm_ghtk_address_map (column ward_id) still reference the installed VN data. '
                        . 'Clear that carrier mapping data first.')
                )
            ),
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cannot swap: [Secomm_Ghtk (secomm_ghtk_address_map)] 7 row(s)');
        $importer->import(VnSchemes::VN_ADMIN_PRE_2025, true);

        $this->assertSame(0, $this->deleteCalls); // aborted before any delete
    }

    public function testSwapAggregatesViolationsFromEveryGuard(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_PRE_2025, []);
        $this->fetchColQueue = [['vn_current'], ['11'], ['11'], ['vn_current'], ['11'], ['55']];

        $importer = $this->makeImporter([
            $this->guardStub(
                'Secomm_GhnAddressMapper (secomm_ghn_address_mapping_location)',
                new LocalizedException(
                    __('2 row(s) in secomm_ghn_address_mapping_location (column city_id) still reference the installed VN data. '
                        . 'Clear that carrier mapping data first.')
                )
            ),
            $this->guardStub(
                'Secomm_Ghtk (secomm_ghtk_address_map)',
                new LocalizedException(
                    __('7 row(s) in secomm_ghtk_address_map (column ward_id) still reference the installed VN data. '
                        . 'Clear that carrier mapping data first.')
                )
            ),
        ]);

        try {
            $importer->import(VnSchemes::VN_ADMIN_PRE_2025, true);
            $this->fail('Expected the aggregated guard exception.');
        } catch (LocalizedException $e) {
            // ONE exception names EVERY blocking table (no first-violation short circuit).
            $this->assertStringContainsString('secomm_ghn_address_mapping_location', $e->getMessage());
            $this->assertStringContainsString('secomm_ghtk_address_map', $e->getMessage());
            $this->assertStringStartsWith('Cannot swap: [', $e->getMessage());
        }

        $this->assertSame(0, $this->deleteCalls);
    }

    public function testInvalidGuardEntryIsSkippedWithWarning(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_PRE_2025, []);
        $this->arrangeHierarchyResult(63, 0, 11294, 0);
        // fetchCol: gate detect, gate hasVnRegion, swap-if hasVnRegion, swap-if detect,
        // purge regions, purge cityIds, cleanup regions (none left), reseed regions, reseed cityIds
        $this->fetchColQueue = [
            ['vn_current'], ['11'], ['11'], ['vn_current'], ['11'], ['55'], [], ['201'], [],
        ];
        $this->fetchOneQueue = [false];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')
            ->with('Secomm_VietNamAddress: skipping invalid directory reference guard entry.', self::anything());

        $importer = $this->makeImporter([new \stdClass(), $this->guardStub('Safe')], $logger);
        $report = $importer->import(VnSchemes::VN_ADMIN_PRE_2025, true);

        $this->assertTrue($report->swapPerformed);
    }

    public function testDryRunWritesNothingAndSimulatesRekey(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_2025, []);
        $this->fetchColQueue = [['vn_current'], ['11']];
        $this->fetchAllQueue = [
            // Region bridge: one legacy-format region (bare official code "01").
            [['region_id' => 5, 'code' => '01', 'default_name' => 'Hanoi City', 'vi_name' => 'Thành phố Hà Nội']],
            // City bridge: one legacy-format city under that region.
            [['city_id' => 77, 'default_name' => 'Hoan Kiem Ward', 'region_code' => '01', 'vi_name' => 'Phường Hoàn Kiếm']],
        ];
        $this->matcher->method('matchRegion')->willReturn('VN-11');
        $this->matcher->method('match')->willReturn('VNA25-3D6A6CF4D0');

        $report = $this->importer->dryRun(VnSchemes::VN_ADMIN_2025);

        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->rekeyRegionMatched);
        $this->assertSame(0, $report->rekeyRegionMissed);
        $this->assertSame(1, $report->rekeyMatched);
        $this->assertSame(0, $report->rekeyMissed);
        $this->assertSame(0, $this->deleteCalls);
        $this->assertSame(0, $this->writeCalls);
        $this->hierarchyImport->expects($this->never())->method('import');
        $this->cacheManager->expects($this->never())->method('clean');
        $this->unitSnapshotWriter->expects($this->never())->method('write');
        $this->registryUpdater->expects($this->never())->method('apply');
    }

    public function testRebuildPurgesRuntimeAndSchemeSnapshotThenImportsFresh(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_2025, []);
        $this->arrangeHierarchyResult(34, 0, 3321, 0);
        // fetchCol: gate detect, gate hasVnRegion, purge-if hasVnRegion, purge-if detect,
        // purge regions, purge cityIds, cleanup regions (none left), reseed regions, reseed cityIds
        $this->fetchColQueue = [
            ['vn_current'], ['11'], ['11'], ['vn_current'], ['11'], ['55'], [], ['11'], [],
        ];
        // fetchOne: guards are DI fakes now — profile mapping raw (cleanup early-returns on empty regions)
        $this->fetchOneQueue = ['SERIALIZED'];
        $this->fetchAllQueue = [[]]; // re-key skipped after rebuild

        $report = $this->importer->import(VnSchemes::VN_ADMIN_2025, false, true);

        $this->assertTrue($report->rebuildPerformed);
        $this->assertFalse($report->swapPerformed);
        $this->assertSame(1, $report->purgedRegions);
        $this->assertSame(1, $report->purgedCities);
        $this->assertSame(1, $report->unitsPurged); // mocked delete returns 1 per call; snapshot purge = 1 delete
        // Purge (2 deletes) + snapshot purge (1) + orphan sweep (1) + membership reseed (1) = 5 deletes.
        $this->assertSame(5, $this->deleteCalls);
    }

    public function testValidationFailureStopsBeforeAnyWork(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_2025, ['Line 5: boom', 'Line 6: also boom']);

        try {
            $this->importer->import(VnSchemes::VN_ADMIN_2025);
            $this->fail('Expected VnImportValidationException');
        } catch (VnImportValidationException $e) {
            $this->assertSame(['Line 5: boom', 'Line 6: also boom'], $e->getErrors());
            // The message embeds the actual reasons (setup:upgrade only shows the message).
            $this->assertStringContainsString('failed validation with 2 error(s)', $e->getMessage());
            $this->assertStringContainsString('- Line 5: boom', $e->getMessage());
            $this->assertStringContainsString('- Line 6: also boom', $e->getMessage());
        }

        $this->assertSame(0, $this->deleteCalls);
        $this->assertSame(0, $this->writeCalls);
        $this->assertSame([], $this->transactionCalls); // validation precedes the transaction
    }

    public function testRegionBridgeRekeysLegacyRegionsBeforeCityBridge(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_2025, []);
        $this->arrangeHierarchyResult(0, 1, 0, 2);
        // fetchCol: gate detect, gate has, switchesScheme has+detect, cleanup regions,
        // cleanup stray regions, reseed regions, reseed cityIds
        $this->fetchColQueue = [['vn_admin_2025'], ['11'], ['11'], ['vn_admin_2025'], ['11'], [], ['11'], []];
        $this->fetchOneQueue = ['0', 'SERIALIZED'];
        $this->fetchAllQueue = [
            // Region bridge: legacy region with a bare official code ("01" = Hanoi) and
            // type-worded names.
            [['region_id' => 5, 'code' => '01', 'default_name' => 'Hanoi City', 'vi_name' => 'Thành phố Hà Nội']],
            // City bridge: legacy city under that region.
            [['city_id' => 77, 'default_name' => 'Hoan Kiem Ward', 'region_code' => '01', 'vi_name' => 'Phường Hoàn Kiếm']],
        ];
        $this->matcher->method('matchRegion')->willReturn('VN-11');
        $this->matcher->method('match')->willReturn('VNA25-3D6A6CF4D0');

        $report = $this->importer->import(VnSchemes::VN_ADMIN_2025);

        $this->assertSame(1, $report->rekeyRegionMatched);
        $this->assertSame(0, $report->rekeyRegionMissed);
        $this->assertSame(1, $report->rekeyMatched);
        // The region code assignment happens BEFORE the city code assignment, so the city
        // bridge joins dataset region codes.
        $this->assertCount(2, $this->updateCalls);
        $this->assertSame('directory_country_region', $this->updateCalls[0]['table']);
        $this->assertSame(['code' => 'VN-11'], $this->updateCalls[0]['data']);
        $this->assertSame('directory_region_city', $this->updateCalls[1]['table']);
        $this->assertSame(['code' => 'VNA25-3D6A6CF4D0'], $this->updateCalls[1]['data']);
    }

    public function testBootstrapFromEmptyDatabaseSeedsRegionsThroughHierarchyImport(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_2025, []);
        $this->arrangeHierarchyResult(34, 0, 3321, 0);
        // Every DB probe comes back empty: no installed scheme, no VN regions, no cities.
        $this->fetchColQueue = [[], [], [], [], [], []];
        $this->fetchOneQueue = [false];
        $this->fetchAllQueue = [[], []];

        $report = $this->importer->import(VnSchemes::VN_ADMIN_2025);

        $this->assertFalse($report->hasErrors());
        $this->assertFalse($report->swapPerformed);
        $this->assertFalse($report->rebuildPerformed);
        // The dataset itself seeded everything through the generic hierarchy import.
        $this->assertSame(34, $report->regionsInserted);
        $this->assertSame(3321, $report->citiesInserted);
        $this->assertSame(0, $report->rekeyRegionMatched);
        $this->assertSame(0, $report->rekeyMatched);
        $this->assertSame(0, $report->purgedRegions);
        // Region rows travel inside the import batch — no pre-existing DB regions required.
        $regionBatch = array_values(array_filter(
            $this->hierarchyRows,
            static fn (array $row): bool => $row['entity_type'] === 'region'
        ));
        $this->assertNotEmpty($regionBatch);
        $this->assertSame('VN-32', $regionBatch[0]['code']);
    }

    public function testHierarchyImportFailureRollsBackTheWholeWritePhase(): void
    {
        $this->arrangeDataset(VnSchemes::VN_ADMIN_2025, []);
        $this->hierarchyImport->method('import')->willThrowException(
            new HierarchyImportValidationException(__('mock failure'), new HierarchyImportResult())
        );
        $this->fetchColQueue = [[], [], []];
        $this->fetchAllQueue = [[], []];

        try {
            $this->importer->import(VnSchemes::VN_ADMIN_2025);
            $this->fail('Expected VnImportValidationException');
        } catch (VnImportValidationException $e) {
            $this->assertStringContainsString('Hierarchy import validation failed', $e->getMessage());
        }

        // Outer transaction rolled back; nothing committed, no downstream work ran.
        $this->assertSame(['begin', 'rollback'], $this->transactionCalls);
        $this->assertSame(0, $this->deleteCalls);
        $this->cacheManager->expects($this->never())->method('clean');
        $this->unitSnapshotWriter->expects($this->never())->method('write');
    }

    // ------------------------------------------------------------------ arrangement helpers

    private function arrangeDataset(string $scheme, array $validationErrors): void
    {
        $dataset = [
            'regions' => [
                ['line' => 2, 'region_code' => 'VN-32', 'name_vi' => 'An Giang', 'name_en' => 'An Giang'],
            ],
            'units' => [
                ['line' => 3, 'region_code' => 'VN-32', 'code' => 'VNA25-3D6A6CF4D0', 'parent_code' => '', 'name_vi' => 'An Biên', 'name_en' => 'An Bien'],
                ['line' => 4, 'region_code' => 'VN-89', 'code' => 'VNAP25-B70EDA95D6', 'parent_code' => '', 'name_vi' => 'An Phú', 'name_en' => 'An Phu'],
            ],
            'header' => 'region_code,region_name_vi,region_name_en,code,parent_code,name_vi,name_en',
        ];
        $this->reader->method('read')->with($scheme)->willReturn($dataset);
        $this->validator->method('validate')->with($scheme, $dataset['regions'], $dataset['units'])->willReturn($validationErrors);
    }

    private function arrangeHierarchyResult(int $regionsIns, int $regionsUpd, int $citiesIns, int $citiesUpd): void
    {
        $result = new HierarchyImportResult();
        $result->setRowsValidated(3);
        for ($i = 0; $i < $regionsIns; $i++) {
            $result->countRegionInserted();
        }
        for ($i = 0; $i < $regionsUpd; $i++) {
            $result->countRegionUpdated();
        }
        for ($i = 0; $i < $citiesIns; $i++) {
            $result->countCityInserted();
        }
        for ($i = 0; $i < $citiesUpd; $i++) {
            $result->countCityUpdated();
        }

        $this->hierarchyImport->method('import')->willReturnCallback(
            function (string $countryId, array $rows) use ($result): HierarchyImportResult {
                $this->hierarchyRows = $rows;

                return $result;
            }
        );
    }
}
