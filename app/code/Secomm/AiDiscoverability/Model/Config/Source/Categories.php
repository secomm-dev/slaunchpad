<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Admin config multiselect of curatable categories, scoped to the category
 * tree(s) of the configuration scope being edited (SPEC-TASK-S7MFCT).
 *
 * Scope resolution follows the core mechanism of
 * \Magento\Config\Block\System\Config\Form (website/store request params of
 * the config section URL):
 * - store view scope → only that store group's root tree; the root itself is
 *   never selectable;
 * - website scope → that website's group roots: one distinct root behaves like
 *   the store scope, multiple distinct roots emit the labeled UNION of the
 *   website's trees (never one tree silently, never foreign websites);
 * - default scope → labeled union of all trees (no fake store invented).
 *
 * Labels are breadcrumbs (Parent > Child) built from the `path` column and the
 * id→name map of the SAME collection — one bounded query, no N+1. The tree
 * root name is only prepended when several trees are mixed and disambiguation
 * is required.
 */
class Categories implements OptionSourceInterface
{
    private const GLOBAL_ROOT_ID = 1;
    private const LABEL_SEPARATOR = ' > ';

    /**
     * @var array|null
     */
    private ?array $options = null;

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
     * Scope-aware active categories as multiselect options, sorted by label.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $scope = $this->resolveScope();
        $rootIds = $scope['root_ids'];
        $rootLabels = $scope['root_labels'];

        $collection = $this->collectionFactory->create();
        $collection->setStoreId($scope['store_id']);
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter('entity_id', ['nin' => $this->excludedIds($rootIds)]);
        $conditions = array_map(
            static fn (int $rootId): array => ['attribute' => 'path', 'like' => $rootId . '/%'],
            $rootIds
        );
        $collection->addAttributeToFilter($conditions);

        $items = $collection->getItems();

        // id => name map from the same (single) collection for breadcrumbs.
        $names = [];
        foreach ($items as $category) {
            $names[(int) $category->getId()] = (string) $category->getName();
        }

        $this->options = [];
        foreach ($items as $category) {
            $this->options[] = [
                'value' => (string) $category->getId(),
                'label' => $this->buildLabel(
                    (string) $category->getPath(),
                    (int) $category->getId(),
                    $names,
                    $rootIds,
                    $rootLabels
                ),
            ];
        }

        usort($this->options, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $this->options;
    }

    /**
     * Resolve the editable config scope from the section request.
     *
     * @return array scope data: store_id (int, 0 = admin default),
     * root_ids (int[], resolved tree roots), root_labels (root id => store-group
     * name, only populated when several trees are mixed)
     */
    private function resolveScope(): array
    {
        $storeCode = (string) $this->request->getParam('store');

        if ($storeCode !== '') {
            $store = $this->storeManager->getStore($storeCode);
            $group = $store->getGroup();
            $rootId = (int) $group->getRootCategoryId();

            // Single tree: no group-name prefix needed.
            return ['store_id' => (int) $store->getId(), 'root_ids' => [$rootId], 'root_labels' => []];
        }

        $websiteCode = (string) $this->request->getParam('website');

        if ($websiteCode !== '') {
            $website = $this->storeManager->getWebsite($websiteCode);
            $groups = $website->getGroups();
        } else {
            // Default scope: union of every group-root tree, still root-excluded.
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

        // Only mixed trees need the group-name prefix for disambiguation.
        if (count($rootIds) < 2) {
            $rootLabels = [];
        }

        return ['store_id' => 0, 'root_ids' => array_values($rootIds), 'root_labels' => $rootLabels];
    }

    /**
     * Ids never offered as options: the global root plus the tree roots
     * themselves (path LIKE "root/%" already excludes them; the explicit nin
     * guards against a root whose path shape ever differs).
     *
     * @param int[] $rootIds resolved tree roots
     * @return int[]
     */
    private function excludedIds(array $rootIds): array
    {
        return array_values(array_unique(array_merge([self::GLOBAL_ROOT_ID], $rootIds)));
    }

    /**
     * Breadcrumb label from the category path.
     *
     * @param string $path category path (e.g. "1/2/20/45")
     * @param int $categoryId category entity id
     * @param array $names id => name map of the same collection
     * @param int[] $rootIds resolved tree roots
     * @param array $rootLabels root id => store-group name (mixed trees only)
     * @return string disambiguated label
     */
    private function buildLabel(string $path, int $categoryId, array $names, array $rootIds, array $rootLabels): string
    {
        $segments = [];
        $prefix = '';

        foreach (explode('/', $path) as $rawId) {
            $id = (int) $rawId;
            if ($id === self::GLOBAL_ROOT_ID) {
                continue;
            }
            if (in_array($id, $rootIds, true)) {
                // Tree root: becomes the label prefix only on mixed trees.
                if (isset($rootLabels[$id])) {
                    $prefix = $rootLabels[$id];
                }
                continue;
            }
            if (isset($names[$id]) && $names[$id] !== '') {
                $segments[] = $names[$id];
            }
        }

        if ($prefix !== '') {
            array_unshift($segments, $prefix);
        }

        $label = implode(self::LABEL_SEPARATOR, $segments);
        if ($label === '') {
            $name = $names[$categoryId] ?? '';
            $label = $name !== '' ? $name : sprintf('[ID: %d]', $categoryId);
        }

        return $label;
    }
}
