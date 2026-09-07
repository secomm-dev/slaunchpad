<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Model\VietQr;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Secomm\VietQr\Model\Config;

/**
 * HTTP client for communicating with the VietQR API
 * (TASK-N35E28 / SPEC-FEAT-ZKD4VA §3 AC-008, AC-019).
 *
 * Sends a POST request to generate a QR code and returns the parsed result.
 * Supports debug logging of API requests and responses.
 */
class ApiClient
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Call the VietQR API to generate a QR code.
     *
     * @param array $request The request payload (bankAccount, amount, content, etc.)
     * @return QrResult
     * @throws \Magento\Framework\Exception\LocalizedException When the API request fails or returns an invalid response
     */
    public function generate(array $request): QrResult
    {
        $endpoint = $this->config->getApiEndpoint();
        $timeout = $this->config->getRequestTimeout();
        $clientId = $this->config->getApiClientId();
        $apiKey = $this->config->getApiKey();

        $this->curl->setOptions([
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
        ]);

        $headers = ['Content-Type: application/json'];
        if ($clientId) {
            $headers[] = 'x-client-id: ' . $clientId;
        }
        if ($apiKey) {
            $headers[] = 'x-api-key: ' . $apiKey;
        }

        if ($this->config->isDebugEnabled()) {
            $this->logger->debug('VietQR API Request', [
                'endpoint' => $endpoint,
                'payload' => $request
            ]);
        }

        $this->curl->setHeaders($headers);
        $this->curl->post($endpoint, $this->json->serialize($request));

        $statusCode = $this->curl->getStatus();
        $responseBody = (string)$this->curl->getBody();

        if ($this->config->isDebugEnabled()) {
            $this->logger->debug('VietQR API Response', [
                'status' => $statusCode,
                'response' => $responseBody
            ]);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->warning(
                'VietQR API error',
                ['status' => $statusCode, 'response' => $responseBody]
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('VietQR API request failed. Please use manual bank transfer instructions.')
            );
        }

        $data = $this->json->unserialize($responseBody);
        if (!is_array($data)) {
            $data = [];
        }

        $qrCode = $data['qrCode'] ?? '';
        if (empty($qrCode)) {
            $this->logger->warning('VietQR API response missing qrCode field');
            throw new \Magento\Framework\Exception\LocalizedException(
                __('VietQR API returned invalid response. Please use manual bank transfer instructions.')
            );
        }

        return new QrResult((string)$qrCode, $data);
    }
}
