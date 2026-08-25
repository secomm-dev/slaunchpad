<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Model;

use Magento\Framework\App\CacheInterface;
use Secomm\AiCommerce\Model\Cache\ResponseCache;

/**
 * Correctness-first invalidation helper.
 *
 * A product change can make a previously absent product ENTER a search
 * result (visibility/price/stock change), so product observers clean the
 * whole module tag — not only entries that contained the product. Store
 * tags exist for scope-restricted cases where the change provably affects
 * one store only.
 *
 * CacheInterface is the App\Cache\Proxy at runtime, whose clean($tags)
 * contract (deprecated but active) reinterprets Zend-style
 * clean($mode, $tags) by swallowing the mode string as a tag — tag
 * invalidation silently became a no-op. clean() is therefore called with
 * the plain tags array (SPEC-TASK-AIC-PDC1 §3.4, runtime-proven via
 * redis MONITOR).
 */
class InvalidateCache
{
    /**
     * @param CacheInterface $cache application cache backend
     * @param ResponseCache $responseCache cache id/tag authority
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ResponseCache $responseCache
    ) {
    }

    /**
     * Invalidate every cached /ai response (product/config/scope-wide changes).
     *
     * @return void
     */
    public function cleanAll(): void
    {
        $this->cache->clean([ResponseCache::CACHE_TAG]);
    }

    /**
     * Invalidate the cached /ai responses of a single store.
     *
     * @param int $storeId store view id
     * @return void
     */
    public function cleanStore(int $storeId): void
    {
        $this->cache->clean([$this->responseCache->storeTag($storeId)]);
    }
}
