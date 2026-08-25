<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Model\Config\Source;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\Group;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Model\Config\Source\Categories;

/**
 * SPEC-TASK-S7MFCT: store-tree scoping, root exclusion, disambiguated labels.
 */
class CategoriesTest extends TestCase
{
    /**
     * @var CollectionFactory&MockObject
     */
    private $collectionFactory;

    /**
     * @var Collection&MockObject
     */
    private $collection;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * @var RequestInterface&MockObject
     */
    private $request;

    /**
     * @var array[] captured addAttributeToFilter calls
     */
    private array $filters = [];

    /**
     * @var int[] captured setStoreId calls
     */
    private array $storeIds = [];

    protected function setUp(): void
    {
        $this->filters = [];
        $this->storeIds = [];
        $this->collection = $this->createMock(Collection::class);
        $this->collection->method('setStoreId')->willReturnCallback(function (int $storeId) {
            $this->storeIds[] = $storeId;
            return $this->collection;
        });
        $this->collection->method('addAttributeToSelect')->willReturnSelf();
        $this->collection->method('addAttributeToFilter')->willReturnCallback(function ($attribute, $condition = null) {
            $this->filters[] = [$attribute, $condition];
            return $this->collection;
        });

        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->collectionFactory->method('create')->willReturn($this->collection);

        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('getParam')->willReturnCallback(fn ($key) => match ($key) {
            'store' => $this->storeParam,
            'website' => $this->websiteParam,
            default => null,
        });
    }

    /**
     * @var string|null simulated `store` request param (store-view scope)
     */
    private ?string $storeParam = null;

    /**
     * @var string|null simulated `website` request param (website scope)
     */
    private ?string $websiteParam = null;

    /**
     * @param array{id: int, name: string, path: string}[] $items
     * @return Categories configured source under test
     */
    private function sourceWithItems(array $items): Categories
    {
        $mocks = array_map(
            fn (array $item): Category => $this->categoryMock($item['id'], $item['name'], $item['path']),
            $items
        );
        $this->collection->method('getItems')->willReturn($mocks);

        return new Categories($this->collectionFactory, $this->storeManager, $this->request);
    }

    /**
     * @return array<int, string> option value => label
     */
    private function optionMap(array $options): array
    {
        $map = [];
        foreach ($options as $option) {
            $map[$option['value']] = $option['label'];
        }

        return $map;
    }

    private function categoryMock(int $id, string $name, string $path): Category
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn($id);
        $category->method('getName')->willReturn($name);
        $category->method('getPath')->willReturn($path);

        return $category;
    }

    private function groupMock(int $rootId, string $name): Group
    {
        $group = $this->createMock(Group::class);
        $group->method('getRootCategoryId')->willReturn($rootId);
        $group->method('getName')->willReturn($name);

        return $group;
    }

    public function testStoreScopeResolvesStoreTreeAndExcludesRoots(): void
    {
        $this->storeParam = 'vietnam';
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(3);
        $store->method('getGroup')->willReturn($this->groupMock(2, 'Main Store'));
        $this->storeManager->method('getStore')->with('vietnam')->willReturn($store);

        $options = $this->sourceWithItems([
            ['id' => 20, 'name' => 'Women', 'path' => '1/2/20'],
            ['id' => 45, 'name' => 'Accessories', 'path' => '1/2/20/45'],
        ])->toOptionArray();

        // Store-scoped collection + tree filter + root/global exclusion.
        $this->assertSame([3], $this->storeIds);
        $this->assertStringContainsString('2/%', var_export($this->filters, true));
        $this->assertStringContainsString('nin', var_export($this->filters, true));
        $this->assertStringContainsString('is_active', var_export($this->filters, true));

        $map = $this->optionMap($options);
        $this->assertSame(['20', '45'], array_map('strval', array_keys($map)));
        $this->assertSame('Women', $map['20']);
        $this->assertSame('Women > Accessories', $map['45']);
    }

    public function testInactiveCategoriesFiltered(): void
    {
        $this->storeParam = 'vietnam';
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(3);
        $store->method('getGroup')->willReturn($this->groupMock(2, 'Main Store'));
        $this->storeManager->method('getStore')->with('vietnam')->willReturn($store);

        $this->sourceWithItems([])->toOptionArray();

        $isActive = array_filter($this->filters, fn ($f) => $f[0] === 'is_active');
        $this->assertSame([[1]], array_map(fn ($f) => array_values((array) $f[1]), array_values($isActive)));
    }

    public function testDuplicateNamesDisambiguatedAndSorted(): void
    {
        $this->storeParam = 'vietnam';
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(3);
        $store->method('getGroup')->willReturn($this->groupMock(2, 'Main Store'));
        $this->storeManager->method('getStore')->with('vietnam')->willReturn($store);

        $options = $this->sourceWithItems([
            ['id' => 21, 'name' => 'Men', 'path' => '1/2/21'],
            ['id' => 46, 'name' => 'Accessories', 'path' => '1/2/21/46'], // Men > Accessories
            ['id' => 20, 'name' => 'Women', 'path' => '1/2/20'],
            ['id' => 45, 'name' => 'Accessories', 'path' => '1/2/20/45'], // Women > Accessories
            ['id' => 22, 'name' => 'Bedroom', 'path' => '1/2/22'],
            ['id' => 47, 'name' => 'Accessories', 'path' => '1/2/22/47'], // Bedroom > Accessories
        ])->toOptionArray();

        $this->assertSame(
            ['Bedroom', 'Bedroom > Accessories', 'Men', 'Men > Accessories', 'Women', 'Women > Accessories'],
            array_column($options, 'label')
        );
        // No duplicate option values; values remain entity ids.
        $values = array_map('strval', array_column($options, 'value'));
        $this->assertSame($values, array_values(array_unique($values)));
    }

    public function testWebsiteScopeSingleRootBehavesLikeTreeWithoutPrefix(): void
    {
        $this->websiteParam = 'base';
        $website = $this->createMock(Website::class);
        $website->method('getGroups')->willReturn([$this->groupMock(2, 'Main Store'), $this->groupMock(2, 'Outlet')]);
        $this->storeManager->method('getWebsite')->with('base')->willReturn($website);

        $map = $this->optionMap($this->sourceWithItems([
            ['id' => 20, 'name' => 'Women', 'path' => '1/2/20'],
            ['id' => 45, 'name' => 'Accessories', 'path' => '1/2/20/45'],
        ])->toOptionArray());

        $this->assertSame('Women > Accessories', $map['45']);
    }

    public function testWebsiteScopeMultipleRootsLabeledUnionOfOwnTreesOnly(): void
    {
        $this->websiteParam = 'base';
        $website = $this->createMock(Website::class);
        $website->method('getGroups')->willReturn([
            $this->groupMock(2, 'Fashion Store'),
            $this->groupMock(9, 'Home Store'),
        ]);
        $this->storeManager->method('getWebsite')->with('base')->willReturn($website);

        $map = $this->optionMap($this->sourceWithItems([
            ['id' => 20, 'name' => 'Women', 'path' => '1/2/20'],
            ['id' => 45, 'name' => 'Accessories', 'path' => '1/2/20/45'],
            ['id' => 90, 'name' => 'Living', 'path' => '1/9/90'],
            ['id' => 100, 'name' => 'Accessories', 'path' => '1/9/90/100'],
        ])->toOptionArray());

        $this->assertSame('Fashion Store > Women > Accessories', $map['45']);
        $this->assertSame('Home Store > Living > Accessories', $map['100']);
        // Both website roots excluded from the id filter.
        $this->assertStringContainsString('9/%', var_export($this->filters, true));
    }

    public function testDefaultScopeIsLabeledUnionOfAllTrees(): void
    {
        $this->storeParam = null;
        $this->websiteParam = null;
        $this->storeManager->method('getGroups')->willReturn([
            $this->groupMock(2, 'Fashion Store'),
            $this->groupMock(9, 'Home Store'),
        ]);

        $map = $this->optionMap($this->sourceWithItems([
            ['id' => 20, 'name' => 'Women', 'path' => '1/2/20'],
            ['id' => 45, 'name' => 'Accessories', 'path' => '1/2/20/45'],
            ['id' => 90, 'name' => 'Living', 'path' => '1/9/90'],
            ['id' => 100, 'name' => 'Beds', 'path' => '1/9/90/100'],
        ])->toOptionArray());

        $this->assertSame('Fashion Store > Women > Accessories', $map['45']);
        $this->assertSame('Home Store > Living > Beds', $map['100']);
    }

    public function testUnnamedCategoryFallsBackToIdLabel(): void
    {
        $this->storeParam = 'vietnam';
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(3);
        $store->method('getGroup')->willReturn($this->groupMock(2, 'Main Store'));
        $this->storeManager->method('getStore')->with('vietnam')->willReturn($store);

        $map = $this->optionMap($this->sourceWithItems([
            ['id' => 55, 'name' => '', 'path' => '1/2/55'],
        ])->toOptionArray());

        $this->assertSame('[ID: 55]', $map['55']);
    }
}
