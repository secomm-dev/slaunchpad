<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Http;

use Magento\Framework\HTTP\Client\CurlFactory;
use Secomm\ShippingCore\Api\Http\CarrierHttpClientInterface;
use Secomm\ShippingCore\Api\Http\CarrierHttpException;
use Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory;

/**
 * TASK-7AJ3K8 — the default curl-backed {@see CarrierHttpClientInterface}. ONE exchange per
 * call; every non-2xx outcome is a categorized throw. Classification limits (documented):
 * the Magento curl client exposes no errno, so a status of 0 (connect timeout, DNS, reset…)
 * is classified NETWORK — TIMEOUT is reserved for adapters that can inspect errno.
 */
final class CurlCarrierHttpClient implements CarrierHttpClientInterface
{
    public function __construct(
        private readonly CurlFactory $curlFactory
    ) {
    }

    public function send(CarrierHttpRequest $request): CarrierHttpResponse
    {
        $curl = $this->curlFactory->create();
        $curl->setOptions([
            CURLOPT_CONNECTTIMEOUT => $request->getTimeoutConnect(),
            CURLOPT_TIMEOUT => $request->getTimeoutTotal(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        foreach ($request->getHeaders() as $name => $value) {
            $curl->addHeader((string) $name, (string) $value);
        }

        try {
            if ($request->getMethod() === CarrierHttpRequest::METHOD_POST) {
                $curl->post($request->getUri(), (string) $request->getBody());
            } else {
                $curl->get($request->getUri());
            }
        } catch (\Throwable $e) {
            throw new CarrierHttpException(
                CarrierHttpErrorCategory::NETWORK,
                'Carrier HTTP request failed (network): ' . $e->getMessage(),
                $e
            );
        }

        $status = (int) $curl->getStatus();
        if ($status === 0) {
            throw new CarrierHttpException(
                CarrierHttpErrorCategory::NETWORK,
                'Carrier HTTP request failed without a status (network/timeout).'
            );
        }
        if ($status >= 500) {
            throw new CarrierHttpException(
                CarrierHttpErrorCategory::SERVER_ERROR,
                'Carrier HTTP request failed (status ' . $status . ').'
            );
        }
        if ($status === 429) {
            throw new CarrierHttpException(
                CarrierHttpErrorCategory::RATE_LIMIT,
                'Carrier HTTP request rate limited (status 429).'
            );
        }
        if ($status >= 400) {
            throw new CarrierHttpException(
                CarrierHttpErrorCategory::CLIENT_ERROR,
                'Carrier HTTP request rejected (status ' . $status . ').'
            );
        }

        return new CarrierHttpResponse($status, (string) $curl->getBody());
    }

    public function sendJson(CarrierHttpRequest $request): array
    {
        $response = $this->send($request);

        try {
            $decoded = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new CarrierHttpException(
                CarrierHttpErrorCategory::INVALID_RESPONSE,
                'Carrier HTTP response is not valid JSON.',
                $e
            );
        }

        if (!is_array($decoded)) {
            throw new CarrierHttpException(
                CarrierHttpErrorCategory::INVALID_RESPONSE,
                'Carrier HTTP response JSON is not an object/array.'
            );
        }

        return $decoded;
    }
}
