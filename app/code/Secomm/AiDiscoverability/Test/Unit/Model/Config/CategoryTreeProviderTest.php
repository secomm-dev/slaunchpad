<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Model\Config;

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
use Secomm\AiDiscoverability\Model\Config\CategoryTreeProvider;

/**
 * SPEC-TASK-5TGJ7V correction: ui-select options (Product Edit Categories
 * shape: nested {value, label, optgroup}) scoped to the config section scope.
 */
class CategoryTreeProviderTest extends TestCase
{
    /**
     * @var CollectionFactory&MockObject
     */
    private $collectionFactory;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * @var RequestInterface&MockObject
     */
    private $request;

    /**
     * @var array[] captured options-collection filter calls
     */
    private array $filters = [];

    /**
     * @var int[] captured setStoreId calls
     */
    private array $storeIds = [];

    /**
     * @var string|null simulated `store` request param
     */
    private ?string $storeParam = null;

    /**
     * @var string|null simulated `website` request param
     */
    private ?string $websiteParam = null;

    protected function setUp(): void
    {
        $this->filters = [];
        $this->storeIds = [];
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('getParam')->willReturnCallback(fn ($key) => match ($key) {
            'store' => $this->storeParam,
            'website' => $this->websiteParam,
            default => null,
        });
    }

    /**
     * Provider with simulated collections: factory call #1 = root-path lookup,
     * call #2 = options query.
     *
     * @param array{id: int, name: string, path: string, parent_id: int}[] $items
     * @param array<int, string> $rootPaths
     * @return CategoryTreeProvider
     */
    private function providerWithItems(array $items, array $rootPaths): CategoryTreeProvider
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('setStoreId')->willReturnCallback(function (int $storeId) use ($collection) {
            $this->storeIds[] = $storeId;
            return $collection;
        });
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToSort')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnCallback(
            function ($attribute, $condition = null) use ($collection) {
                $this->filters[] = [$attribute, $condition];
                return $collection;
            }
        );
        $collection->method('getItems')->willReturn(array_map(
            fn (array $item): Category => $this->categoryMock(
                $item['id'],
                $item['name'],
                $item['path'],
                $item['parent_id']
            ),
            $items
        ));

        $rootPathCollection = $this->createMock(Collection::class);
        $rootPathCollection->method('addFieldToFilter')->willReturnSelf();
        $rootPathCollection->method('getItems')->willReturn(array_map(
            fn (int $id, string $path): Category => $this->categoryMock($id, '', $path, 1),
            array_keys($rootPaths),
            $rootPaths
        ));

        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $factoryCalls = 0;
        $this->collectionFactory->method('create')->willReturnCallback(
            function () use (&$factoryCalls, $rootPathCollection, $collection) {
                $factoryCalls++;
                return $factoryCalls === 1 ? $rootPathCollection : $collection;
            }
        );

        return new CategoryTreeProvider($this->collectionFactory, $this->storeManager, $this->request);
    }

    private function categoryMock(int $id, string $name, string $path, int $parentId): Category
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn($id);
        $category->method('getName')->willReturn($name);
        $category->method('getPath')->willReturn($path);
        $category->method('getParentId')->willReturn($parentId);

        return $category;
    }

    private function groupMock(int $rootId, string $name): Group
    {
        $group = $this->createMock(Group::class);
        $group->method('getRootCategoryId')->willReturn($rootId);
        $group->method('getName')->willReturn($name);

        return $group;
    }

    private function storeScope(): void
    {
        $this->storeParam = 'vietnam';
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(3);
        $store->method('getGroup')->willReturn($this->groupMock(2, 'Main Store'));
        $this->storeManager->method('getStore')->with('vietnam')->willReturn($store);
    }

    /**
     * @return array<int, string> option value => label path (flattened)
     */
    private function flatten(array $options, string $prefix = ''): array
    {
        $flat = [];
        foreach ($options as $option) {
            $label = $prefix === '' ? $option['label'] : $prefix . ' > ' . $option['label'];
            $flat[$option['value']] = $label;
            foreach ($this->flatten($option['optgroup'] ?? [], $label) as $id => $path) {
                $flat[$id] = $path;
            }
        }

        return $flat;
    }

    public function testStoreScopeNestedOptionsAndRootNotSelectable(): void
    {
        $this->storeScope();

        $options = $this->providerWithItems([
            ['id' => 2, 'name' => 'Default Category', 'path' => '1/2', 'parent_id' => 1],
            ['id' => 20, 'name' => 'Women', 'path' => '1/2/20', 'parent_id' => 2],
            ['id' => 45, 'name' => 'Accessories', 'path' => '1/2/20/45', 'parent_id' => 20],
            ['id' => 12, 'name' => 'Bedroom', 'path' => '1/2/12', 'parent_id' => 2],
        ], [2 => '1/2'])->getOptions();

        // Store-scoped collection; LIKE prefixes use the root's STORED path.
        $this->assertSame([3], $this->storeIds);
        $this->assertStringContainsString("'1/2'", var_export($this->filters, true));

        // Nested optgroup hierarchy; root/global root never an option.
        $flat = $this->flatten($options);
        $this->assertArrayNotHasKey(2, $flat);
        $this->assertArrayNotHasKey(1, $flat);
        $this->assertSame('Women', $flat[20]);
        $this->assertSame('Women > Accessories', $flat[45]);
        $this->assertSame('Bedroom', $flat[12]);
        // Child option nested under its parent via optgroup.
        $this->assertSame(45, $options[0]['optgroup'][0]['value']);
    }

    public function testStoreScopePathFiltersUseStoredRootPath(): void
    {
        $this->storeScope();

        $this->providerWithItems([], [2 => '1/2'])->getOptions();

        $filters = var_export($this->filters, true);
        $this->assertStringContainsString("'path'", $filters);
        $this->assertStringContainsString("'1/2'", $filters);
        // Bare-id prefix (the 1.2.1 production bug) must never appear.
        $this->assertStringNotContainsString("'2/%'", str_replace("'1/2'", '', $filters));
    }

    public function testInactiveCategoriesFilteredOut(): void
    {
        $this->storeScope();

        $this->providerWithItems([], [2 => '1/2'])->getOptions();

        $isActive = array_values(array_filter(
            $this->filters,
            fn ($f) => $f[0] === 'is_active'
        ));
        $this->assertSame(1, count($isActive));
        $this->assertSame([1], array_values((array) $isActive[0][1]));
    }

    public function testWebsiteScopeSingleRootNoGroupWrapper(): void
    {
        $this->websiteParam = 'base';
        $website = $this->createMock(Website::class);
        $website->method('getGroups')->willReturn([$this->groupMock(2, 'Main Store'), $this->groupMock(2, 'Outlet')]);
        $this->storeManager->method('getWebsite')->with('base')->willReturn($website);

        $options = $this->providerWithItems([
            ['id' => 2, 'name' => 'Default Category', 'path' => '1/2', 'parent_id' => 1],
            ['id' => 20, 'name' => 'Women', 'path' => '1/2/20', 'parent_id' => 2],
        ], [2 => '1/2'])->getOptions();

        $flat = $this->flatten($options);
        $this->assertArrayNotHasKey('group_2', $flat);
        $this->assertSame('Women', $flat[20]);
    }

    public function testWebsiteScopeMultipleRootsLabeledGroupsOwnTreesOnly(): void
    {
        $this->websiteParam = 'base';
        $website = $this->createMock(Website::class);
        $website->method('getGroups')->willReturn([
            $this->groupMock(2, 'Fashion Store'),
            $this->groupMock(9, 'Home Store'),
        ]);
        $this->storeManager->method('getWebsite')->with('base')->willReturn($website);

        $options = $this->providerWithItems([
            ['id' => 2, 'name' => 'Root A', 'path' => '1/2', 'parent_id' => 1],
            ['id' => 20, 'name' => 'Women', 'path' => '1/2/20', 'parent_id' => 2],
            ['id' => 45, 'name' => 'Accessories', 'path' => '1/2/20/45', 'parent_id' => 20],
            ['id' => 9, 'name' => 'Root B', 'path' => '1/9', 'parent_id' => 1],
            ['id' => 90, 'name' => 'Living', 'path' => '1/9/90', 'parent_id' => 9],
        ], [2 => '1/2', 9 => '1/9'])->getOptions();

        $flat = $this->flatten($options);
        // Group headers wrap each tree; roots and foreign ids never appear.
        $this->assertSame('Fashion Store > Women > Accessories', $flat[45]);
        $this->assertSame('Home Store > Living', $flat[90]);
        $this->assertArrayNotHasKey(2, $flat);
        $this->assertArrayNotHasKey(9, $flat);
        $filters = var_export($this->filters, true);
        $this->assertStringContainsString("'1/2'", $filters);
        $this->assertStringContainsString("'1/9'", $filters);
    }

    public function testDefaultScopeIsLabeledUnionOfAllTrees(): void
    {
        $this->storeManager->method('getGroups')->willReturn([
            $this->groupMock(2, 'Fashion Store'),
            $this->groupMock(9, 'Home Store'),
        ]);

        $options = $this->providerWithItems([
            ['id' => 2, 'name' => 'Root A', 'path' => '1/2', 'parent_id' => 1],
            ['id' => 20, 'name' => 'Women', 'path' => '1/2/20', 'parent_id' => 2],
            ['id' => 9, 'name' => 'Root B', 'path' => '1/9', 'parent_id' => 1],
            ['id' => 90, 'name' => 'Living', 'path' => '1/9/90', 'parent_id' => 9],
        ], [2 => '1/2', 9 => '1/9'])->getOptions();

        $flat = $this->flatten($options);
        $this->assertSame('Fashion Store > Women', $flat[20]);
        $this->assertSame('Home Store > Living', $flat[90]);
    }

    public function testNestedSelectedIdsRoundTripInOptionValues(): void
    {
        $this->storeScope();

        // Saved config "20,45" must map onto existing option values; the
        // component checks those values and re-renders them as chips.
        $options = $this->providerWithItems([
            ['id' => 2, 'name' => 'Default Category', 'path' => '1/2', 'parent_id' => 1],
            ['id' => 20, 'name' => 'Women', 'path' => '1/2/20', 'parent_id' => 2],
            ['id' => 45, 'name' => 'Accessories', 'path' => '1/2/20/45', 'parent_id' => 20],
        ], [2 => '1/2'])->getOptions();

        $flat = $this->flatten($options);
        foreach ([20, 45] as $savedId) {
            $this->assertArrayHasKey($savedId, $flat);
        }
    }
}
