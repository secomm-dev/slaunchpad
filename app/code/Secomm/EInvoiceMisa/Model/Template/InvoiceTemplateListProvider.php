<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Template;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceMisa\Model\Client\MisaApiClient;
use Secomm\EInvoiceMisa\Model\Client\MisaTokenProvider;
use Throwable;

/**
 * Fetches and caches the invoice template list from MeInvoice per store.
 */
class InvoiceTemplateListProvider
{
    private const CACHE_PREFIX = 'secomm_einvoice_misa_templates_';
    private const CACHE_LIFETIME = 1800;

    /**
     * @param MisaApiClient $apiClient
     * @param MisaTokenProvider $tokenProvider
     * @param CacheInterface $cache
     * @param Json $json
     * @param InvoiceTemplateListFactory $listFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly MisaApiClient $apiClient,
        private readonly MisaTokenProvider $tokenProvider,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly InvoiceTemplateListFactory $listFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Return the invoice template list for the store (cached).
     *
     * @param int|null $storeId
     * @return InvoiceTemplateList
     */
    public function getList(?int $storeId = null): InvoiceTemplateList
    {
        $cacheKey = self::CACHE_PREFIX . (string) ($storeId ?? 0);
        $cached = $this->cache->load($cacheKey);
        if (is_string($cached) && $cached !== '') {
            try {
                $rows = $this->json->unserialize($cached);
                if (is_array($rows)) {
                    return $this->buildList($rows);
                }
            } catch (Throwable $exception) {
                $this->logger->warning('Failed to read cached MISA templates', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $rows = $this->fetchRows($storeId);
        $this->cache->save($this->json->serialize($rows), $cacheKey, [], self::CACHE_LIFETIME);

        return $this->buildList($rows);
    }

    /**
     * Drop the cached template list for the store.
     *
     * @param int|null $storeId
     * @return void
     */
    public function invalidate(?int $storeId = null): void
    {
        $this->cache->remove(self::CACHE_PREFIX . (string) ($storeId ?? 0));
    }

    /**
     * Fetch raw template rows from MeInvoice API.
     *
     * @param int|null $storeId
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(?int $storeId): array
    {
        try {
            $token = $this->tokenProvider->getToken($storeId);
            $response = $this->apiClient->getTemplates($token, $storeId);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to fetch MISA invoice templates', [
                'store_id' => $storeId,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        $data = $response['data'] ?? [];
        if (is_string($data) && $data !== '') {
            try {
                $decoded = $this->json->unserialize($data);
                $data = is_array($decoded) ? $decoded : [];
            } catch (Throwable $exception) {
                $data = [];
            }
        }

        if (!is_array($data)) {
            return [];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * Build the template list value object from raw rows.
     *
     * @param array<int, mixed> $rows
     * @return InvoiceTemplateList
     */
    private function buildList(array $rows): InvoiceTemplateList
    {
        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = InvoiceTemplate::fromApiRow($row);
            }
        }

        return $this->listFactory->create(['items' => $items]);
    }
}
