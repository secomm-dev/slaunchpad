<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Client;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderRemoteException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\Logger\GhnLogger;

/**
 * SPEC-FEAT-FQWEQ3 §10 — the central GHN client. Owns base URL by environment, Token/ShopId
 * headers, JSON serialization, timeouts (§9 — the legacy integration never set any), envelope
 * parsing, error translation and safe logging. No other class in Secomm_Ghn performs HTTP.
 *
 * Fail-closed guarantees (§13, AC-RATE-003):
 *  - HTTP 200 with an EMPTY body → ProviderRemoteException (shop-not-found quirk, SPIKE-9Z231Q R11
 *    — a naive client would read this as success).
 *  - envelope code != 200 → translated typed provider exception, never fake data.
 *  - unconfigured token/shop → ProviderAuthenticationException before any HTTP call.
 */
final class GhnApiClient implements GhnApiClientInterface
{
    /** GHN envelope success code (independent of the HTTP status). */
    private const ENVELOPE_SUCCESS = 200;

    /** GET-only retry: master-data fetches walk hundreds of endpoints (authoring/sync CLI) and
     * flaky WSL/provider networks would kill multi-hour batches on one transient blip. GET is
     * idempotent; POST (create order) is NEVER retried (money-adjacent, §19 idempotency own path). */
    private const GET_RETRY_ATTEMPTS = 3;
    private const GET_RETRY_BACKOFF_MS = 500;

    public function __construct(
        private readonly Curl $httpClient,
        private readonly Config $config,
        private readonly GhnErrorTranslator $errorTranslator,
        private readonly GhnLogger $logger,
        private readonly Json $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function post(string $operation, string $path, array $payload): array
    {
        return $this->request($operation, $path, $this->serializer->serialize($payload));
    }

    /**
     * @inheritDoc
     */
    public function get(string $operation, string $path, array $params = []): array
    {
        $attempt = 1;
        while (true) {
            try {
                return $this->request($operation, $path, null, $params);
            } catch (ProviderTimeoutException $timeoutException) {
                if ($attempt >= self::GET_RETRY_ATTEMPTS) {
                    throw $timeoutException;
                }
                $attempt++;
                usleep(self::GET_RETRY_BACKOFF_MS * $attempt * 1000);
            }
        }
    }

    /**
     * Single transport + envelope path shared by post()/get() (TL-review note GHN-A: keeps each
     * public method readable; every failure mode stays an explicit early throw).
     *
     * @param array<string, string|int> $params query parameters for GET
     * @return array the envelope `data` value
     * @throws ProviderAuthenticationException|ProviderRemoteException|ProviderTimeoutException
     */
    private function request(string $operation, string $path, ?string $body, array $params = []): array
    {
        $token = $this->config->getApiToken();
        $shopId = $this->config->getShopId();

        if ($token === '' || $shopId === '') {
            throw new ProviderAuthenticationException(
                __('GHN %1 aborted: api_token / shop_id is not configured.', $operation)
            );
        }

        $url = $this->config->getBaseUrl() . '/' . ltrim($path, '/');
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        $startedAt = microtime(true);
        $this->prepareTransport($token, $shopId);

        try {
            if ($body === null) {
                $this->httpClient->get($url);
            } else {
                $this->httpClient->post($url, $body);
            }
        } catch (\Throwable $transportException) {
            $this->logCall($operation, $shopId, 0, null, $this->durationMs($startedAt));
            if ($this->isTimeout($transportException)) {
                throw new ProviderTimeoutException(
                    __('GHN %1 timed out after transport failure: %2', $operation, $transportException->getMessage())
                );
            }

            throw new ProviderRemoteException(
                __('GHN %1 transport error: %2', $operation, $transportException->getMessage())
            );
        }

        return $this->parseResponse($operation, $shopId, $startedAt, (string) $this->httpClient->getBody());
    }

    private function prepareTransport(string $token, string $shopId): void
    {
        $this->httpClient->setTimeout($this->config->getRequestTimeout());
        $this->httpClient->setOptions([
            CURLOPT_CONNECTTIMEOUT => $this->config->getConnectionTimeout(),
        ]);
        $this->httpClient->setHeaders([
            'Content-Type' => 'application/json',
            'Token' => $token,
            'ShopId' => $shopId,
        ]);
    }

    /**
     * Envelope parsing + classification for a completed transport round-trip.
     */
    private function parseResponse(string $operation, string $shopId, float $startedAt, string $rawBody): array
    {
        $httpStatus = (int) $this->httpClient->getStatus();
        $durationMs = $this->durationMs($startedAt);

        if ($httpStatus === 0) {
            $this->logCall($operation, $shopId, 0, null, $durationMs);
            throw new ProviderTimeoutException(__('GHN %1 timed out (no HTTP response).', $operation));
        }

        if ($httpStatus >= 400) {
            $this->logCall($operation, $shopId, $httpStatus, null, $durationMs);
            throw $this->errorTranslator->translate($httpStatus, $rawBody, $operation);
        }

        if (trim($rawBody) === '') {
            // HTTP 200 + empty body = shop-not-found quirk — MUST be an exception (R11).
            $this->logCall($operation, $shopId, $httpStatus, null, $durationMs);
            throw new ProviderRemoteException(
                __('GHN %1 returned an empty response body (HTTP %2) — treated as failure.', $operation, $httpStatus)
            );
        }

        try {
            $envelope = $this->serializer->unserialize($rawBody);
        } catch (\Throwable $parseError) {
            $this->logCall($operation, $shopId, $httpStatus, null, $durationMs);
            throw new ProviderRemoteException(
                __('GHN %1 returned malformed JSON: %2', $operation, $parseError->getMessage())
            );
        }

        if (!is_array($envelope) || !array_key_exists('code', $envelope)) {
            $this->logCall($operation, $shopId, $httpStatus, null, $durationMs);
            throw new ProviderRemoteException(__('GHN %1 returned an unexpected response envelope.', $operation));
        }

        $providerCode = (int) $envelope['code'];
        $providerMessage = (string) ($envelope['message'] ?? '');

        $this->logCall($operation, $shopId, $httpStatus, $providerCode, $durationMs);
        if ($this->config->isDebugEnabled()) {
            // Request payloads never carry the token (it is a header); scrub is defense in depth.
            $this->logger->debugPayload('GHN call payload', ['operation' => $operation, 'response' => $envelope]);
        }

        if ($providerCode !== self::ENVELOPE_SUCCESS) {
            throw $this->errorTranslator->translate($providerCode, $providerMessage, $operation);
        }

        $data = $envelope['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Single audit line per call (SPEC §48) — token never enters the context.
     */
    private function logCall(
        string $operation,
        string $shopId,
        int $httpStatus,
        ?int $providerCode,
        int $durationMs
    ): void {
        $this->logger->call('GHN call', [
            'operation' => $operation,
            'shop_id' => $shopId,
            'http_status' => $httpStatus,
            'provider_code' => $providerCode,
            'duration_ms' => $durationMs,
        ]);
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * Core Curl surfaces curl errors as \Exception with the raw curl message; timeouts surface as
     * CURLE_OPERATION_TIMEDOUT ("Operation timed out after ...").
     */
    private function isTimeout(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out') || str_contains($message, 'timeout');
    }
}
