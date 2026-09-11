<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Model\Client;

use Magento\Framework\HTTP\Client\CurlFactory;
use Secomm\FulfillmentCore\Model\Log\FulfillmentLogger;
use Secomm\Pancake\Api\PosApiConfigInterface;
use Secomm\Pancake\Model\ServiceCode;

/**
 * Pancake POS HTTP client. api_key is query-only and never logged.
 */
class PosClient
{
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly PosApiConfigInterface $config,
        private readonly FulfillmentLogger $fulfillmentLogger
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload, ?int $storeId = null): array
    {
        $shopId = $this->config->getShopId($storeId);
        return $this->request('POST', '/shops/' . rawurlencode($shopId) . '/orders', $storeId, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(string $externalOrderId, ?int $storeId = null): array
    {
        $shopId = $this->config->getShopId($storeId);
        return $this->request(
            'GET',
            '/shops/' . rawurlencode($shopId) . '/orders/' . rawurlencode($externalOrderId),
            $storeId
        );
    }

    /**
     * @param array<string, scalar|array> $query
     * @return array<string, mixed>
     */
    public function listOrders(array $query, ?int $storeId = null): array
    {
        $shopId = $this->config->getShopId($storeId);
        return $this->request('GET', '/shops/' . rawurlencode($shopId) . '/orders', $storeId, null, $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function listWarehouses(?int $storeId = null): array
    {
        $shopId = $this->config->getShopId($storeId);
        return $this->request('GET', '/shops/' . rawurlencode($shopId) . '/warehouses', $storeId);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, scalar|array> $extraQuery
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        ?int $storeId,
        ?array $body = null,
        array $extraQuery = []
    ): array {
        $apiKey = $this->config->getApiKey($storeId);
        if ($apiKey === '' || $this->config->getShopId($storeId) === '') {
            throw new PosClientException('pancake_not_configured');
        }

        $query = array_merge(['api_key' => $apiKey], $extraQuery);
        $url = $this->config->getBaseUrl($storeId) . $path . '?' . http_build_query($query);

        $curl = $this->curlFactory->create();
        $curl->setOptions([
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $curl->addHeader('Content-Type', 'application/json');
        $curl->addHeader('Accept', 'application/json');

        try {
            if ($method === 'POST') {
                $curl->post($url, (string) json_encode($body ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            } else {
                $curl->get($url);
            }
        } catch (\JsonException) {
            throw new PosClientException('pancake_json_encode_failed');
        } catch (\Throwable) {
            throw new PosClientException('pancake_network_error');
        }

        $status = (int) $curl->getStatus();
        $rawBody = (string) $curl->getBody();
        $hint = $status >= 400 ? $this->errorHint($rawBody) : '';
        $this->fulfillmentLogger->info(
            ServiceCode::CODE,
            'Pancake POS HTTP call.',
            ['method' => $method, 'path' => $path, 'http_status' => $status, 'error_hint' => $hint]
        );

        if ($status === 401 || $status === 403) {
            throw new PosClientException('pancake_auth_failed');
        }
        if ($status === 0 || $status >= 500) {
            throw new PosClientException('pancake_http_' . $status . ($hint !== '' ? ':' . $hint : ''));
        }
        if ($status >= 400) {
            throw new PosClientException('pancake_http_' . $status . ($hint !== '' ? ':' . $hint : ''));
        }

        $decoded = json_decode((string) $curl->getBody(), true);
        if (!is_array($decoded)) {
            throw new PosClientException('pancake_invalid_json');
        }

        return $decoded;
    }

    /**
     * Short vendor message for logs and last_error. Never includes api_key or raw phone.
     *
     * @param string $body Raw HTTP body
     */
    private function errorHint(string $body): string
    {
        $body = str_replace(["\r", "\n"], ' ', $body);
        $body = (string) preg_replace('/api_key=[^&\s"]+/i', 'api_key=<redacted>', $body);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return $this->clip($body);
        }

        $parts = [];
        foreach (['message', 'error', 'msg'] as $key) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key]) && (string) $decoded[$key] !== '') {
                $parts[] = (string) $decoded[$key];
            }
        }
        if (isset($decoded['errors'])) {
            $encoded = json_encode($decoded['errors'], JSON_UNESCAPED_UNICODE);
            if (is_string($encoded) && $encoded !== '' && $encoded !== 'null') {
                $parts[] = $encoded;
            }
        }

        return $this->clip($parts === [] ? $body : implode(' | ', $parts));
    }

    /**
     * Clip and mask digit runs that may be phone numbers.
     */
    private function clip(string $text): string
    {
        $text = (string) preg_replace('/\+?\d[\d\s\-().]{7,}\d/', '<phone>', $text);
        if (strlen($text) > 160) {
            return substr($text, 0, 160);
        }

        return $text;
    }
}
