<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Client;

use Magento\Framework\App\CacheInterface;

/**
 * Caches MISA access token per store (24h per vendor docs).
 */
class MisaTokenProvider
{
    private const CACHE_PREFIX = 'secomm_einvoice_misa_token_';
    private const CACHE_LIFETIME = 82800;

    /**
     * @param MisaApiClient $apiClient
     * @param CacheInterface $cache
     */
    public function __construct(
        private readonly MisaApiClient $apiClient,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Return cached token or fetch a new one from MISA.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getToken(?int $storeId = null): string
    {
        $cacheKey = self::CACHE_PREFIX . (string) ($storeId ?? 0);
        $cached = $this->cache->load($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->apiClient->getIntegrationToken($storeId);
        $this->cache->save($token, $cacheKey, [], self::CACHE_LIFETIME);

        return $token;
    }

    /**
     * Remove cached token for the store.
     *
     * @param int|null $storeId
     * @return void
     */
    public function invalidate(?int $storeId = null): void
    {
        $this->cache->remove(self::CACHE_PREFIX . (string) ($storeId ?? 0));
    }

    /**
     * Execute an API callback; refresh token once on HTTP 401 (token valid up to 14 days per MISA).
     *
     * @template T
     * @param callable(string): T $callback
     * @return T
     */
    public function executeWithToken(?int $storeId, callable $callback): mixed
    {
        try {
            return $callback($this->getToken($storeId));
        } catch (\Magento\Framework\Exception\LocalizedException $exception) {
            if (!str_contains($exception->getMessage(), '401')) {
                throw $exception;
            }
            $this->invalidate($storeId);

            return $callback($this->getToken($storeId));
        }
    }
}
