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

namespace Mirasvit\Core\Ai\Service\Gemini;

use Mirasvit\Core\Ai\Logger\AiLogger;
use Mirasvit\Core\Ai\Service\AiClientInterface;
use Mirasvit\Core\Service\SerializeService;

class Client implements AiClientInterface
{
    public const HEADER_API_KEY = 'x-goog-api-key';

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

            $requestHeaders = [
                self::HEADER_CONTENT_TYPE . ': ' . self::CONTENT_TYPE_JSON,
                self::HEADER_USER_AGENT . ': ' . self::DEFAULT_USER_AGENT,
                self::HEADER_API_KEY . ': ' . $this->apiKey,
            ];

            if (!empty($headers)) {
                $requestHeaders = array_merge($requestHeaders, $headers);
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);

            if (!empty($data)) {
                $jsonData = SerializeService::encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            }


            $response = curl_exec($ch);

            if ($response === false) {
                $error = curl_error($ch);
                throw new \Exception('cURL error: ' . $error);
            }

            $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseBody = $response;


            if ($httpCode >= 400) {
            }

            $decodedResponse = SerializeService::decode($responseBody);

            if ($decodedResponse === null) {
                throw new \Exception('Invalid JSON response from Gemini API');
            }

            return $decodedResponse;

        } finally {
            curl_close($ch);
        }
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }
}
