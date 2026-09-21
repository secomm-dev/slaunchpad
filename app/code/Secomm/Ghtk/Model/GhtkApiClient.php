<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model;

use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Log\MaskingLogger;
use Secomm\Ghtk\Model\GhtkApiProfile;
use Secomm\ShippingCore\Api\Http\CarrierHttpClientInterface;
use Secomm\ShippingCore\Api\Http\CarrierHttpException;
use Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory;
use Secomm\ShippingCore\Model\Http\CarrierHttpRequest;
use Secomm\ShippingCore\Model\Http\RetryExecutor;
use Secomm\ShippingCore\Model\Http\RetryPolicy;

/**
 * Single GHTK API client abstraction (DEC-023 — base URI locked here). TASK-7AJ3K8: transport
 * (HTTP, timeouts, status/JSON error classification) moved onto the SHARED ShippingCore client
 * (DEC-TASK7AJ3K8-001 §1); GHTK still owns its auth headers, endpoints (via GhtkApiProfile),
 * payload mapping and retry SEMANTICS: safe reads retry NETWORK/SERVER_ERROR per config
 * `retry_max` (DEC-023 rule: never 4xx); create-order is SINGLE attempt — no automatic retry
 * until GHTK documents idempotency (DEC-SL016-001). Never logs the raw payload or the token.
 */
class GhtkApiClient
{
    private const HEADER_TOKEN = 'Token';
    private const HEADER_CLIENT_SOURCE = 'X-Client-Source';
    private const HEADER_CONTENT_TYPE = 'Content-Type';

    public function __construct(
        private readonly CarrierHttpClientInterface $httpClient,
        private readonly RetryExecutor $retryExecutor,
        private readonly GhtkConfig $config,
        private readonly GhtkApiProfile $profile,
        private readonly MaskingLogger $logger
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
        $uri = $this->config->getApiBaseUrl($storeId)
            . $this->profile->getFeePath()
            . '?' . http_build_query($params);
        $policy = RetryPolicy::safeRead(1 + $this->config->getRetryMax($storeId));

        try {
            return $this->retryExecutor->execute(
                fn (): array => $this->httpClient->sendJson($this->request(CarrierHttpRequest::METHOD_GET, $uri, $storeId)),
                $policy
            );
        } catch (CarrierHttpException $e) {
            throw $this->wrap($e, 'GHTK fee request');
        }
    }

    /**
     * Submits an order to GHTK (SL-016 / DEC-SL016-001). SINGLE attempt by
     * design: an automatic retry of a create call risks a duplicate GHTK order
     * when the first request actually succeeded after a network hiccup — the
     * deterministic order.id (Secomm partner id) plus a manual merchant retry is the safe
     * idempotency story for this release.
     *
     * @param array<string, mixed> $payload Mapped payload (see OrderRequestMapper).
     * @return array Decoded response; the caller (OrderResponseMapper) interprets it.
     * @throws GhtkApiException On transport failure, non-2xx, or invalid JSON.
     */
    public function submitOrder(array $payload, ?int $storeId = null): array
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            throw new GhtkApiException('GHTK order payload could not be encoded.', false, 0, $e);
        }

        $uri = $this->config->getApiBaseUrl($storeId) . $this->profile->getOrderPath();
        $request = new CarrierHttpRequest(
            method: CarrierHttpRequest::METHOD_POST,
            uri: $uri,
            headers: $this->headers($storeId, true),
            body: $body,
            timeoutConnect: $this->config->getTimeoutConnect($storeId),
            timeoutTotal: $this->config->getTimeoutTotal($storeId)
        );

        try {
            // SINGLE attempt by policy (DEC-SL016-001 / DEC-TASK7AJ3K8-001 §1) — never a loop.
            $decoded = $this->retryExecutor->execute(
                fn (): array => $this->httpClient->sendJson($request),
                RetryPolicy::singleAttempt()
            );
        } catch (CarrierHttpException $e) {
            throw $this->wrap($e, 'GHTK order request');
        }

        $this->logger->info(
            'GHTK order submit call ok.',
            ['order_id' => (string) ($payload['order']['id'] ?? '')]
        );

        return $decoded;
    }

    /**
     * Fetches the GHTK order/tracking status by label id (SL-017 fallback /
     * reconciliation path — same processing pipeline as the webhook via the
     * shared tracking reconciliation). Q-EXT: endpoint path per GHTK docs.
     *
     * @return array Decoded response (order block carries the status).
     * @throws GhtkApiException On transport failure, non-2xx, or invalid JSON.
     */
    public function getOrderStatus(string $labelId, ?int $storeId = null): array
    {
        $uri = $this->config->getApiBaseUrl($storeId) . $this->profile->getOrderStatusPath($labelId);
        $policy = RetryPolicy::safeRead(1 + $this->config->getRetryMax($storeId));

        try {
            return $this->retryExecutor->execute(
                fn (): array => $this->httpClient->sendJson($this->request(CarrierHttpRequest::METHOD_GET, $uri, $storeId)),
                $policy
            );
        } catch (CarrierHttpException $e) {
            throw $this->wrap($e, 'GHTK status request');
        }
    }

    /**
     * Cancels a GHTK shipment/order (TASK-FNVHK5 — official api-cancel-order).
     * SINGLE automatic attempt: a mutation that may already have landed — never
     * retried automatically (manual retry resolves benignly via the
     * already-cancelled answer).
     *
     * @param string $identifier GHTK label code, or `partner_id:{code}`
     * @return array Decoded response (`success`, `message`, `log_id`).
     * @throws GhtkApiException On transport failure, non-2xx, or invalid JSON.
     */
    public function cancelShipment(string $identifier, ?int $storeId = null): array
    {
        $uri = $this->config->getApiBaseUrl($storeId)
            . $this->profile->getCancelPath()
            . rawurlencode($identifier);
        // Single attempt by policy — a mutation; no RetryPolicy (unlike safe reads).
        try {
            $decoded = $this->httpClient->sendJson(
                $this->request(CarrierHttpRequest::METHOD_POST, $uri, $storeId)
            );
        } catch (CarrierHttpException $e) {
            throw $this->wrap($e, 'GHTK cancel request');
        }

        $this->logger->info('GHTK cancel call ok.');

        return $decoded;
    }

    /**
     * Lists the merchant pickup addresses (TASK-3HPB76 — admin Test Connection
     * tooling; NEVER called from rate/create/checkout paths).
     *
     * @return array Decoded response (`success`, `data[]` pickup rows).
     * @throws GhtkApiException On transport failure, non-2xx, or invalid JSON.
     */
    public function getPickupAddresses(?int $storeId = null): array
    {
        $uri = $this->config->getApiBaseUrl($storeId) . $this->profile->getPickupListPath();
        // Read-only operation — approved safe-read retry applies (NETWORK/SERVER_ERROR/TIMEOUT).
        $policy = RetryPolicy::safeRead(1 + $this->config->getRetryMax($storeId));

        try {
            return $this->retryExecutor->execute(
                fn (): array => $this->httpClient->sendJson($this->request(CarrierHttpRequest::METHOD_GET, $uri, $storeId)),
                $policy
            );
        } catch (CarrierHttpException $e) {
            throw $this->wrap($e, 'GHTK pickup list request');
        }
    }

    private function request(string $method, string $uri, ?int $storeId): CarrierHttpRequest
    {
        return new CarrierHttpRequest(
            method: $method,
            uri: $uri,
            headers: $this->headers($storeId, false),
            timeoutConnect: $this->config->getTimeoutConnect($storeId),
            timeoutTotal: $this->config->getTimeoutTotal($storeId)
        );
    }

    /**
     * @return array<string, string>
     */
    private function headers(?int $storeId, bool $jsonBody): array
    {
        $headers = [];
        $token = $this->config->getApiToken($storeId);
        if ($token !== '') {
            $headers[self::HEADER_TOKEN] = $token;
        }
        $clientSource = $this->config->getClientSource($storeId);
        if ($clientSource !== '') {
            $headers[self::HEADER_CLIENT_SOURCE] = $clientSource;
        }
        if ($jsonBody) {
            $headers[self::HEADER_CONTENT_TYPE] = 'application/json';
        }

        return $headers;
    }

    /**
     * One carrier-facing exception surface (public contract unchanged): the retryable flag
     * follows the shared category — NETWORK/SERVER_ERROR/TIMEOUT are retryable, everything
     * else (RATE_LIMIT/CLIENT_ERROR/INVALID_RESPONSE) is not.
     */
    private function wrap(CarrierHttpException $e, string $operation): GhtkApiException
    {
        $retryable = in_array(
            $e->getCategory(),
            [
                CarrierHttpErrorCategory::NETWORK,
                CarrierHttpErrorCategory::SERVER_ERROR,
                CarrierHttpErrorCategory::TIMEOUT,
            ],
            true
        );

        return new GhtkApiException($operation . ' failed: ' . $e->getMessage(), $retryable, 0, $e, $e->getCategory());
    }
}
