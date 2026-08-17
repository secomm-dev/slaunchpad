<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model;

use Magento\Framework\HTTP\Client\CurlFactory;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Log\MaskingLogger;

/**
 * Single GHTK API client abstraction (DEC-023 — base URI locked here). Transport
 * only (SL-015): HTTP, auth headers, timeouts, one retry on network/5xx errors
 * (never on 4xx), masked logging. Business payload assembly lives in
 * Model\Fee\FeeRequestMapper; origin resolution in the ShippingCore provider
 * chain — this class resolves nothing and builds no business payload.
 * Never logs the raw payload or decrypted token.
 */
class GhtkApiClient
{
    private const FEE_PATH = '/services/shipment/fee';
    private const ORDER_PATH = '/services/shipment/order';

    public function __construct(
        private CurlFactory $curlFactory,
        private GhtkConfig $config,
        private MaskingLogger $logger
    ) {
    }

    /**
     * @param array<string, string|int|float> $params Mapped GHTK fee query params (see FeeRequestMapper).
     * @param int|null $storeId Store scope for API config reads (token/base URL); null = default scope.
     * @return array Decoded fee response (full); the caller (FeeResponseMapper) extracts the `fee` object.
     * @throws GhtkApiException On non-recoverable failure (network exhausted / 4xx / bad JSON).
     */
    public function getFee(array $params, ?int $storeId = null): array
    {
        $url = $this->config->getApiBaseUrl($storeId) . self::FEE_PATH . '?' . http_build_query($params);
        $attempt = 0;
        $max = $this->config->getRetryMax($storeId);

        while (true) {
            try {
                return $this->call($url, $storeId);
            } catch (GhtkApiException $e) {
                if (!$e->isRetryable() || $attempt >= $max) {
                    throw $e;
                }
                $attempt++;
                $this->logger->warning(
                    'GHTK fee call retrying after retryable error.',
                    ['attempt' => $attempt]
                );
            }
        }
    }

    /**
     * Submits an order to GHTK (SL-016 / DEC-SL016-001). SINGLE attempt by
     * design: an automatic retry of a create call risks a duplicate GHTK order
     * when the first request actually succeeded after a network hiccup — the
     * deterministic partner_order_id plus a manual merchant retry is the safe
     * idempotency story for this release.
     *
     * @param array<string, mixed> $payload Mapped payload (see OrderRequestMapper).
     * @return array Decoded response; the caller (OrderResponseMapper) interprets it.
     * @throws GhtkApiException On transport failure, non-2xx, or invalid JSON.
     */
    public function submitOrder(array $payload, ?int $storeId = null): array
    {
        $url = $this->config->getApiBaseUrl($storeId) . self::ORDER_PATH;
        $curl = $this->curlFactory->create();
        $curl->setOptions([
            CURLOPT_CONNECTTIMEOUT => $this->config->getTimeoutConnect($storeId),
            CURLOPT_TIMEOUT => $this->config->getTimeoutTotal($storeId),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $token = $this->config->getApiToken($storeId);
        if ($token !== '') {
            $curl->addHeader('Token', $token);
        }
        $clientSource = $this->config->getClientSource($storeId);
        if ($clientSource !== '') {
            $curl->addHeader('X-Client-Source', $clientSource);
        }
        $curl->addHeader('Content-Type', 'application/json');

        try {
            $curl->post($url, (string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } catch (\JsonException $e) {
            throw new GhtkApiException('GHTK order payload could not be encoded.', false, 0, $e);
        } catch (\Throwable $e) {
            throw new GhtkApiException('GHTK order request failed (network): ' . $e->getMessage(), true, 0, $e);
        }

        $status = (int) $curl->getStatus();
        if ($status === 0 || $status >= 500) {
            throw new GhtkApiException('GHTK order request failed (status ' . $status . ').', true);
        }
        if ($status >= 400) {
            throw new GhtkApiException('GHTK order request rejected (status ' . $status . ').', false);
        }

        $decoded = json_decode((string) $curl->getBody(), true);
        if (!is_array($decoded)) {
            throw new GhtkApiException('GHTK order response is not valid JSON.', false);
        }

        $this->logger->info(
            'GHTK order submit call ok.',
            ['status' => $status, 'partner_order_id' => (string) ($payload['partner_order_id'] ?? '')]
        );

        return $decoded;
    }

    /**
     * Fetches the GHTK order/tracking status by label id (SL-017 fallback /
     * reconciliation path — same processing pipeline as the webhook via
     * TrackingRefreshService). Q-EXT: endpoint path per GHTK docs.
     *
     * @return array Decoded response (order block carries the status).
     * @throws GhtkApiException On transport failure, non-2xx, or invalid JSON.
     */
    public function getOrderStatus(string $labelId, ?int $storeId = null): array
    {
        $url = $this->config->getApiBaseUrl($storeId) . '/services/shipment/v2/' . rawurlencode($labelId);

        $curl = $this->curlFactory->create();
        $curl->setOptions([
            CURLOPT_CONNECTTIMEOUT => $this->config->getTimeoutConnect($storeId),
            CURLOPT_TIMEOUT => $this->config->getTimeoutTotal($storeId),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $token = $this->config->getApiToken($storeId);
        if ($token !== '') {
            $curl->addHeader('Token', $token);
        }
        $clientSource = $this->config->getClientSource($storeId);
        if ($clientSource !== '') {
            $curl->addHeader('X-Client-Source', $clientSource);
        }

        try {
            $curl->get($url);
        } catch (\Throwable $e) {
            throw new GhtkApiException('GHTK status request failed (network): ' . $e->getMessage(), true, 0, $e);
        }

        $status = (int) $curl->getStatus();
        if ($status === 0 || $status >= 500) {
            throw new GhtkApiException('GHTK status request failed (status ' . $status . ').', true);
        }
        if ($status >= 400) {
            throw new GhtkApiException('GHTK status request rejected (status ' . $status . ').', false);
        }

        $decoded = json_decode((string) $curl->getBody(), true);
        if (!is_array($decoded)) {
            throw new GhtkApiException('GHTK status response is not valid JSON.', false);
        }

        $this->logger->info('GHTK status call ok.', ['status' => $status]);

        return $decoded;
    }

    private function call(string $url, ?int $storeId): array
    {
        $curl = $this->curlFactory->create();
        $curl->setOptions([
            CURLOPT_CONNECTTIMEOUT => $this->config->getTimeoutConnect($storeId),
            CURLOPT_TIMEOUT => $this->config->getTimeoutTotal($storeId),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $token = $this->config->getApiToken($storeId);
        if ($token !== '') {
            $curl->addHeader('Token', $token);
        }
        $clientSource = $this->config->getClientSource($storeId);
        if ($clientSource !== '') {
            $curl->addHeader('X-Client-Source', $clientSource);
        }

        try {
            $curl->get($url);
        } catch (\Throwable $e) {
            throw new GhtkApiException(
                'GHTK fee request failed (network): ' . $e->getMessage(),
                true,
                0,
                $e
            );
        }

        $status = (int) $curl->getStatus();

        if ($status === 0 || $status >= 500) {
            throw new GhtkApiException('GHTK fee request failed (status ' . $status . ').', true);
        }
        if ($status >= 400) {
            // 4xx — never retry.
            throw new GhtkApiException('GHTK fee request rejected (status ' . $status . ').', false);
        }

        $decoded = json_decode((string) $curl->getBody(), true);
        if (!is_array($decoded)) {
            throw new GhtkApiException('GHTK fee response is not valid JSON.', false);
        }

        $this->logger->info('GHTK fee call ok.', ['status' => $status]);

        return $decoded;
    }
}
