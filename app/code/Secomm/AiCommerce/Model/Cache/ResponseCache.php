<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Internal per-store/per-route response cache.
 *
 * Key: secomm_aic_s{storeId}_{route}_{sha1(normalized params)} — store and
 * every normalized query parameter are part of the cache identity, so no
 * cross-store/cross-query contamination is possible. Tags: module tag plus
 * store tag (correctness-first invalidation: product/category/config
 * observers clean the whole module tag, because a product change can make a
 * previously absent product ENTER a search result — see plan rev 2 §4).
 */
class ResponseCache
{
    public const CACHE_TAG = 'secomm_aic';

    /**
     * @param CacheInterface $cache application cache backend
     * @param Json $json json serializer
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $json
    ) {
    }

    /**
     * Cache tag identifying one store's responses.
     *
     * @param int $storeId store view id
     * @return string store tag
     */
    public function storeTag(int $storeId): string
    {
        return self::CACHE_TAG . '_store_' . $storeId;
    }

    /**
     * Load a cached response body for a route + normalized params.
     *
     * @param int $storeId store view id
     * @param string $route route name (store|search|product|categories)
     * @param mixed[] $params normalized request parameters
     * @return mixed[]|null cached DTO array or null on miss
     */
    public function load(int $storeId, string $route, array $params): ?array
    {
        $raw = $this->cache->load($this->key($storeId, $route, $params));

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $data = $this->json->unserialize($raw);

        return is_array($data) ? $data : null;
    }

    /**
     * Save a response body for a route + normalized params.
     *
     * @param mixed[] $data DTO array
     * @param int $storeId store view id
     * @param string $route route name
     * @param mixed[] $params normalized request parameters
     * @param int $lifetime cache lifetime in seconds
     * @return void
     */
    public function save(array $data, int $storeId, string $route, array $params, int $lifetime): void
    {
        if ($lifetime <= 0) {
            return;
        }

        $this->cache->save(
            $this->json->serialize($data),
            $this->key($storeId, $route, $params),
            [self::CACHE_TAG, $this->storeTag($storeId)],
            $lifetime
        );
    }

    /**
     * Deterministic cache key from store + route + sorted params.
     *
     * @param int $storeId store view id
     * @param string $route route name
     * @param mixed[] $params normalized request parameters
     * @return string cache key
     */
    private function key(int $storeId, string $route, array $params): string
    {
        unset($params['store']);
        ksort($params);

        return self::CACHE_TAG . '_s' . $storeId . '_' . $route . '_' . sha1((string) $this->json->serialize($params));
    }
}
