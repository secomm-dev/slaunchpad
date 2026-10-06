<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model\Resolver;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Api\AddressProfileResolverInterface;
use Secomm\AddressDropdown\Api\Data\AddressProfileInterface;
use Secomm\AddressDropdown\Api\Data\LocationNodeInterface;
use Secomm\AddressDropdown\Api\LocationHierarchyProviderInterface;
use Secomm\AddressDropdown\Helper\Data;
use Secomm\AddressDropdown\Model\DataStorage;
use Secomm\AddressDropdown\Model\Resolver\GetListCityGraphql;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollection;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory;

/**
 * TASK-Z6SK3T / DEC-TASKZ6SK3T-001 — GetListCity is a BC shim: mapped country →
 * LocationHierarchyProvider (root-only); unmapped country / admin area / null region_id
 * → legacy CityLocaleCollection; invalid region_id → [] without queries.
 */
class GetListCityGraphqlTest extends TestCase
{
    private CityLocaleCollectionFactory&MockObject $collectionFactory;

    private CityLocaleCollection&MockObject $collection;

    private Data&MockObject $helper;

    private AddressProfileResolverInterface&MockObject $profileResolver;

    private LocationHierarchyProviderInterface&MockObject $hierarchyProvider;

    private GetListCityGraphql $resolver;

    private array $items = [];

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CityLocaleCollectionFactory::class);
        $this->collection = $this->createMock(CityLocaleCollection::class);
        $this->collectionFactory->method('create')->willReturn($this->collection);
        $this->collection->method('getIterator')->willReturnCallback(
            fn (): \Iterator => new \ArrayIterator($this->items)
        );

        // Default-enabled fixture: these tests exercise resolver behavior, not the switch.
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);

        $this->helper = $this->createMock(Data::class);
        $this->profileResolver = $this->createMock(AddressProfileResolverInterface::class);
        $this->hierarchyProvider = $this->createMock(LocationHierarchyProviderInterface::class);

        $this->resolver = new GetListCityGraphql(
            $this->collectionFactory,
            $this->createMock(DataStorage::class),
            $scopeConfig,
            $this->helper,
            $this->profileResolver,
            $this->hierarchyProvider
        );
    }

    private function resolve(array $input): array
    {
        $field = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
        $info = $this->createMock(\Magento\Framework\GraphQl\Schema\Type\ResolveInfo::class);

        return $this->resolver->resolve($field, null, $info, null, ['input' => $input]);
    }

    private function stubMappedCountry(int $regionId = 1205): AddressProfileInterface&MockObject
    {
        $this->helper->method('getCountryIdByRegionId')->with($regionId)->willReturn('VN');
        $profile = $this->createMock(AddressProfileInterface::class);
        $profile->method('getCode')->willReturn('vn_admin_2025');
        $this->profileResolver->method('resolve')->with('VN')->willReturn($profile);

        return $profile;
    }

    private function node(int $cityId, string $name, string $defaultName): LocationNodeInterface&MockObject
    {
        $node = $this->createMock(LocationNodeInterface::class);
        $node->method('getCityId')->willReturn($cityId);
        $node->method('getRegionId')->willReturn(1205);
        $node->method('getName')->willReturn($name);
        $node->method('getDefaultName')->willReturn($defaultName);

        return $node;
    }

    public function testMappedCountryDelegatesToHierarchyProvider(): void
    {
        $this->stubMappedCountry();
        $this->hierarchyProvider->expects($this->once())
            ->method('getRootLocations')
            ->with(1205, 'vn_admin_2025')
            ->willReturn([
                $this->node(42, 'Phường Bến Nghé', 'Phuong Ben Nghe'),
                $this->node(43, 'Thảo Điền', 'Thao Dien'),
            ]);
        // Legacy engine must stay untouched on the canonical path.
        $this->collectionFactory->expects($this->never())->method('create');

        $result = $this->resolve(['region_id' => '1205']);

        $this->assertSame(
            [
                [
                    'city_id' => 42,
                    'region_id' => 1205,
                    'label' => 'Phường Bến Nghé',
                    'default_name' => 'Phuong Ben Nghe',
                ],
                [
                    'city_id' => 43,
                    'region_id' => 1205,
                    'label' => 'Thảo Điền',
                    'default_name' => 'Thao Dien',
                ],
            ],
            $result
        );
    }

    public function testUnmappedCountryFallsBackToLegacyCollection(): void
    {
        $this->helper->method('getCountryIdByRegionId')->willReturn('US');
        $this->profileResolver->method('resolve')->with('US')->willReturn(null);
        $this->collection->expects($this->once())->method('addFieldToFilter')->with('region_id', '555');
        $this->collection->expects($this->once())->method('load');
        $this->hierarchyProvider->expects($this->never())->method('getRootLocations');

        $this->assertSame([], $this->resolve(['region_id' => '555']));
    }

    public function testAdminAreaKeepsLegacyPathEvenForMappedCountry(): void
    {
        $this->helper->expects($this->never())->method('getCountryIdByRegionId');
        $this->collection->expects($this->once())->method('load');
        $this->hierarchyProvider->expects($this->never())->method('getRootLocations');

        $this->assertSame([], $this->resolve(['region_id' => '1205', 'area' => 'adminhtml']));
    }

    public function testNonNumericRegionIdReturnsEmptyWithoutQueries(): void
    {
        $this->helper->expects($this->never())->method('getCountryIdByRegionId');
        $this->collectionFactory->expects($this->never())->method('create');
        $this->hierarchyProvider->expects($this->never())->method('getRootLocations');

        $this->assertSame([], $this->resolve(['region_id' => 'abc']));
        $this->assertSame([], $this->resolve(['region_id' => '0']));
        $this->assertSame([], $this->resolve(['region_id' => '-3']));
    }

    public function testUnknownRegionReturnsEmptyWithoutCollection(): void
    {
        $this->helper->method('getCountryIdByRegionId')->willReturn('');
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertSame([], $this->resolve(['region_id' => '999999']));
    }

    public function testNullRegionIdKeepsLegacyLoadAll(): void
    {
        $this->collection->expects($this->never())->method('addFieldToFilter');
        $this->collection->expects($this->once())->method('load');
        $this->hierarchyProvider->expects($this->never())->method('getRootLocations');

        $this->assertSame([], $this->resolve([]));
    }

    public function testMasterSwitchOffReturnsEmpty(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(false);
        $resolver = new GetListCityGraphql(
            $this->collectionFactory,
            $this->createMock(DataStorage::class),
            $scopeConfig,
            $this->helper,
            $this->profileResolver,
            $this->hierarchyProvider
        );
        $field = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
        $info = $this->createMock(\Magento\Framework\GraphQl\Schema\Type\ResolveInfo::class);

        $this->assertSame([], $resolver->resolve($field, null, $info, null, ['input' => ['region_id' => '1205']]));
    }
}
