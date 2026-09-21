<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Http;

use Secomm\ShippingCore\Model\Http\CarrierHttpRequest;
use Secomm\ShippingCore\Model\Http\CarrierHttpResponse;

/**
 * TASK-7AJ3K8 (DEC-TASK7AJ3K8-001 §1) — the shared carrier transport primitive: exactly ONE
 * HTTP exchange per call (timeouts, status classification, error taxonomy). Auth headers,
 * endpoints, payload assembly and retry policy belong to the carrier; the client never adds,
 * logs or retries anything on its own.
 *
 * Failures throw {@see CarrierHttpException} with a {@see CarrierHttpErrorCategory}; a
 * non-2xx status is a THROW (category SERVER_ERROR/CLIENT_ERROR/RATE_LIMIT), not a response —
 * callers cannot accidentally parse an error body as data.
 */
interface CarrierHttpClientInterface
{
    /**
     * @throws CarrierHttpException TIMEOUT|NETWORK|RATE_LIMIT|SERVER_ERROR|CLIENT_ERROR
     */
    public function send(CarrierHttpRequest $request): CarrierHttpResponse;

    /**
     * send() + strict JSON decode of the response body.
     *
     * @return array<mixed>
     * @throws CarrierHttpException INVALID_RESPONSE when the body is not valid JSON or not an object/array
     */
    public function sendJson(CarrierHttpRequest $request): array;
}
