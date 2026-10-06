<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Certificate;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceMisa\Model\Client\MisaApiClient;
use Secomm\EInvoiceMisa\Model\Client\MisaTokenProvider;
use Throwable;

/**
 * Fetches and caches HSM certificates from MeInvoice per store.
 */
class CertificateListProvider
{
    private const CACHE_PREFIX = 'secomm_einvoice_misa_certificates_';
    private const CACHE_LIFETIME = 86400;

    public function __construct(
        private readonly MisaApiClient $apiClient,
        private readonly MisaTokenProvider $tokenProvider,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return HsmCertificate[]
     */
    public function getList(?int $storeId = null): array
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
                $this->logger->warning('Failed to read cached MISA certificates', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $rows = $this->fetchRows($storeId);
        $this->cache->save($this->json->serialize($rows), $cacheKey, [], self::CACHE_LIFETIME);

        return $this->buildList($rows);
    }

    public function invalidate(?int $storeId = null): void
    {
        $this->cache->remove(self::CACHE_PREFIX . (string) ($storeId ?? 0));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(?int $storeId): array
    {
        try {
            $token = $this->tokenProvider->getToken($storeId);
            $response = $this->apiClient->getCertificates($token, $storeId);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to fetch MISA HSM certificates', [
                'store_id' => $storeId,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (empty($response['success']) && empty($response['Success'])) {
            return [];
        }

        $data = $response['data'] ?? $response['Data'] ?? [];
        if (is_string($data) && $data !== '') {
            try {
                $decoded = $this->json->unserialize($data);
                $data = is_array($decoded) ? $decoded : [];
            } catch (Throwable) {
                $data = [];
            }
        }

        if (!is_array($data)) {
            return [];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * @param array<int, mixed> $rows
     * @return HsmCertificate[]
     */
    private function buildList(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cert = HsmCertificate::fromApiRow($row);
            if ($cert !== null) {
                $items[] = $cert;
            }
        }

        return $items;
    }
}
