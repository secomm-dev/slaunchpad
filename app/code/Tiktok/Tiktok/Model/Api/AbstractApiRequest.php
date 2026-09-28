<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tiktok\Tiktok\Api\Request\ApiRequestInterface;
use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Api\Account\ExternalDataHandler;
use Tiktok\Tiktok\Model\Api\Auth\TokenHandler;
use Tiktok\Tiktok\Model\Config\ScopeManager;

/**
 * Abstract API Service class.
 * This class is responsible for sending requests to the TikTok API.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractApiRequest implements ApiRequestInterface
{
    /**
     * TikTok Base URL
     */
    protected const BASE_URL = 'https://business-api.tiktok.com';

    /**
     * Init dependencies
     *
     * @param \Tiktok\Tiktok\Model\Config\ScopeManager $scopeManager
     * @param \GuzzleHttp\Client $client
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     * @param \Tiktok\Tiktok\Model\Api\Auth\TokenHandler $tokenHandler
     * @param \Tiktok\Tiktok\Model\Api\Account\ExternalDataHandler $externalData
     */
    public function __construct(
        protected ScopeManager $scopeManager,
        protected Client $client,
        protected TiktokLogger $logger,
        protected TokenHandler $tokenHandler,
        protected ExternalDataHandler $externalData
    ) {
    }

    /**
     * Shared logic for sending requests.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $params Request parameters
     * @param string|null $endpoint Specific endpoint for this request
     * @param bool $authRequired
     * @param array|null $headers Request headers
     * @param string|null $baseUrl Specific base URL for this request
     * @param bool $externalData Whether to include external data
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     */
    public function sendRequest(
        string  $method,
        array   $params = [],
        ?string $endpoint = null,
        bool    $authRequired = true,
        ?array  $headers = [],
        ?string $baseUrl = null,
        bool    $externalData = false
    ): array {
        try {
            $url = $this->getFullUrl($endpoint, $baseUrl);
            if ($externalData) {
                $url .= '?external_data=' . $this->getExternalData();
            }

            $headers = $this->buildHeaders($headers, $authRequired);

            $options = [
                'headers' => $headers];

            if (strtoupper($method) === 'GET') {
                $options['query'] = $params; // Use 'query' for GET requests
            } else {
                $options['json'] = $params; // Use 'json' for POST/PUT/etc.
            }

            $this->logRequest($method, $url, $params, $headers);

            $response = $this->client->request($method, $url, $options);
            $responseBody = json_decode(
                $response->getBody()->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $this->logResponse($response, $responseBody);

            return $responseBody;
        } catch (RequestException $e) {
            $this->logger->error('TikTok API request failed: ' . $e->getMessage());
            $this->handleError($e->getResponse() ?? $e->getMessage());
            throw $e;
        }
    }

    /**
     * Return Full URL
     *
     * @param string|null $endpoint
     * @param string|null $baseUrl
     *
     * @return string
     */
    protected function getFullUrl(?string $endpoint = null, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? $this->getBaseUrl();
        return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * Return base URL
     *
     * @return string
     */
    protected function getBaseUrl(): string
    {
        return static::BASE_URL;
    }

    /**
     * Retrieve External Data
     *
     * @return string
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getExternalData(): string
    {
        return $this->externalData->getExternalData();
    }

    /**
     * Build headers
     *
     * @param array|null $headers
     * @param bool|null $authRequired
     *
     * @return array
     * @throws GuzzleException
     */
    protected function buildHeaders(?array $headers = [], ?bool $authRequired = null): array
    {
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }
        if (!isset($headers['Access-Token']) && $authRequired === true) {
            $headers['Access-Token'] = $this->tokenHandler->getAccessToken();
        }

        return $headers;
    }

    /**
     * Log request
     *
     * @param string $method
     * @param string $url
     * @param array $params
     * @param array $headers
     *
     * @return void
     * @throws JsonException
     */
    protected function logRequest(string $method, string $url, array $params, array $headers): void
    {
        $logLevel = $this->scopeManager->getLogLevel();

        // Anonymize the Access-Token header
        if (isset($headers['Access-Token'])) {
            $headers['Access-Token'] = $this->anonymizeAccessToken($headers['Access-Token']);
        }

        $this->logger->debug("Sending request to $url with method $method");

        if ($logLevel === 'debug') {
            $this->logger->debug('Request params: ' . json_encode($params, JSON_THROW_ON_ERROR));
            $this->logger->debug('Request headers: ' . json_encode($headers, JSON_THROW_ON_ERROR));
        }
    }

    /**
     * Anonymize access token
     *
     * @param string $token
     *
     * @return string
     */
    protected function anonymizeAccessToken(string $token): string
    {
        // Keep the last 4 characters and replace the rest with *
        $length = strlen($token);
        if ($length > 4) {
            return str_repeat('*', $length - 4) . substr($token, -4);
        }

        return $token;
    }

    /**
     * Log response
     *
     * @param ResponseInterface $response
     * @param array $responseBody
     *
     * @return void
     * @throws JsonException
     */
    protected function logResponse(ResponseInterface $response, array $responseBody): void
    {
        $logLevel = $this->scopeManager->getLogLevel();

        $this->logger->info("Received response with status code: {$response->getStatusCode()}");
        if ($logLevel === 'debug') {
            $this->logger->debug('Response body: ' . json_encode($responseBody, JSON_THROW_ON_ERROR));
        }
    }

    /**
     * Handle any errors that occur during the request.
     *
     * @param string|ResponseInterface $response The API response or error message
     *
     * @throws Exception
     */
    protected function handleError(string|ResponseInterface $response): void
    {
        if ($response instanceof ResponseInterface) {
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400 && $statusCode < 500) {
                $this->logger->error('Client error: ' . $response->getReasonPhrase());
                throw new RuntimeException('Client error: ' . $response->getReasonPhrase(), $statusCode);
            }

            if ($statusCode >= 500) {
                $this->logger->error('Server error: ' . $response->getReasonPhrase());
                throw new RuntimeException('Server error: ' . $response->getReasonPhrase(), $statusCode);
            }
        } else {
            $this->logger->error('Request error: ' . $response);
            throw new RuntimeException('Request error: ' . $response);
        }
    }
}
