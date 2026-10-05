<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Client;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;

/**
 * HTTP client for MeInvoice Integration API.
 */
class MisaApiClient
{
    /**
     * @param Curl $curl
     * @param Json $json
     * @param MisaConfig $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly MisaConfig $config,
        private readonly MisaApiQueryBuilder $queryBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Obtain MeInvoice Integration API bearer token for the store.
     *
     * @param int|null $storeId
     * @return string
     * @throws LocalizedException
     */
    public function getIntegrationToken(?int $storeId = null): string
    {
        $response = $this->request(
            'POST',
            $this->config->getAuthTokenUrl($storeId),
            [
                'appid' => $this->config->getAppId($storeId),
                'taxcode' => $this->config->getTaxCode($storeId),
                'username' => $this->config->getUsername($storeId),
                'password' => $this->config->getPassword($storeId),
            ]
        );

        if (!(bool) ($response['success'] ?? false)) {
            throw new LocalizedException(__('MeInvoice token failed: %1', $this->extractError($response)));
        }

        $token = (string) ($response['data'] ?? '');
        if ($token === '') {
            throw new LocalizedException(__('MeInvoice token response is empty.'));
        }

        return $token;
    }

    /**
     * List invoice templates available for publishing.
     *
     * @param string $token
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function getTemplates(string $token, ?int $storeId = null): array
    {
        $query = $this->queryBuilder->build([
            'haveTempOld' => 'false',
            'invoiceWithCode' => $this->queryBuilder->invoiceWithCodeParam($storeId),
            'ticket' => 'false',
        ]);
        $url = $this->config->getInvoiceApiBaseUrl($storeId) . '/templates?' . $query;

        return $this->request('GET', $url, null, $token, $storeId);
    }

    /**
     * List HSM certificates for publish (§3).
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function getCertificates(string $token, ?int $storeId = null): array
    {
        $url = $this->config->getInvoiceApiBaseUrl($storeId) . '/get-certificates';

        return $this->request('GET', $url, null, $token, $storeId);
    }

    /**
     * Preview unpublished invoice data before HSM publish.
     *
     * @param array<string, mixed> $invoiceData
     * @param string $token
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function previewUnpublishedInvoice(array $invoiceData, string $token, ?int $storeId = null): array
    {
        $url = $this->config->getInvoiceApiBaseUrl($storeId) . '/unpublishview';

        return $this->request('POST', $url, $invoiceData, $token, $storeId);
    }

    /**
     * Publish invoice via HSM signing (MeInvoice POST /invoice).
     *
     * @param array<string, mixed> $invoiceData
     * @param string $token
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function publishInvoiceHsm(array $invoiceData, string $token, ?int $storeId = null): array
    {
        $url = $this->config->getInvoiceApiBaseUrl($storeId);

        $body = [
            'SignType' => $this->config->getSignType($storeId),
            'InvoiceData' => [$invoiceData],
            'PublishInvoiceData' => null,
        ];
        $certificateSn = $this->config->getCertificateSn($storeId);
        if ($certificateSn !== '' && (int) $body['SignType'] === MisaConfig::SIGN_TYPE_HSM) {
            $body['CertificateSN'] = $certificateSn;
        }

        return $this->request('POST', $url, $body, $token, $storeId);
    }

    /**
     * Fetch published invoice view HTML/metadata by transaction id.
     *
     * @param string $transactionId
     * @param string $token
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function viewPublishedInvoice(string $transactionId, string $token, ?int $storeId = null): array
    {
        $url = $this->config->getInvoiceApiBaseUrl($storeId) . '/publishview';

        return $this->request('POST', $url, [$transactionId], $token, $storeId);
    }

    /**
     * Request invoice file download (PDF or XML) for a published transaction.
     *
     * @param string $transactionId
     * @param string $downloadType MeInvoice downloadDataType (pdf/xml).
     * @param string $token
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function downloadInvoice(string $transactionId, string $downloadType, string $token, ?int $storeId = null): array
    {
        $query = $this->queryBuilder->build([
            'invoiceWithCode' => $this->queryBuilder->invoiceWithCodeParam($storeId),
            'invoiceCalcu' => $this->queryBuilder->invoiceCalcuParam($storeId),
            'downloadDataType' => $downloadType,
        ]);
        $url = $this->config->getInvoiceApiBaseUrl($storeId) . '/Download?' . $query;

        return $this->request('POST', $url, [$transactionId], $token, $storeId);
    }

    /**
     * Query publish/status metadata for a transaction id.
     *
     * @param string $transactionId
     * @param string $token
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function getInvoiceStatus(string $transactionId, string $token, ?int $storeId = null): array
    {
        return $this->getInvoiceStatusByInput($transactionId, 1, $token, $storeId);
    }

    /**
     * Query invoice status by TransactionID (1) or RefID (2).
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function getInvoiceStatusByInput(
        string $identifier,
        int $inputType,
        string $token,
        ?int $storeId = null
    ): array {
        $query = $this->queryBuilder->build([
            'inputType' => (string) $inputType,
            'invoiceWithCode' => $this->queryBuilder->invoiceWithCodeParam($storeId),
            'invoiceCalcu' => $this->queryBuilder->invoiceCalcuParam($storeId),
        ]);
        $url = $this->config->getInvoiceApiBaseUrl($storeId) . '/status?' . $query;

        return $this->request('POST', $url, [$identifier], $token, $storeId);
    }

    /**
     * Paging reconciliation API (§13.2).
     *
     * @param array<string, scalar|null> $params
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function getInvoicePaging(array $params, string $token, ?int $storeId = null): array
    {
        $defaults = [
            'invoiceWithCode' => $this->queryBuilder->invoiceWithCodeParam($storeId),
            'invoiceCalcu' => $this->queryBuilder->invoiceCalcuParam($storeId),
        ];
        $query = $this->queryBuilder->build(array_merge($defaults, $params));
        $url = $this->config->getInvoiceApiBaseUrl($storeId) . '/paging?' . $query;

        return $this->request('GET', $url, null, $token, $storeId);
    }

    /**
     * Cancel an issued invoice on MeInvoice.
     *
     * @param string $transactionId
     * @param string $reason
     * @param string $token
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function cancelInvoice(
        string $transactionId,
        string $invSeries,
        string $reason,
        string $token,
        ?int $storeId = null
    ): array {
        $url = $this->config->getInvoiceApiBaseUrl($storeId) . '/cancel';

        return $this->request('POST', $url, [
            'TransactionID' => $transactionId,
            'InvSeries' => $invSeries,
            'CancelReason' => $reason,
        ], $token, $storeId);
    }

    /**
     * Send published invoice email to customer(s) via MeInvoice.
     *
     * @see https://doc.meinvoice.vn/itg/Doc/SendEmail.html
     * @param array<int, array<string, mixed>> $sendEmailDatas
     * @param bool $isInvoiceCode
     * @param string $token
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function sendInvoiceEmail(
        array $sendEmailDatas,
        bool $isInvoiceCode,
        string $token,
        ?int $storeId = null
    ): array {
        $url = $this->config->getInvoiceApiBaseUrl($storeId) . '/sendemail';

        return $this->request('POST', $url, [
            'SendEmailDatas' => $sendEmailDatas,
            'IsInvoiceCode' => $isInvoiceCode,
            'IsInvoiceCalculatingMachine' => $this->config->isInvoiceCalculatingMachine($storeId),
        ], $token, $storeId);
    }

    /**
     * Fetch binary content from an absolute MeInvoice download URL.
     *
     * @param string $url
     * @param string|null $token
     * @param int|null $storeId
     * @return string
     * @throws LocalizedException
     */
    public function fetchBinary(string $url, ?string $token = null, ?int $storeId = null): string
    {
        $this->curl->setTimeout(60);
        $headers = ['Accept' => '*/*'];
        if ($token !== null && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
            $headers['CompanyTaxCode'] = $this->config->getTaxCode($storeId);
        }
        $this->curl->setHeaders($headers);
        $this->curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->curl->get($url);

        $status = $this->curl->getStatus();
        $body = $this->curl->getBody();
        $this->logger->debug('MISA binary fetch', ['url' => $url, 'status' => $status, 'size' => strlen($body)]);

        if ($status < 200 || $status >= 300) {
            throw new LocalizedException(__('MeInvoice download URL HTTP error %1', $status));
        }

        if ($body === '') {
            throw new LocalizedException(__('MeInvoice download URL returned empty content.'));
        }

        return $body;
    }

    /**
     * Execute HTTP request against MeInvoice API.
     *
     * @param string $method
     * @param string $url
     * @param array<string, mixed>|array<int, mixed>|null $body
     * @param string|null $token
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function request(string $method, string $url, ?array $body = null, ?string $token = null, ?int $storeId = null): array
    {
        $this->curl->setTimeout(30);
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
            $headers['CompanyTaxCode'] = $this->config->getTaxCode($storeId);
        }
        $this->curl->setHeaders($headers);
        $this->curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $encodedBody = $body !== null ? $this->json->serialize($body) : '{}';

        if ($method === 'GET') {
            $this->curl->get($url);
        } elseif ($method === 'POST') {
            $this->curl->post($url, $encodedBody);
        } else {
            $this->curl->setOption(CURLOPT_CUSTOMREQUEST, $method);
            $this->curl->post($url, $encodedBody);
        }

        $status = $this->curl->getStatus();
        $raw = $this->curl->getBody();
        $this->logger->debug('MISA API response', $this->buildSafeResponseLogContext($url, $status, $raw));

        if ($status === 401) {
            throw new LocalizedException(__('MeInvoice API HTTP error 401'));
        }

        if ($status < 200 || $status >= 300) {
            throw new LocalizedException(__('MeInvoice API HTTP error %1', $status));
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException $exception) {
            throw new LocalizedException(__('Invalid MeInvoice API response: %1', $exception->getMessage()));
        }

        return $decoded;
    }

    /**
     * Extract error message from MISA response payload.
     *
     * @param array $response
     * @return string
     */
    private function extractError(array $response): string
    {
        if (isset($response['descriptionErrorCode']) && is_string($response['descriptionErrorCode'])) {
            return $response['descriptionErrorCode'];
        }
        if (isset($response['errorCode'])) {
            return (string) $response['errorCode'];
        }
        if (isset($response['errors']) && is_array($response['errors']) && $response['errors'] !== []) {
            return (string) implode(', ', array_map(static fn ($v): string => (string) $v, $response['errors']));
        }

        return __('Unknown error')->render();
    }

    /**
     * Build debug log context with redacted secrets and truncated body preview.
     *
     * @param string $url
     * @param int $status
     * @param string $raw
     * @return array<string, mixed>
     */
    private function buildSafeResponseLogContext(string $url, int $status, string $raw): array
    {
        return [
            'url' => $this->sanitizeLogUrl($url),
            'status' => $status,
            'body_length' => strlen($raw),
            'body_preview' => $this->redactResponsePreview($raw),
        ];
    }

    private function sanitizeLogUrl(string $url): string
    {
        if (!str_contains($url, '/auth/token')) {
            return $url;
        }

        return preg_replace('#/auth/token.*#', '/auth/token', $url) ?? $url;
    }

    private function redactResponsePreview(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $maxLength = 240;
        $preview = strlen($raw) > $maxLength ? substr($raw, 0, $maxLength) . '…' : $raw;

        try {
            $decoded = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException) {
            return $preview;
        }

        if (!is_array($decoded)) {
            return $preview;
        }

        foreach (['data', 'password', 'token', 'access_token'] as $key) {
            if (array_key_exists($key, $decoded)) {
                $decoded[$key] = '[redacted]';
            }
        }

        $encoded = $this->json->serialize($decoded);

        return strlen($encoded) > $maxLength ? substr($encoded, 0, $maxLength) . '…' : $encoded;
    }
}
