<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Catalog;

use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Service\Response\CategoryDto;
use Secomm\AiCommerce\Service\Url\PublicUrlResolver;

/**
 * Single-load store category tree (plan rev 2 §2b "Category tree").
 *
 * Exactly ONE bounded category collection query loads the whole visible
 * tree (explicit attributes, store scope, active only, store-root path
 * filter); the hierarchy is then assembled in memory. No repository
 * recursion, no per-node loads.
 */
class CategoryTreeService
{
    private const SELECT_ATTRIBUTES = ['name', 'url_key', 'is_active', 'path', 'parent_id', 'position'];

    /**
     * @param CategoryCollectionFactory $collectionFactory category collection factory
     * @param Config $config module config (depth bound)
     * @param PublicUrlResolver $urlResolver store-scoped public/canonical URLs
     * @param CategoryDto $categoryDto category node DTO builder
     */
    public function __construct(
        private readonly CategoryCollectionFactory $collectionFactory,
        private readonly Config $config,
        private readonly PublicUrlResolver $urlResolver,
        private readonly CategoryDto $categoryDto
    ) {
    }

    /**
     * Build the active category tree of a store (one collection load).
     *
     * @param StoreInterface $store resolved store view
     * @return array root-level category node DTOs
     */
    public function getTree(StoreInterface $store): array
    {
        $storeId = (int) $store->getId();
        $rootId = (int) $store->getRootCategoryId();
        $rootPath = $this->rootPath($storeId, $rootId);

        if ($rootPath === null) {
            return [];
        }

        $nodes = $this->loadNodes($storeId, $rootPath, $rootId);

        return $this->buildChildren($store, $nodes, $rootId, $rootPath, $this->config->getCategoryDepth($storeId));
    }

    /**
     * Load every active category of the store tree with one query.
     *
     * @param int $storeId store view id
     * @param string $rootPath entity path of the store root category
     * @param int $rootId store root category id
     * @return array raw nodes keyed by entity id
     */
    private function loadNodes(int $storeId, string $rootPath, int $rootId): array
    {
        /** @var CategoryCollection $collection */
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(self::SELECT_ATTRIBUTES);
        $collection->addAttributeToFilter('path', ['like' => $rootPath . '/%']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter('entity_id', ['neq' => $rootId]);
        $collection->addAttributeToSort('position', 'asc');

        $nodes = [];

        foreach ($collection as $category) {
            $nodes[(int) $category->getId()] = [
                'id' => (int) $category->getId(),
                'parent_id' => (int) $category->getParentId(),
                'name' => (string) $category->getName(),
            ];
        }

        return $nodes;
    }

    /**
     * Assemble the tree in memory from parent links, bounded by depth.
     *
     * @param StoreInterface $store resolved store view
     * @param array $nodes raw nodes
     * @param int $rootId store root category id
     * @param string $rootPath entity path of the store root
     * @param int $maxDepth maximum depth below the root
     * @return array node DTOs
     */
    private function buildChildren(
        StoreInterface $store,
        array $nodes,
        int $rootId,
        string $rootPath,
        int $maxDepth
    ): array {
        $byParent = [];

        foreach ($nodes as $node) {
            $byParent[$node['parent_id']][] = $node['id'];
        }

        $rootDepth = count(explode('/', $rootPath));

        return $this->buildLevel($store, $nodes, $byParent, $rootId, $rootDepth, $rootDepth, $maxDepth);
    }

    /**
     * Recursively build the children of one parent, depth-bounded.
     *
     * @param StoreInterface $store resolved store view
     * @param array $nodes raw nodes
     * @param array $byParent parent id => child id list
     * @param int $parentId parent category id
     * @param int $depth absolute depth of the parent
     * @param int $rootDepth absolute depth of the store root
     * @param int $maxDepth maximum depth below the store root
     * @return array node DTOs
     */
    private function buildLevel(
        StoreInterface $store,
        array $nodes,
        array $byParent,
        int $parentId,
        int $depth,
        int $rootDepth,
        int $maxDepth
    ): array {
        if ($depth >= $maxDepth + $rootDepth) {
            return [];
        }

        $result = [];

        foreach ($byParent[$parentId] ?? [] as $childId) {
            $node = $nodes[$childId];
            $result[] = $this->categoryDto->toArray(
                $node['id'],
                $node['name'],
                $this->urlResolver->getCategoryUrls($node['id'], $store),
                $this->buildLevel($store, $nodes, $byParent, $childId, $depth + 1, $rootDepth, $maxDepth)
            );
        }

        return $result;
    }

    /**
     * Entity path of the store root category.
     *
     * @param int $storeId store view id
     * @param int $rootId store root category id
     * @return string|null path or null when the root cannot be resolved
     */
    private function rootPath(int $storeId, int $rootId): ?string
    {
        /** @var CategoryCollection $collection */
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(['path']);
        $collection->addAttributeToFilter('entity_id', $rootId);
        $category = $collection->getFirstItem();

        $path = (string) $category->getPath();

        return $path !== '' ? $path : null;
    }
}
