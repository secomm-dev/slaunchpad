<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\CustomerData;

use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\CustomerData\CityData;
use Secomm\AddressDropdown\Helper\Data as AddressDropdownHelper;
use Secomm\AddressDropdown\Model\Cache\Type as CacheType;
use Secomm\AddressDropdown\Model\ResourceModel\CitySort;

/**
 * TASK-Z6SK3T — the section builds the region→city tree in exactly TWO statements
 * (was 1,191 collections), groups in PHP, keeps the store-scoped cache id and now
 * saves through the tagged `secomm_address_city` type frontend.
 */
class CityDataTest extends TestCase
{
    private AddressDropdownHelper&MockObject $helper;

    private AdapterInterface&MockObject $adapter;

    private Select&MockObject $select;

    private CacheType&MockObject $cacheType;

    private ResolverInterface&MockObject $localeResolver;

    private State&MockObject $appState;

    private StoreManagerInterface&MockObject $storeManager;

    /** @var array<int, array> captured fetchAll bind args */
    private array $fetchAllBinds = [];

    /** @var array<int, array> canned fetchAll results: [0]=regions, [1]=cities */
    private array $fetchAllResults = [];

    private ?string $savedCache = null;

    private CityData $cityData;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(AddressDropdownHelper::class);
        $this->helper->method('isAddressDropdownModuleEnabled')->willReturn(true);

        $this->adapter = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $this->adapter->method('select')->willReturn($select);
        $this->select = $select;
        $this->adapter->method('fetchAll')->willReturnCallback(function ($sql, $bind = []) {
            $this->fetchAllBinds[] = $bind;
            return $this->fetchAllResults[count($this->fetchAllBinds) - 1] ?? [];
        });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $this->cacheType = $this->createMock(CacheType::class);
        $this->cacheType->method('load')->willReturnCallback(function () {
            return $this->savedCache;
        });
        $this->cacheType->method('save')->willReturnCallback(function ($data) {
            $this->savedCache = $data;
            return true;
        });

        $this->localeResolver = $this->createMock(ResolverInterface::class);
        $this->localeResolver->method('getLocale')->willReturn('vi_VN');

        $this->appState = $this->createMock(State::class);
        $this->appState->method('getAreaCode')->willReturn('frontend');

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->resetFixtures();
        $this->cityData = new CityData(
            $this->helper,
            $resource,
            $this->cacheType,
            $this->localeResolver,
            $this->appState,
            $this->storeManager
        );
    }

    private function resetFixtures(): void
    {
        $this->fetchAllBinds = [];
        $this->savedCache = null;
        $this->fetchAllResults = [
            // [0] regions (region_id, name=default_name, label=COALESCE)
            [
                ['region_id' => 1205, 'name' => 'TP. Hồ Chí Minh', 'label' => 'TP. Hồ Chí Minh'],
                ['region_id' => 1222, 'name' => 'Hà Nội', 'label' => 'Hà Nội'],
            ],
            // [1] cities (region_id, default_name, name nullable)
            [
                ['region_id' => 1205, 'default_name' => 'Phường Bến Nghé', 'name' => 'Phường Bến Nghé'],
                ['region_id' => 1205, 'default_name' => 'Thanh An', 'name' => null],
                ['region_id' => 1222, 'default_name' => 'Phường Hàng Bài', 'name' => 'Phường Hàng Bài'],
                ['region_id' => 999, 'default_name' => 'Orphan', 'name' => 'Orphan'],
            ],
        ];
    }

    public function testMasterSwitchOffReturnsEmptyWithoutDatabaseOrCacheAccess(): void
    {
        $helper = $this->createMock(AddressDropdownHelper::class);
        $helper->method('isAddressDropdownModuleEnabled')->willReturn(false);
        $cityData = new CityData(
            $helper,
            $this->createMock(ResourceConnection::class),
            $this->cacheType,
            $this->localeResolver,
            $this->appState,
            $this->storeManager
        );

        $this->adapter->expects($this->never())->method('fetchAll');
        $this->cacheType->expects($this->never())->method('load');

        $this->assertSame([], $cityData->getSectionData());
    }

    public function testColdBuildUsesExactlyTwoFetchAllsAndKeepsOutputShape(): void
    {
        $result = $this->cityData->getSectionData();

        $this->assertCount(2, $this->fetchAllBinds, 'cities + regions must be 2 statements total');

        // Data-derived scope: only regions carrying cities; orphan city row ignored.
        $this->assertSame(array_keys($result), [1205, 1222]);

        foreach ($this->fetchAllBinds as $bind) {
            $this->assertSame([':region_locale' => 'vi_VN'], $bind);
        }

        // Per-region shape identical to the legacy builder (Region::getName() falls back
        // to default_name; city name stays nullable).
        $this->assertSame('TP. Hồ Chí Minh', $result[1205]['name']);
        $this->assertSame('TP. Hồ Chí Minh', $result[1205]['label']);
        $this->assertSame(
            ['default_name' => 'Phường Bến Nghé', 'name' => 'Phường Bến Nghé'],
            $result[1205]['city']['Phường Bến Nghé']
        );
        $this->assertSame(['default_name' => 'Thanh An', 'name' => null], $result[1205]['city']['Thanh An']);
        $this->assertSame(['Hà Nội', 'Hà Nội'], [$result[1222]['name'], $result[1222]['label']]);
        $this->assertSame(['Phường Hàng Bài'], array_keys($result[1222]['city']));

        // Saved through the tagged frontend, store-scoped id, 1h TTL.
        $this->assertNotNull($this->savedCache);
        $this->assertSame($result, json_decode((string) $this->savedCache, true));
    }

    public function testCitiesQueryUsesVietnameseAlphabetSortKey(): void
    {
        // TASK-Z6SK3T: the section keeps the canonical vi-alphabet sort — Đ is its own
        // letter after the full D block (d8bad508 regression guard; only the cities query
        // orders).
        $this->select->expects($this->once())
            ->method('order')
            ->with($this->callback(static function ($expr): bool {
                $sql = (string) $expr;
                return $expr instanceof \Zend_Db_Expr
                    && str_contains($sql, 'CONVERT(COALESCE(n.name, c.default_name) USING utf8mb4)')
                    && str_contains($sql, 'COLLATE ' . CitySort::COLLATION)
                    && str_contains($sql, 'c.city_id ASC');
            }));

        $this->cityData->getSectionData();
    }

    public function testSaveUsesStoreScopedIdTagAndTtl(): void
    {
        $captured = null;
        $cacheType = $this->createMock(CacheType::class);
        $cacheType->method('load')->willReturn(false);
        $cacheType->expects($this->once())
            ->method('save')
            ->willReturnCallback(function ($data, $id, array $tags, $lifetime) use (&$captured) {
                $captured = [$id, $tags, $lifetime];
                return true;
            });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $cityData = new CityData(
            $this->helper,
            $resource,
            $cacheType,
            $this->localeResolver,
            $this->appState,
            $this->storeManager
        );
        $cityData->getSectionData();

        [$id, $tags, $lifetime] = $captured;
        $this->assertSame('city_data_cache_key_2', $id);
        $this->assertSame([CacheType::CACHE_TAG], $tags);
        $this->assertSame(3600, $lifetime);
    }

    public function testCacheHitSkipsDatabase(): void
    {
        $payload = json_encode([1205 => ['name' => 'TP. Hồ Chí Minh', 'label' => 'TP. Hồ Chí Minh']]);
        $this->savedCache = $payload;

        $this->adapter->expects($this->never())->method('fetchAll');

        $this->assertSame(json_decode($payload, true), $this->cityData->getSectionData());
    }

    public function testAdminAreaResolvesDefaultLocale(): void
    {
        $this->appState = $this->createMock(State::class);
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_ADMINHTML);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnArgument(0);
        $cityData = new CityData(
            $this->helper,
            $resource,
            $this->cacheType,
            $this->localeResolver,
            $this->appState,
            $this->storeManager
        );

        $cityData->getSectionData();

        foreach ($this->fetchAllBinds as $bind) {
            $this->assertSame([':region_locale' => LocaleResolver::DEFAULT_LOCALE], $bind);
        }
    }
}
