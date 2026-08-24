<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Model;

use Magento\Framework\App\CacheInterface;
use Secomm\AiDiscoverability\Service\LlmsTxtProvider;

/**
 * Targeted tag-based invalidation helper. Observers only call clean*() here —
 * no regeneration happens in observers (lazy regen on next request).
 */
class InvalidateCache
{
    /**
     * @param CacheInterface $cache application cache backend
     * @param LlmsTxtProvider $provider cache id/tag authority
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LlmsTxtProvider $provider
    ) {
    }

    /**
     * Invalidate a single store's cached llms.txt.
     *
     * @param int $storeId store view id
     * @return void
     */
    public function cleanStore(int $storeId): void
    {
        $this->cache->clean(
            \Zend_Cache::CLEANING_MODE_MATCHING_TAG,
            [$this->provider->storeTag($storeId)]
        );
    }

    /**
     * Invalidate the cached llms.txt of each given store.
     *
     * @param int[] $storeIds store view ids
     * @return void
     */
    public function cleanStores(array $storeIds): void
    {
        foreach (array_unique(array_map('intval', $storeIds)) as $storeId) {
            $this->cleanStore($storeId);
        }
    }

    /**
     * Invalidate every store (only for scope-unspecific changes).
     *
     * @return void
     */
    public function cleanAll(): void
    {
        $this->cache->clean(
            \Zend_Cache::CLEANING_MODE_MATCHING_TAG,
            [LlmsTxtProvider::CACHE_TAG]
        );
    }
}
