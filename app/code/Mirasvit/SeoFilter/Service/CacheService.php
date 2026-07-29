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
 * @package   mirasvit/module-seo-filter
 * @version   1.3.64
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoFilter\Service;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\CacheInterface;

class CacheService
{
    const REWRITE_CACHE_TTL = 86400;

    private $serializer;

    private $cache;

    private $memoryCache = [];

    public function __construct(
        Json $serializer,
        CacheInterface $cache
    ) {
        $this->serializer = $serializer;
        $this->cache      = $cache;
    }

    private function getCacheKey(string $instance, array $dataKey): string
    {
        $key = mb_strtoupper('mst_' . $instance . '_' . implode('_', $dataKey));

        // Cache instance doesn't distinguish between '-' and '_' symbols in identifier of load($identifier) method
        return str_replace('-', '--', $key);
    }

    public function getCache(string $instance, array $dataKey): ?array
    {
        $cacheKey = $this->getCacheKey($instance, $dataKey);

        if (isset($this->memoryCache[$cacheKey])) {
            return $this->memoryCache[$cacheKey];
        }

        $cachedData = $this->cache->load($cacheKey);
        if (empty($cachedData)) {
            return null;
        }

        $cachedData = $this->serializer->unserialize($cachedData);
        $cachedData = array_values($cachedData)[0];
        $result     = is_array($cachedData) ? $cachedData : [$cachedData];

        $this->memoryCache[$cacheKey] = $result;

        return $result;
    }

    public function clearCache(string $instance, array $dataKey): void
    {
        $cacheKey = $this->getCacheKey($instance, $dataKey);
        $this->cache->remove($cacheKey);
        unset($this->memoryCache[$cacheKey]);
    }

    public function setCache(string $instance, array $dataKey, array $dataValue, ?int $ttl = null): void
    {
        $cacheKey  = $this->getCacheKey($instance, $dataKey);
        $dataValue = array_values($dataValue)[0];

        $this->cache->save(
            $this->serializer->serialize([$dataValue]),
            $cacheKey,
            [],
            $ttl
        );

        $this->memoryCache[$cacheKey] = is_array($dataValue) ? $dataValue : [$dataValue];
    }
}
