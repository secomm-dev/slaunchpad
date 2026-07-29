<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



declare(strict_types=1);

namespace Mirasvit\Core\Ai\Service\OpenAi;

use Mirasvit\Core\Ai\Logger\AiLogger;
use Mirasvit\Core\Ai\Service\AiClientInterface;
use Mirasvit\Core\Service\SerializeService;

class Client implements AiClientInterface
{
    private $logger;

    private $baseUrl = '';

    private $apiKey  = '';

    private $timeout = null;


    public function __construct(AiLogger $logger)
    {
        $this->logger = $logger;
    }

    public function sendRequest(string $endpoint, string $method, array $data = [], array $headers = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init();

        try {
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADER         => false,
                CURLOPT_NOBODY         => false,
            ]);

            if ($this->timeout) {
                curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            }

            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, SerializeService::encode($data));
            }

            $requestHeaders = $this->buildHeaders($headers);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);

            $response = curl_exec($ch);

            if ($response === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new \Exception(sprintf('cURL request failed: %s', $error));
            }

            $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseBody = $response;

            curl_close($ch);

            $decodedResponse = SerializeService::decode($responseBody);

            if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(sprintf('Invalid JSON response: %s', json_last_error_msg()));
            }

            if ($httpCode >= 400) {
                $errorMessage = $this->extractErrorMessage($decodedResponse, $httpCode);
                throw new \Exception($errorMessage, $httpCode);
            }


            return $decodedResponse ? : [];

        } catch (\Exception $e) {
            if (is_resource($ch)) {
                curl_close($ch);
            }


            throw $e;
        }
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getLastResponseCode(): int
    {
        return $this->lastResponseCode;
    }

    public function getLastResponseHeaders(): array
    {
        return $this->lastResponseHeaders;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }

    private function buildHeaders(array $additionalHeaders = []): array
    {
        $headers = [
            self::HEADER_CONTENT_TYPE => self::CONTENT_TYPE_JSON,
            self::HEADER_USER_AGENT   => self::DEFAULT_USER_AGENT,
        ];

        if (!empty($this->apiKey)) {
            $headers[self::HEADER_AUTHORIZATION] = 'Bearer ' . $this->apiKey;
        }

        $headers = array_merge($headers, $additionalHeaders);

        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = $key . ': ' . $value;
        }

        return $curlHeaders;
    }


    private function extractErrorMessage($response, int $httpCode): string
    {
        if (is_array($response) && isset($response['error'])) {
            $error = $response['error'];

            if (is_array($error)) {
                $message = $error['message'] ?? (string)__('Unknown API error');
                $type    = $error['type'] ?? '';
                $code    = $error['code'] ?? '';

                return sprintf(
                    'OpenAI API Error: %s (Type: %s, Code: %s, HTTP: %d)',
                    $message,
                    $type,
                    $code,
                    $httpCode
                );
            }

            return sprintf('OpenAI API Error: %s (HTTP: %d)', $error, $httpCode);
        }

        return sprintf('OpenAI API HTTP Error: %d', $httpCode);
    }

}
