<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Test\Unit\Model\Import;

use PHPUnit\Framework\TestCase;
use Secomm\GhnAddressMapper\Api\Data\LocationMappingInterface;
use Secomm\GhnAddressMapper\Api\Data\LocationMappingInterfaceFactory;
use Secomm\GhnAddressMapper\Api\LocationMappingRepositoryInterface;
use Secomm\GhnAddressMapper\Model\Import\MappingImporter;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping as LocationMappingResource;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
class MappingImporterTest extends TestCase
{
    private LocationMappingInterfaceFactory|\PHPUnit\Framework\MockObject\MockObject $mappingFactory;
    private LocationMappingRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject $repository;
    private CacheInterface|\PHPUnit\Framework\MockObject\MockObject $cache;
    private LoggerInterface|\PHPUnit\Framework\MockObject\MockObject $logger;
    private LocationMappingResource|\PHPUnit\Framework\MockObject\MockObject $locationMappingResource;
    private ResourceConnection|\PHPUnit\Framework\MockObject\MockObject $resourceConnection;
    private MappingImporter $importer;

    protected function setUp(): void
    {
        $this->mappingFactory = $this->createMock(LocationMappingInterfaceFactory::class);
        $this->repository = $this->createMock(LocationMappingRepositoryInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->locationMappingResource = $this->createMock(LocationMappingResource::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);

        $this->importer = new MappingImporter(
            $this->mappingFactory,
            $this->repository,
            $this->cache,
            $this->logger,
            $this->locationMappingResource,
            $this->resourceConnection
        );
    }

    /**
     * Helper to build valid CSV data with one row.
     */
    private function buildCsvData(array $overrides = []): array
    {
        $defaults = ['country_id', 'region_id', 'city_id', 'ghn_province_id', 'ghn_district_id', 'ghn_ward_code'];
        $header = $defaults;
        $row = ['VN', '1185', '1', '202', '1442', '20110'];
        foreach ($overrides as $col => $val) {
            $idx = array_search($col, $defaults, true);
            if ($idx !== false) {
                $row[$idx] = $val;
            }
        }
        return array_merge([$header], [$row]);
    }

    /**
     * Configure the resourceConnection mock for transaction support.
     */
    private function setupTransactionMock(AdapterInterface $adapter): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($adapter);
    }

    public function testHappyPathInsert(): void
    {
        $csvData = $this->buildCsvData();
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('beginTransaction');
        $adapter->expects($this->once())->method('commit');
        $adapter->expects($this->never())->method('rollBack');
        $this->setupTransactionMock($adapter);

        // No existing mapping
        $this->repository->method('findByMapping')->willReturn(null);

        // Bulk name maps return empty (processRow uses null fallback)
        $this->locationMappingResource->method('getRegionNameMap')->willReturn([]);
        $this->locationMappingResource->method('getCityNameMap')->willReturn([]);
        $this->locationMappingResource->method('getProvinceNameMap')->willReturn([]);
        $this->locationMappingResource->method('getDistrictNameMap')->willReturn([]);
        $this->locationMappingResource->method('getWardNameMap')->willReturn([]);

        // Factory creates a new mapping
        $newMapping = $this->createMock(LocationMappingInterface::class);
        $newMapping->expects($this->once())->method('setCountryId')->with('VN');
        $newMapping->expects($this->once())->method('setRegionId')->with(1185);
        $newMapping->expects($this->once())->method('setCityId')->with(1);
        $newMapping->expects($this->once())->method('setGhnProvinceId')->with(202);
        $newMapping->expects($this->once())->method('setGhnDistrictId')->with(1442);
        $newMapping->expects($this->once())->method('setGhnWardCode')->with('20110');
        $newMapping->expects($this->once())->method('setStatus')->with(1);
        $newMapping->expects($this->once())->method('setPriority')->with(0);
        $newMapping->expects($this->once())->method('setRegionName');
        $newMapping->expects($this->once())->method('setCityName');
        $newMapping->expects($this->once())->method('setGhnProvinceName');
        $newMapping->expects($this->once())->method('setGhnDistrictName');
        $newMapping->expects($this->once())->method('setGhnWardName');
        $this->mappingFactory->method('create')->willReturn($newMapping);

        // save() called with cleanCache=false
        $this->repository->expects($this->once())->method('save')->with($newMapping, false);

        // Cache cleaned once after commit
        $this->cache->expects($this->once())->method('clean')->with(['secomm_ghn_address_mapper']);

        $result = $this->importer->importAll($csvData, false);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['failed']);
        $this->assertEmpty($result['errors']);
    }

    public function testUpdateExistingTrueUpdates(): void
    {
        $csvData = $this->buildCsvData();
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('beginTransaction');
        $adapter->expects($this->once())->method('commit');
        $this->setupTransactionMock($adapter);

        $this->locationMappingResource->method('getRegionNameMap')->willReturn([1185 => 'Hồ Chí Minh']);
        $this->locationMappingResource->method('getCityNameMap')->willReturn([1 => 'Quận 1']);
        $this->locationMappingResource->method('getProvinceNameMap')->willReturn([202 => 'TP. Hồ Chí Minh']);
        $this->locationMappingResource->method('getDistrictNameMap')->willReturn([1442 => 'Quận 1']);
        $this->locationMappingResource->method('getWardNameMap')->willReturn(['20110' => 'Bến Nghé']);

        $existing = $this->createMock(LocationMappingInterface::class);
        $existing->method('getRegionId')->willReturn(1185);
        $existing->expects($this->once())->method('setCountryId')->with('VN');
        $existing->expects($this->once())->method('setGhnProvinceId')->with(202);
        $existing->expects($this->once())->method('setGhnDistrictId')->with(1442);
        $existing->expects($this->once())->method('setGhnWardCode')->with('20110');
        $existing->expects($this->once())->method('setPriority')->with(0);
        $existing->expects($this->once())->method('setRegionName')->with('Hồ Chí Minh');
        $existing->expects($this->once())->method('setCityName')->with('Quận 1');
        $existing->expects($this->once())->method('setGhnProvinceName')->with('TP. Hồ Chí Minh');
        $existing->expects($this->once())->method('setGhnDistrictName')->with('Quận 1');
        $existing->expects($this->once())->method('setGhnWardName')->with('Bến Nghé');
        $existing->expects($this->never())->method('setStatus'); // Status NOT changed on update
        $this->repository->method('findByMapping')->willReturn($existing);
        $this->repository->expects($this->once())->method('save')->with($existing, false);

        $this->cache->expects($this->once())->method('clean');

        $result = $this->importer->importAll($csvData, true);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['failed']);
    }

    public function testUpdateExistingFalseSkips(): void
    {
        $csvData = $this->buildCsvData();
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('beginTransaction');
        $adapter->expects($this->once())->method('commit');
        $this->setupTransactionMock($adapter);

        $this->locationMappingResource->method('getRegionNameMap')->willReturn([1185 => 'Hồ Chí Minh']);
        $this->locationMappingResource->method('getCityNameMap')->willReturn([1 => 'Quận 1']);
        $this->locationMappingResource->method('getProvinceNameMap')->willReturn([202 => 'TP. HCM']);
        $this->locationMappingResource->method('getDistrictNameMap')->willReturn([1442 => 'Q.1']);
        $this->locationMappingResource->method('getWardNameMap')->willReturn(['20110' => 'Bến Nghé']);

        $existing = $this->createMock(LocationMappingInterface::class);
        $existing->method('getRegionId')->willReturn(1185);
        $existing->expects($this->never())->method('setCountryId'); // No update
        $existing->expects($this->never())->method('setStatus');
        $this->repository->method('findByMapping')->willReturn($existing);
        $this->repository->expects($this->never())->method('save');

        $this->cache->expects($this->once())->method('clean');

        $result = $this->importer->importAll($csvData, false);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['failed']);
    }

    public function testDisabledMappingNoLongerCrashes(): void
    {
        // Regression test: a status=0 mapping is detected by findByMapping (status-agnostic)
        // and either updated (without changing status) or skipped — never crashes with duplicate-key.
        $csvData = $this->buildCsvData(['city_id' => '99']);

        $this->locationMappingResource->method('getRegionNameMap')->willReturn([1185 => 'Hồ Chí Minh']);
        $this->locationMappingResource->method('getCityNameMap')->willReturn([99 => 'Quận Bình Thạnh']);
        $this->locationMappingResource->method('getProvinceNameMap')->willReturn([202 => 'TP. HCM']);
        $this->locationMappingResource->method('getDistrictNameMap')->willReturn([1442 => 'Q.1']);
        $this->locationMappingResource->method('getWardNameMap')->willReturn(['20110' => 'Bến Nghé']);

        // Existing mapping with status=0 — findByMapping returns it (status-agnostic lookup)
        $disabledMapping = $this->createMock(LocationMappingInterface::class);
        $disabledMapping->method('getRegionId')->willReturn(1185);
        $this->repository->method('findByMapping')->willReturn($disabledMapping);

        // --- Sub-case 1: updateExisting=true → updated, status NOT re-enabled ---
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('beginTransaction');
        $adapter->method('commit');
        $this->setupTransactionMock($adapter);

        $disabledMapping->expects($this->once())->method('setCountryId');
        $disabledMapping->expects($this->once())->method('setGhnProvinceId');
        $disabledMapping->expects($this->once())->method('setGhnDistrictId');
        $disabledMapping->expects($this->once())->method('setGhnWardCode');
        $disabledMapping->expects($this->once())->method('setPriority');
        $disabledMapping->expects($this->once())->method('setRegionName');
        $disabledMapping->expects($this->once())->method('setCityName');
        $disabledMapping->expects($this->once())->method('setGhnProvinceName');
        $disabledMapping->expects($this->once())->method('setGhnDistrictName');
        $disabledMapping->expects($this->once())->method('setGhnWardName');
        $disabledMapping->expects($this->never())->method('setStatus'); // Status stays 0 — not re-enabled
        $this->repository->expects($this->once())->method('save')->with($disabledMapping, false);
        $this->cache->expects($this->once())->method('clean');

        $result = $this->importer->importAll($csvData, true);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['failed']);

        // --- Sub-case 2: updateExisting=false → skipped, no crash ---
        // Create fresh importer + mocks for the second run
        $this->repository = $this->createMock(LocationMappingRepositoryInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $adapter2 = $this->createMock(AdapterInterface::class);
        $adapter2->method('beginTransaction');
        $adapter2->method('commit');
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->resourceConnection->method('getConnection')->willReturn($adapter2);
        $this->importer = new MappingImporter(
            $this->mappingFactory,
            $this->repository,
            $this->cache,
            $this->logger,
            $this->locationMappingResource,
            $this->resourceConnection
        );

        $disabledMapping2 = $this->createMock(LocationMappingInterface::class);
        $disabledMapping2->method('getRegionId')->willReturn(1185);
        $this->repository->method('findByMapping')->willReturn($disabledMapping2);
        $this->repository->expects($this->never())->method('save');
        $this->cache->expects($this->once())->method('clean');

        $result2 = $this->importer->importAll($csvData, false);
        $this->assertSame(1, $result2['skipped']);
        $this->assertSame(0, $result2['failed']);
    }

    public function testMissingRequiredColumnThrows(): void
    {
        $csvData = [
            ['country_id', 'region_id', 'city_id', 'ghn_province_id', 'ghn_district_id'], // missing ghn_ward_code
            ['VN', '1185', '1', '202', '1442'],
        ];

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('Missing required CSV column: ghn_ward_code');

        $this->importer->importAll($csvData, false);
    }

    public function testTransactionCommitAndRollback(): void
    {
        $csvData = $this->buildCsvData();

        // --- Sub-case 1: success path commits ---
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('beginTransaction');
        $adapter->expects($this->once())->method('commit');
        $adapter->expects($this->never())->method('rollBack');
        $this->setupTransactionMock($adapter);

        $this->repository->method('findByMapping')->willReturn(null);
        $this->mappingFactory->method('create')->willReturn($this->createMock(LocationMappingInterface::class));
        $this->repository->method('save');
        $this->locationMappingResource->method('getRegionNameMap')->willReturn([]);
        $this->locationMappingResource->method('getCityNameMap')->willReturn([]);
        $this->locationMappingResource->method('getProvinceNameMap')->willReturn([]);
        $this->locationMappingResource->method('getDistrictNameMap')->willReturn([]);
        $this->locationMappingResource->method('getWardNameMap')->willReturn([]);
        $this->cache->expects($this->once())->method('clean');

        $result = $this->importer->importAll($csvData, false);
        $this->assertSame(1, $result['imported']);

        // --- Sub-case 2: commit() throws (deadlock / connection loss) → rollback, cache NOT cleaned ---
        // Create fresh mocks (sub-case 1 consumed adapter/repository/cache expectations)
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->repository = $this->createMock(LocationMappingRepositoryInterface::class);
        $this->mappingFactory = $this->createMock(LocationMappingInterfaceFactory::class);
        $this->locationMappingResource = $this->createMock(LocationMappingResource::class);
        $this->locationMappingResource->method('getRegionNameMap')->willReturn([]);
        $this->locationMappingResource->method('getCityNameMap')->willReturn([]);
        $this->locationMappingResource->method('getProvinceNameMap')->willReturn([]);
        $this->locationMappingResource->method('getDistrictNameMap')->willReturn([]);
        $this->locationMappingResource->method('getWardNameMap')->willReturn([]);

        $this->repository->method('findByMapping')->willReturn(null);
        $newMapping = $this->createMock(LocationMappingInterface::class);
        $this->mappingFactory->method('create')->willReturn($newMapping);
        $this->repository->method('save');

        // commit() throws — simulates deadlock / connection loss at commit time
        $adapter2 = $this->createMock(AdapterInterface::class);
        $adapter2->expects($this->once())->method('beginTransaction');
        $adapter2->expects($this->once())->method('commit')->willThrowException(
            new \RuntimeException('DB connection lost')
        );
        $adapter2->expects($this->once())->method('rollBack');
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->resourceConnection->method('getConnection')->willReturn($adapter2);

        $this->importer = new MappingImporter(
            $this->mappingFactory,
            $this->repository,
            $this->cache,
            $this->logger,
            $this->locationMappingResource,
            $this->resourceConnection
        );

        $this->logger->expects($this->once())->method('error')->with(
            $this->stringContains('Import transaction failed')
        );
        $this->cache->expects($this->never())->method('clean'); // No clean on rollback

        $result2 = $this->importer->importAll($csvData, false);
        $this->assertNotEmpty($result2['errors']);
        $this->assertStringContainsString('DB connection lost', (string)$result2['errors'][0]);
    }

    public function testMultipleMappingsSameCityIdNoViolation(): void
    {
        // 1:N mapping: same city_id with different ghn_ward_code should both be imported
        $csvData = [
            ['country_id', 'region_id', 'city_id', 'ghn_province_id', 'ghn_district_id', 'ghn_ward_code'],
            ['VN', '1185', '1', '202', '1442', '20110'],
            ['VN', '1185', '1', '202', '1442', '20111'], // same city_id, different ward
        ];

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('beginTransaction');
        $adapter->expects($this->once())->method('commit');
        $adapter->expects($this->never())->method('rollBack');
        $this->setupTransactionMock($adapter);

        // findByMapping returns null for both ward codes (new mappings)
        $this->repository->method('findByMapping')->willReturn(null);

        $this->locationMappingResource->method('getRegionNameMap')->willReturn([]);
        $this->locationMappingResource->method('getCityNameMap')->willReturn([]);
        $this->locationMappingResource->method('getProvinceNameMap')->willReturn([]);
        $this->locationMappingResource->method('getDistrictNameMap')->willReturn([]);
        $this->locationMappingResource->method('getWardNameMap')->willReturn([]);

        // Factory creates 2 new mappings
        $mapping1 = $this->createMock(LocationMappingInterface::class);
        $mapping2 = $this->createMock(LocationMappingInterface::class);
        $this->mappingFactory->method('create')->willReturnOnConsecutiveCalls($mapping1, $mapping2);

        $this->repository->expects($this->exactly(2))->method('save')
            ->with($this->anything(), false);
        $this->cache->expects($this->once())->method('clean');

        $result = $this->importer->importAll($csvData, false);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['failed']);
        $this->assertEmpty($result['errors']);
    }
}
