<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Seo\Service\AutoRedirect;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Mirasvit\Seo\Model\Config;

/**
 * Resolves the redirect target for a removed (disabled/deleted) product, per store view.
 *
 * Priority order (PROP-0124):
 *   1. first active assigned category, by category-tree order;
 *   2. store-level configured fallback URL;
 *   3. homepage ("/").
 *
 * Returns a relative target path (leading "/"); the matcher prepends the store base URL.
 */
class TargetResolver
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CategoryCollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var UrlFinderInterface
     */
    private $urlFinder;

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(
        Config                    $config,
        CategoryCollectionFactory $categoryCollectionFactory,
        UrlFinderInterface        $urlFinder,
        ResourceConnection        $resource
    ) {
        $this->config                    = $config;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->urlFinder                 = $urlFinder;
        $this->resource                  = $resource;
    }

    /**
     * Category ids a product is assigned to, read from the committed catalog_category_product table
     * (the in-memory product's getCategoryIds() is unreliable across the multi-pass save).
     *
     * @return int[]
     */
    public function getProductCategoryIds(int $productId): array
    {
        $connection = $this->resource->getConnection();
        $select     = $connection->select()
            ->from($this->resource->getTableName('catalog_category_product'), ['category_id'])
            ->where('product_id = ?', $productId);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * @param int[] $categoryIds product's assigned category ids
     */
    public function resolve(array $categoryIds, int $storeId): string
    {
        $categoryPath = $this->resolveFirstActiveCategoryPath($categoryIds, $storeId);
        if ($categoryPath !== null) {
            return '/' . ltrim($categoryPath, '/');
        }

        $fallback = trim($this->config->getAutoRedirectFallbackUrl($storeId));
        if ($fallback !== '') {
            if (strpos($fallback, 'http://') === 0 || strpos($fallback, 'https://') === 0) {
                return $fallback;
            }

            return '/' . ltrim($fallback, '/');
        }

        return '/';
    }

    /**
     * First active assigned category, ordered by tree position, that has a storefront URL on the store.
     */
    private function resolveFirstActiveCategoryPath(array $categoryIds, int $storeId): ?string
    {
        $categoryIds = array_filter(array_map('intval', $categoryIds));
        if (!$categoryIds) {
            return null;
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId)
            ->addAttributeToFilter('entity_id', ['in' => $categoryIds])
            ->addAttributeToFilter('is_active', ['eq' => 1])
            ->addAttributeToSelect(['name', 'is_active'])
            ->addOrder('level', 'ASC')
            ->addOrder('path', 'ASC')
            ->addOrder('position', 'ASC');

        // Fetch all rewrite rows for the candidate category IDs in one query instead of N queries.
        $rewrites = $this->urlFinder->findAllByData([
            UrlRewrite::ENTITY_ID     => array_values($categoryIds),
            UrlRewrite::ENTITY_TYPE   => 'category',
            UrlRewrite::STORE_ID      => $storeId,
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);
        $rewriteByCategory = [];
        foreach ($rewrites as $rewrite) {
            $path = trim((string)$rewrite->getRequestPath());
            if ($path !== '') {
                $rewriteByCategory[(int)$rewrite->getEntityId()] = $path;
            }
        }

        foreach ($collection as $category) {
            if (!in_array($storeId, $category->getStoreIds())) {
                continue;
            }

            $categoryId = (int)$category->getId();
            if (isset($rewriteByCategory[$categoryId])) {
                return $rewriteByCategory[$categoryId];
            }
        }

        return null;
    }
}
