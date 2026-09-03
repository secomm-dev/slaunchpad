<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Command\City;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\AddressProfileResolverInterface;
use Secomm\AddressDropdown\Api\Data\AddressProfileInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\SchemaLevelInterface;
use Secomm\AddressDropdown\Api\AddressSchemaProviderInterface;
use Secomm\AddressDropdown\Command\City\SaveValidator;
use Secomm\AddressDropdown\Model\CityModel;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollection;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollectionFactory;

/**
 * TASK-9EX975 Slice B — admin city CRUD validation mirrors the import rules:
 * region required, self/cycle guard, same-region parent, MAX_DEPTH bound,
 * duplicate code per (region, parent), code length, and the non-blocking
 * beyond-profile depth warning (AC-B3).
 */
class SaveValidatorTest extends TestCase
{
    private CityCollectionFactory&MockObject $collectionFactory;
    private AddressProfileResolverInterface&MockObject $profileResolver;
    private AddressSchemaProviderInterface&MockObject $schemaProvider;
    private LoggerInterface&MockObject $logger;

    /** @var array<int, CityCollection&MockObject> consumed FIFO by factory->create() */
    private array $collectionQueue = [];

    /** @var string[] messages passed to the validator's logger->warning (diagnostics) */
    private array $loggedWarnings = [];

    private SaveValidator $validator;

    protected function setUp(): void
    {
        $this->collectionQueue = [];

        $this->collectionFactory = $this->createMock(CityCollectionFactory::class);
        $this->collectionFactory->method('create')->willReturnCallback(
            fn (): CityCollection => array_shift($this->collectionQueue)
                ?? throw new \LogicException('No queued collection for this call')
        );

        $this->profileResolver = $this->createMock(AddressProfileResolverInterface::class);
        $this->schemaProvider = $this->createMock(AddressSchemaProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->method('warning')->willReturnCallback(
            function (string $message): void {
                $this->loggedWarnings[] = $message;
            }
        );

        $this->validator = new SaveValidator(
            $this->collectionFactory,
            $this->profileResolver,
            $this->schemaProvider,
            $this->logger
        );
    }

    /**
     * Queue one collection whose getFirstItem() yields the given row (empty row = isEmpty()).
     *
     * @return CityCollection&MockObject the queued mock (tests may assert filters on it)
     */
    private function queueCityRow(array $row): CityCollection&MockObject
    {
        $item = $this->createMock(CityModel::class);
        $item->method('isEmpty')->willReturn($row === []);
        $item->method('getData')->willReturn($row);

        $collection = $this->createMock(CityCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($item);

        $this->collectionQueue[] = $collection;

        return $collection;
    }

    /**
     * Queue the collection used by countryOfRegion(): ->getConnection()->fetchOne() -> $countryId.
     */
    private function queueCountryLookup(string|false|null $countryId): void
    {
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $adapter = $this->createMock(\Magento\Framework\DB\Adapter\Pdo\Mysql::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('fetchOne')->willReturn($countryId ?: false);

        $collection = $this->createMock(CityCollection::class);
        $collection->method('getConnection')->willReturn($adapter);

        $this->collectionQueue[] = $collection;
    }

    private function makeCity(
        ?int $regionId = 1,
        ?int $cityId = null,
        ?int $parentId = null,
        ?string $code = null,
        ?string $defaultName = 'District A'
    ): CityInterface&MockObject {
        $city = $this->createMock(CityInterface::class);
        $city->method('getRegionId')->willReturn($regionId);
        $city->method('getCityId')->willReturn($cityId);
        $city->method('getParentCityId')->willReturn($parentId);
        $city->method('getCode')->willReturn($code);
        $city->method('getDefaultName')->willReturn($defaultName);

        return $city;
    }

    // ------------------------------------------------------------------ hard failures

    public function testThrowsWhenRegionMissing(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Region is required.');

        $this->validator->validate($this->makeCity(regionId: null));
    }

    public function testThrowsWhenCityIsItsOwnParent(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cannot be its own parent');

        $this->validator->validate($this->makeCity(cityId: 5, parentId: 5));
    }

    public function testThrowsWhenParentDoesNotExist(): void
    {
        $this->queueCityRow([]); // loadCity(999) -> not found

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Parent city (ID 999) does not exist.');

        $this->validator->validate($this->makeCity(cityId: 5, parentId: 999));
    }

    public function testThrowsWhenParentBelongsToAnotherRegion(): void
    {
        $this->queueCityRow(['city_id' => 10, 'region_id' => 2, 'parent_city_id' => null]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('same region');

        $this->validator->validate($this->makeCity(regionId: 1, cityId: 5, parentId: 10));
    }

    public function testThrowsWhenParentChainIsBroken(): void
    {
        $this->queueCityRow(['city_id' => 10, 'region_id' => 1, 'parent_city_id' => 7]);
        $this->queueCityRow([]); // ancestor 7 vanished

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Parent chain is broken');

        $this->validator->validate($this->makeCity(regionId: 1, cityId: 5, parentId: 10));
    }

    public function testThrowsWhenParentChainCyclesBackIntoSubtree(): void
    {
        // Editing city 5 under parent 10; 10 -> 7 -> 5 loops back into the subtree being moved.
        $this->queueCityRow(['city_id' => 10, 'region_id' => 1, 'parent_city_id' => 7]);
        $this->queueCityRow(['city_id' => 7, 'region_id' => 1, 'parent_city_id' => 5]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cycle');

        $this->validator->validate($this->makeCity(regionId: 1, cityId: 5, parentId: 10));
    }

    public function testThrowsWhenParentChainExceedsMaxDepth(): void
    {
        // Parent 1 -> 2 -> ... -> 17: the walk bound (MAX_DEPTH = 16) trips before the end.
        for ($id = 1; $id <= 16; $id++) {
            $this->queueCityRow(['city_id' => $id, 'region_id' => 1, 'parent_city_id' => $id + 1]);
        }

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('maximum depth of 16');

        $this->validator->validate($this->makeCity(regionId: 1, parentId: 1));
    }

    public function testThrowsWhenCodeTooLong(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Code longer than 64 characters.');

        $this->validator->validate($this->makeCity(code: str_repeat('x', 65)));
    }

    public function testThrowsOnDuplicateCodeAtDepthOne(): void
    {
        $this->queueCityRow(['city_id' => 99, 'region_id' => 1, 'parent_city_id' => null]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('already exists at this level');

        $this->validator->validate($this->makeCity(code: 'ABC'));
    }

    public function testDuplicateCodeCheckExcludesSelfAndMatchesParent(): void
    {
        // Editing city 5 (code ABC) under parent 10: the duplicate row found is a DIFFERENT city
        // (99), so it must still throw; also proves self exclusion and parent filters were applied.
        $captured = [];
        $collection = $this->createMock(CityCollection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$captured, $collection) {
                $captured[$field] = $condition;

                return $collection;
            }
        );
        $item = $this->createMock(CityModel::class);
        $item->method('isEmpty')->willReturn(false);
        $collection->method('getFirstItem')->willReturn($item);

        // resolveDepth consumes the parent lookup before assertUniqueCode consumes the dup check.
        $this->queueCityRow(['city_id' => 10, 'region_id' => 1, 'parent_city_id' => null]);
        $this->collectionQueue[] = $collection;

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('already exists at this level');

        try {
            $this->validator->validate($this->makeCity(cityId: 5, parentId: 10, code: 'ABC'));
        } finally {
            self::assertSame('ABC', $captured[CityInterface::CODE]);
            self::assertSame(10, $captured[CityInterface::PARENT_CITY_ID]);
            self::assertSame(['neq' => 5], $captured[CityInterface::CITY_ID]);
        }
    }

    public function testDuplicateCheckUsesNullParentFilterForDepthOne(): void
    {
        $captured = [];
        $collection = $this->createMock(CityCollection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$captured, $collection) {
                $captured[$field] = $condition;

                return $collection;
            }
        );
        $item = $this->createMock(CityModel::class);
        $item->method('isEmpty')->willReturn(true);
        $collection->method('getFirstItem')->willReturn($item);
        $this->collectionQueue[] = $collection;

        $this->queueCountryLookup(false); // coverage check bails out: no country for region

        $warnings = $this->validator->validate($this->makeCity(code: 'ABC'));

        self::assertSame([], $warnings);
        self::assertSame(['null' => true], $captured[CityInterface::PARENT_CITY_ID]);
        self::assertSame('ABC', $captured[CityInterface::CODE]);
    }

    // ------------------------------------------------------------------ success paths

    public function testRootCityWithoutCodePasses(): void
    {
        $this->queueCountryLookup(false);

        $warnings = $this->validator->validate($this->makeCity());

        self::assertSame([], $warnings);
    }

    public function testChildCityPassesAtDepthTwo(): void
    {
        $this->queueCityRow(['city_id' => 10, 'region_id' => 1, 'parent_city_id' => null]);
        $this->queueCountryLookup(false);

        $warnings = $this->validator->validate($this->makeCity(parentId: 10));

        self::assertSame([], $warnings);
    }

    public function testBeyondProfileDepthSavesWithWarning(): void
    {
        // AC-B3: depth 2 under a profile that only renders 1 city level — saved, but flagged.
        $this->queueCityRow(['city_id' => 10, 'region_id' => 1, 'parent_city_id' => null]);
        $this->queueCountryLookup('VN');

        $profile = $this->createMock(AddressProfileInterface::class);
        $profile->method('getCode')->willReturn('vn_admin_2025');
        $this->profileResolver->method('resolve')->with('VN')->willReturn($profile);

        $cityLevel = $this->createMock(SchemaLevelInterface::class);
        $cityLevel->method('getEntityType')->willReturn(SchemaLevelInterface::ENTITY_TYPE_CITY);
        $cityLevel->method('getDepth')->willReturn(1);
        $this->schemaProvider->method('getSchema')->with('vn_admin_2025')->willReturn([$cityLevel]);

        $warnings = $this->validator->validate($this->makeCity(parentId: 10));

        self::assertCount(1, $warnings, implode(' | ', $this->loggedWarnings));
        self::assertStringContainsString('deeper than the 1 city level(s)', $warnings[0]);
        self::assertStringContainsString('vn_admin_2025', $warnings[0]);
    }

    public function testDepthWithinProfileProducesNoWarning(): void
    {
        $this->queueCityRow(['city_id' => 10, 'region_id' => 1, 'parent_city_id' => null]);
        $this->queueCountryLookup('VN');

        $profile = $this->createMock(AddressProfileInterface::class);
        $profile->method('getCode')->willReturn('vn_admin_2025');
        $this->profileResolver->method('resolve')->with('VN')->willReturn($profile);

        $cityLevel = $this->createMock(SchemaLevelInterface::class);
        $cityLevel->method('getEntityType')->willReturn(SchemaLevelInterface::ENTITY_TYPE_CITY);
        $cityLevel->method('getDepth')->willReturn(2);
        $this->schemaProvider->method('getSchema')->willReturn([$cityLevel]);

        $warnings = $this->validator->validate($this->makeCity(parentId: 10));

        self::assertSame([], $warnings);
    }

    public function testCoverageCheckFailureNeverBlocksSave(): void
    {
        $this->queueCityRow(['city_id' => 10, 'region_id' => 1, 'parent_city_id' => null]);
        $this->queueCountryLookup('VN');

        $this->profileResolver->method('resolve')->willThrowException(new \RuntimeException('boom'));

        $warnings = $this->validator->validate($this->makeCity(parentId: 10));

        self::assertSame([], $warnings);
    }
}
