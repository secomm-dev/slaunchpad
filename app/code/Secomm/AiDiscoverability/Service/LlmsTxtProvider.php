<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service;

use Magento\Framework\App\CacheInterface;
use Secomm\AiDiscoverability\Model\Config;

/**
 * Cache boundary of the public /llms.txt endpoint: store-scoped cache ID,
 * stable custom tag, lazy regeneration on miss/expiry — no cron, no broad
 * flushes (SPEC-TASK-0X552E §4.4).
 */
class LlmsTxtProvider
{
    public const CACHE_TAG = 'seocomm_llms';
    public const CACHE_ID_PREFIX = 'seocomm_llms_txt_store_';

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var LlmsTxtGenerator
     */
    private $generator;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param CacheInterface $cache application cache backend
     * @param LlmsTxtGenerator $generator llms.txt body generator
     * @param Config $config module configuration accessor
     */
    public function __construct(CacheInterface $cache, LlmsTxtGenerator $generator, Config $config)
    {
        $this->cache = $cache;
        $this->generator = $generator;
        $this->config = $config;
    }

    /**
     * Cached llms.txt body for a store view; regenerates lazily on miss.
     *
     * @param int $storeId store view id
     * @return string llms.txt body
     */
    public function get(int $storeId): string
    {
        $cacheId = $this->cacheId($storeId);
        $cached = $this->cache->load($cacheId);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $body = $this->generator->generate($storeId);
        $this->cache->save(
            $body,
            $cacheId,
            [self::CACHE_TAG, $this->storeTag($storeId)],
            $this->config->getCacheLifetime($storeId)
        );

        return $body;
    }

    /**
     * Generate fresh, bypassing the public response cache (admin preview seam).
     *
     * @param int $storeId store view id
     * @return string llms.txt body
     */
    public function getFresh(int $storeId): string
    {
        return $this->generator->generate($storeId);
    }

    /**
     * Cache identifier of a store's cached llms.txt body.
     *
     * @param int $storeId store view id
     * @return string cache identifier
     */
    public function cacheId(int $storeId): string
    {
        return self::CACHE_ID_PREFIX . $storeId;
    }

    /**
     * Store-scoped cache tag for targeted invalidation.
     *
     * @param int $storeId store view id
     * @return string cache tag
     */
    public function storeTag(int $storeId): string
    {
        return self::CACHE_TAG . '_store_' . $storeId;
    }
}
