<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Model\Config;

use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Nested ui-select options of curatable categories for the admin config
 * selector (SPEC-TASK-5TGJ7V correction).
 *
 * Produces the exact option structure the core Product Edit Categories field
 * consumes (\Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Categories::
 * retrieveCategoriesTree(): nested {value, label, optgraph[]} folded from ONE
 * collection via parent references). Rendered by the core component
 * Magento_Ui/js/form/element/ui-select (chips + search + checkbox tree + Done).
 *
 * Scope resolution follows \Magento\Config\Block\System\Config\Form
 * (website/store request params); tree filtering follows the core Flat
 * invariant proven in SPEC-TASK-QQMVY4 (LIKE prefix = the root's STORED path
 * "1/<rootId>", never the bare id). The tree root itself is NOT an option
 * (Product-Edit root context, non-selectable by construction); only its
 * descendants are. Foreign trees are absent because of the path filter.
 */
class CategoryTreeProvider
{
    private const GLOBAL_ROOT_ID = 1;

    /**
     * @param CategoryCollectionFactory $collectionFactory category collection factory
     * @param StoreManagerInterface $storeManager store/group/website registry
     * @param RequestInterface $request admin request (config section scope params)
     */
    public function __construct(
        private readonly CategoryCollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Ui-select options for the effective scope's category tree(s).
     *
     * Single tree: top-level options are the root's children (the root itself
     * is never an option → never selectable/savable). Multiple trees (website
     * with distinct roots / default scope): each tree is wrapped under a
     * non-category label header option ("group_<rootId>" — a non-numeric
     * value, filtered out before save and ignored by the generator).
     *
     * @return array<int, array> nested {value, label, optgraph?} options
     */
    public function getOptions(): array
    {
        $scope = $this->resolveScope();
        $rootPaths = $this->rootPaths($scope['root_ids']);

        if ($rootPaths === []) {
            return [];
        }

        [$nodes, $parents] = $this->categoryOptions(
            $this->scopedCollection((int) $scope['store_id'], $rootPaths)
        );

        if (count($rootPaths) < 2) {
            // Single tree: the root's children are the top level (the root is
            // structural context only — Product Edit shows it, we omit it so
            // it can never be curated). Degenerate fallback (root inactive/
            // absent): orphaned top nodes.
            $rootId = (int) array_key_first($rootPaths);
            if (isset($nodes[$rootId])) {
                return $nodes[$rootId]['optgroup'] ?? [];
            }

            return array_values(array_filter(
                $nodes,
                static fn (array $node, int $id): bool => !isset($parents[$id]),
                ARRAY_FILTER_USE_BOTH
            ));
        }

        $trees = [];
        foreach ($rootPaths as $rootId => $rootPath) {
            if (!isset($nodes[$rootId])) {
                continue;
            }
            $trees[] = [
                'value' => 'group_' . $rootId,
                'label' => (string) ($scope['root_labels'][$rootId] ?? $rootId),
                'optgroup' => $nodes[$rootId]['optgroup'] ?? [],
            ];
        }

        return $trees;
    }

    /**
     * Fold ONE scoped collection into id => ui-select option with children
     * linked by parent references (core retrieveCategoriesTree fold; children
     * ordered by position via the collection sort).
     *
     * @param CategoryCollection $collection scoped active categories incl. roots
     * @return array{0: array<int, array>, 1: array<int, int>} [id => option, id => parent id]
     */
    private function categoryOptions(CategoryCollection $collection): array
    {
        $nodes = [];
        $parents = [];

        foreach ($collection->getItems() as $category) {
            $id = (int) $category->getId();
            $nodes[$id] = [
                'value' => $id,
                'label' => (string) ($category->getName() !== null ? $category->getName() : (string) $id),
            ];
            $parents[$id] = (int) $category->getParentId();
        }

        foreach ($collection->getItems() as $category) {
            $id = (int) $category->getId();
            $parentId = (int) $category->getParentId();

            if ($id === $parentId || !isset($nodes[$id]) || !isset($nodes[$parentId])) {
                continue;
            }
            $nodes[$parentId]['optgroup'][] = &$nodes[$id];
        }

        return [$nodes, $parents];
    }

    /**
     * The single scoped collection: active categories of the root tree(s) with
     * the roots loaded (fold needs them as parents) but never emitted as
     * options — getOptions() only unwraps their children.
     *
     * @param int $storeId store id (0 = admin default)
     * @param array $rootPaths root id => stored path
     * @return CategoryCollection
     */
    private function scopedCollection(int $storeId, array $rootPaths): CategoryCollection
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(['name', 'is_active', 'position', 'parent_id']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter('entity_id', ['nin' => [self::GLOBAL_ROOT_ID]]);

        // Core Flat pattern (SPEC-TASK-QQMVY4): LIKE prefix = the root's own
        // STORED path ("1/<rootId>"), never the bare id. The root itself is
        // matched (equality) so the fold has its parent option.
        $conditions = [];
        foreach ($rootPaths as $rootPath) {
            $conditions[] = ['attribute' => 'path', 'like' => $rootPath];
            $conditions[] = ['attribute' => 'path', 'like' => $rootPath . '/%'];
        }
        $collection->addAttributeToFilter($conditions);
        $collection->addAttributeToSort('position');

        return $collection;
    }

    /**
     * Resolve the editable config scope from the section request.
     *
     * @return array scope data: store_id (int, 0 = admin default),
     * root_ids (int[]), root_labels (root id => store-group name)
     */
    private function resolveScope(): array
    {
        $storeCode = (string) $this->request->getParam('store');

        if ($storeCode !== '') {
            $store = $this->storeManager->getStore($storeCode);
            $rootId = (int) $store->getGroup()->getRootCategoryId();

            return ['store_id' => (int) $store->getId(), 'root_ids' => [$rootId], 'root_labels' => []];
        }

        $websiteCode = (string) $this->request->getParam('website');

        if ($websiteCode !== '') {
            $groups = $this->storeManager->getWebsite($websiteCode)->getGroups();
        } else {
            $groups = $this->storeManager->getGroups();
        }

        $rootIds = [];
        $rootLabels = [];
        foreach ($groups as $group) {
            if (!$group instanceof GroupInterface) {
                continue;
            }
            $rootId = (int) $group->getRootCategoryId();
            if ($rootId > 0) {
                $rootIds[$rootId] = $rootId;
                $rootLabels[$rootId] = (string) $group->getName();
            }
        }

        return ['store_id' => 0, 'root_ids' => array_values($rootIds), 'root_labels' => $rootLabels];
    }

    /**
     * Stored paths of the tree roots (SPEC-TASK-QQMVY4 invariant).
     *
     * ONE bounded id-list collection (roots are few); no per-root loads.
     *
     * @param int[] $rootIds resolved tree roots
     * @return array<int, string> root id => stored path (e.g. 2 => "1/2")
     */
    private function rootPaths(array $rootIds): array
    {
        if ($rootIds === []) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $rootIds]);

        $paths = [];
        foreach ($collection->getItems() as $category) {
            $paths[(int) $category->getId()] = (string) $category->getPath();
        }

        return $paths;
    }
}
