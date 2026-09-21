<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Api\Client;

/**
 * SPEC-FEAT-FQWEQ3 §10 — THE single GHN HTTP egress point. Every GHN call in Secomm_Ghn goes
 * through this client (base URL, auth headers, serialization, timeouts, envelope parsing, error
 * translation, safe logging). No provider/service class may build its own HTTP request.
 *
 * Failures are thrown as typed provider exceptions (SPEC §11, Api\Exception) — never swallowed,
 * never converted to fake success data (§13: no magic fallback).
 */
interface GhnApiClientInterface
{
    /**
     * POST to a GHN endpoint and return the `data` part of the GHN envelope {code, message, data}.
     *
     * @param string $operation business label used for logging context (e.g. "master_data_provinces")
     * @param string $path endpoint path relative to the base URL, e.g. GhnEndpoints::MASTER_DATA_PROVINCES
     * @param array $payload JSON body
     * @return array the envelope `data` value (empty array when GHN returns a scalar/null data)
     * @throws \Secomm\Ghn\Api\Exception\ProviderAuthenticationException invalid/missing token or shop
     * @throws \Secomm\Ghn\Api\Exception\ProviderInvalidAddressException GHN rejected the address components
     * @throws \Secomm\Ghn\Api\Exception\ProviderInvalidRequestException malformed request / unknown route
     * @throws \Secomm\Ghn\Api\Exception\ProviderRateUnavailableException requested rate/service not offered
     * @throws \Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException GHN side outage / rate limited
     * @throws \Secomm\Ghn\Api\Exception\ProviderTimeoutException connection or request timeout
     * @throws \Secomm\Ghn\Api\Exception\ProviderRemoteException empty body on HTTP 200 (shop-not-found quirk),
     *         malformed JSON, or unmapped GHN error
     */
    public function post(string $operation, string $path, array $payload): array;

    /**
     * GET a GHN endpoint (query-string parameters) and return the `data` part of the envelope.
     * Used by the v3 new-model master-data endpoints (documented GET-only).
     *
     * @param string $operation business label used for logging context
     * @param string $path endpoint path relative to the base URL
     * @param array $params query parameters (scalar values only)
     * @return array the envelope `data` value (empty array when GHN returns a scalar/null data)
     * @throws \Secomm\Ghn\Api\Exception\ProviderAuthenticationException invalid/missing token or shop
     * @throws \Secomm\Ghn\Api\Exception\ProviderInvalidAddressException GHN rejected the address components
     * @throws \Secomm\Ghn\Api\Exception\ProviderInvalidRequestException malformed request / unknown route
     * @throws \Secomm\Ghn\Api\Exception\ProviderRateUnavailableException requested rate/service not offered
     * @throws \Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException GHN side outage / rate limited
     * @throws \Secomm\Ghn\Api\Exception\ProviderTimeoutException connection or request timeout
     * @throws \Secomm\Ghn\Api\Exception\ProviderRemoteException empty body on HTTP 200 (shop-not-found quirk),
     *         malformed JSON, or unmapped GHN error
     */
    public function get(string $operation, string $path, array $params = []): array;
}
