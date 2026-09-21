<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Http;

/**
 * TASK-7AJ3K8 — immutable transport request DTO. Fully-qualified URI + headers + body only;
 * the carrier builds it (auth, endpoint, payload), the client executes it. No secrets are
 * ever logged by the shared layer.
 */
final class CarrierHttpRequest
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';

    /**
     * @param string $method CarrierHttpRequest::METHOD_*
     * @param string $uri absolute URL (query string included)
     * @param array<string, string> $headers header name => value
     * @param string|null $body raw request body (JSON string for API calls)
     * @param int $timeoutConnect connect timeout seconds
     * @param int $timeoutTotal total timeout seconds
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $headers = [],
        private readonly ?string $body = null,
        private readonly int $timeoutConnect = 5,
        private readonly int $timeoutTotal = 15
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getTimeoutConnect(): int
    {
        return $this->timeoutConnect;
    }

    public function getTimeoutTotal(): int
    {
        return $this->timeoutTotal;
    }
}
