<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service\Source;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;
use Secomm\AiDiscoverability\Model\Config;
use Secomm\AiDiscoverability\Service\CanonicalPolicy;
use Secomm\AiDiscoverability\Service\SeoPolicy;

/**
 * Selected categories/collections: active, inside the store's category tree,
 * emitted with the Mirasvit-compatible canonical rewrite path (oldest non-redirect
 * url_rewrite row wins — SPEC-TASK-0X552E §3.1 / AC-006).
 */
class CategoriesSource
{
    public const MAX_ENTRIES = 20;

    /**
     * @param Config $config module configuration accessor
     * @param CategoryRepositoryInterface $categoryRepository category repository
     * @param CanonicalPolicy $canonicalPolicy canonical URL policy
     * @param SeoPolicy $seoPolicy noindex/trailing-slash policy adapter
     * @param LoggerInterface $logger PSR logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CanonicalPolicy $canonicalPolicy,
        private readonly SeoPolicy $seoPolicy,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Emit canonical entries for the store's selected categories.
     *
     * @param StoreInterface $store store view scope
     * @return array<int, array{label: string, url: string}>
     */
    public function getEntries(StoreInterface $store): array
    {
        $storeId = (int) $store->getId();
        $rootPath = $this->getRootCategoryPath($store);

        if ($rootPath === null) {
            return [];
        }

        $entries = [];

        foreach ($this->config->getCategoryIds($storeId) as $categoryId) {
            if (count($entries) >= self::MAX_ENTRIES) {
                break;
            }

            try {
                $category = $this->categoryRepository->get($categoryId, $storeId);
            } catch (LocalizedException $e) {
                continue;
            }

            if (!(bool) $category->getIsActive()) {
                continue;
            }
            if (!$this->isInStoreTree($category, $rootPath)) {
                continue;
            }

            $url = $this->canonicalPolicy->getCategoryUrl($categoryId, $store);

            if ($url === null) {
                continue;
            }
            // Path component only (scheme/host stripped, query string removed).
            $noindexPath = (string) preg_replace('~^[a-z][a-z0-9+.\-]*://[^/?]*~i', '', explode('?', $url, 2)[0]);
            if ($this->seoPolicy->isNoindexed($noindexPath, $storeId)) {
                continue;
            }

            $entries[] = [
                'label' => (string) ($category->getName() !== '' ? $category->getName() : (string) $categoryId),
                'url' => $url,
            ];
        }

        return $entries;
    }

    /**
     * Materialized path of the store group's root category, null when unavailable.
     *
     * @param StoreInterface $store store view scope
     * @return string|null root category materialized path
     */
    private function getRootCategoryPath(StoreInterface $store): ?string
    {
        try {
            $rootCategoryId = (int) $store->getGroup()->getRootCategoryId();
            $root = $this->categoryRepository->get($rootCategoryId, (int) $store->getId());

            return (string) $root->getPath();
        } catch (LocalizedException $e) {
            $this->logger->warning(
                'LC-30: unable to resolve root category path for store {store}',
                ['store' => $store->getId()]
            );

            return null;
        }
    }

    /**
     * Whether a category lies inside the store's category tree.
     *
     * @param CategoryInterface $category candidate category
     * @param string $rootPath store root category materialized path
     * @return bool true when inside the tree
     */
    private function isInStoreTree(CategoryInterface $category, string $rootPath): bool
    {
        $path = (string) $category->getPath();

        return $path === $rootPath || str_starts_with($path, $rootPath . '/');
    }
}
