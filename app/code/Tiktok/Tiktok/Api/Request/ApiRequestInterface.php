<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Api\Request;

/**
 * Interface API Request
 */
interface ApiRequestInterface
{
    /**
     * Sends a request to the specified endpoint with the given HTTP method, parameters, and headers.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $params Request parameters
     * @param string|null $endpoint Optional specific endpoint for this request
     * @param bool $authRequired
     * @param array|null $headers Request headers
     * @param string|null $baseUrl Optional specific base URL for this request
     * @param bool $externalData
     *
     * @return array
     */
    public function sendRequest(
        string $method,
        array $params = [],
        ?string $endpoint = null,
        bool $authRequired = true,
        ?array $headers = [],
        ?string $baseUrl = null,
        bool $externalData = false
    ): array;
}
