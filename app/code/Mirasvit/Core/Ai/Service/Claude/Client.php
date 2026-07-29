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

namespace Mirasvit\Core\Ai\Service\Claude;

use Mirasvit\Core\Ai\Logger\AiLogger;
use Mirasvit\Core\Ai\Service\AiClientInterface;

class Client implements AiClientInterface
{
    public const HEADER_API_KEY           = 'x-api-key';
    public const HEADER_ANTHROPIC_VERSION = 'anthropic-version';
    public const ANTHROPIC_VERSION        = '2023-06-01';

    private $apiKey  = '';

    private $baseUrl = '';

    private $timeout = null;


    private $logger;

    public function __construct(AiLogger $logger)
    {
        $this->logger = $logger;
    }

    public function sendRequest(string $endpoint, string $method, array $data = [], array $headers = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init();

        $defaultHeaders = [
            self::HEADER_CONTENT_TYPE . ': ' . self::CONTENT_TYPE_JSON,
            self::HEADER_API_KEY . ': ' . $this->apiKey,
            self::HEADER_ANTHROPIC_VERSION . ': ' . self::ANTHROPIC_VERSION,
            self::HEADER_USER_AGENT . ': ' . self::DEFAULT_USER_AGENT,
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER         => false, // Don't include headers in response body
            CURLOPT_NOBODY         => false, // Include response body
        ]);

        if ($this->timeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        }

        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \Exception(sprintf('Claude API request failed: %s', $error));
        }

        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(sprintf('Invalid JSON response from Claude API: %s', json_last_error_msg()));
        }

        if ($httpCode >= 400) {
            $errorMessage = isset($decodedResponse['error']['message'])
                ? $decodedResponse['error']['message']
                : sprintf('Claude API HTTP error: %d', $httpCode);

            throw new \Exception($errorMessage);
        }

        return $decodedResponse;
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
        $this->timeout = max(1, $timeout);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }

}
