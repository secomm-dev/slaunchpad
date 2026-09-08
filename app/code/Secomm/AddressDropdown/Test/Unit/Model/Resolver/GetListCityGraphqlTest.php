<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model\Resolver;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Model\DataStorage;
use Secomm\AddressDropdown\Model\Resolver\GetListCityGraphql;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollection;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory;

/**
 * TASK-7HVGAB — the resolver owns filtering + mapping only. Deterministic ordering is
 * owned by CityLocaleCollection::_initSelect (canonical generic sort, see collection test).
 */
class GetListCityGraphqlTest extends TestCase
{
    private CityLocaleCollectionFactory&MockObject $collectionFactory;

    private CityLocaleCollection&MockObject $collection;

    private array $items = [];

    private GetListCityGraphql $resolver;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CityLocaleCollectionFactory::class);
        $this->collection = $this->createMock(CityLocaleCollection::class);
        $this->collectionFactory->method('create')->willReturn($this->collection);
        $this->collection->method('getIterator')->willReturnCallback(
            fn (): \Iterator => new \ArrayIterator($this->items)
        );

        $this->resolver = new GetListCityGraphql(
            $this->collectionFactory,
            $this->createMock(DataStorage::class)
        );
    }

    private function resolve(array $input): array
    {
        $field = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
        $info = $this->createMock(\Magento\Framework\GraphQl\Schema\Type\ResolveInfo::class);

        return $this->resolver->resolve($field, null, $info, null, ['input' => $input]);
    }

    public function testFiltersByRegionIdWhenProvided(): void
    {
        $this->collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('region_id', 1185);
        $this->collection->expects($this->once())->method('load');

        $this->resolve(['region_id' => 1185]);
    }

    public function testLoadsAllCitiesWithoutRegionId(): void
    {
        $this->collection->expects($this->never())->method('addFieldToFilter');
        $this->collection->expects($this->once())->method('load');

        $this->resolve([]);
    }

    public function testMapsItemsWithLabelFallback(): void
    {
        // CityModel exposes data via AbstractModel magic getters — a plain fixture
        // with the same accessors keeps the mapping contract explicit.
        $city = new class {
            public function getCityId(): int
            {
                return 42;
            }

            public function getRegionId(): int
            {
                return 1185;
            }

            public function getName(): ?string
            {
                return null;
            }

            public function getDefaultName(): string
            {
                return 'Ward A';
            }
        };
        $this->items = [$city];

        $result = $this->resolve(['region_id' => 1185]);

        $this->assertSame([
            [
                'city_id' => 42,
                'region_id' => 1185,
                'label' => 'Ward A',
                'default_name' => 'Ward A',
            ],
        ], $result);
    }

    public function testUsesLocaleNameAsLabelWhenAvailable(): void
    {
        $city = new class {
            public function getCityId(): int
            {
                return 7;
            }

            public function getRegionId(): int
            {
                return 1185;
            }

            public function getName(): ?string
            {
                return 'Phường A';
            }

            public function getDefaultName(): string
            {
                return 'Ward A';
            }
        };
        $this->items = [$city];

        $result = $this->resolve([]);

        $this->assertSame('Phường A', $result[0]['label']);
    }
}
