<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Document;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceCore\Api\InvoiceDocumentServiceInterface;
use Secomm\EInvoiceMisa\Model\Client\MisaApiClient;

/**
 * Parses MeInvoice Download/publishview responses into downloadable file bytes.
 */
class MisaDownloadResponseParser
{
    /**
     * @param MisaApiClient $apiClient
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly MisaApiClient $apiClient,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Build a file payload from a MeInvoice API response.
     *
     * @param array<string, mixed> $response
     * @param string $transactionId
     * @param string $downloadType pdf|xml
     * @param string|null $token Bearer token for URL follow-up GET
     * @param int|null $storeId
     * @return array{filename: string, mime: string, contents: string}
     * @throws LocalizedException
     */
    public function parse(
        array $response,
        string $transactionId,
        string $downloadType,
        ?string $token = null,
        ?int $storeId = null
    ): array {
        if (!(bool) ($response['success'] ?? false)) {
            throw new LocalizedException(__('MeInvoice download failed: %1', $this->formatError($response)));
        }

        $payload = $this->resolveTopLevelData($response);
        if ($payload === '') {
            throw new LocalizedException(__('MeInvoice did not return file content for this invoice.'));
        }

        $contents = $this->resolveContents($payload, $transactionId, $downloadType, $token, $storeId);
        $contents = $this->normalizeByType($contents, $downloadType);

        return [
            'filename' => sprintf('invoice-%s.%s', $transactionId, $downloadType),
            'mime' => $downloadType === InvoiceDocumentServiceInterface::DOWNLOAD_XML
                ? 'application/xml'
                : 'application/pdf',
            'contents' => $contents,
        ];
    }

    /**
     * @param array<string, mixed> $response
     * @return string
     */
    private function resolveTopLevelData(array $response): string
    {
        foreach (['data', 'Data'] as $key) {
            $value = $response[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param string $payload
     * @param string $transactionId
     * @param string $downloadType
     * @param string|null $token
     * @param int|null $storeId
     * @return string
     * @throws LocalizedException
     */
    private function resolveContents(
        string $payload,
        string $transactionId,
        string $downloadType,
        ?string $token,
        ?int $storeId
    ): string {
        $trimmed = ltrim($payload);

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            $this->logger->debug('MeInvoice download: fetching URL', [
                'transaction_id' => $transactionId,
                'type' => $downloadType,
            ]);

            return $this->apiClient->fetchBinary($trimmed, $token, $storeId);
        }

        if (str_starts_with($trimmed, '[')) {
            return $this->extractFromJsonList($trimmed, $transactionId);
        }

        return $payload;
    }

    /**
     * @param string $jsonList
     * @param string $transactionId
     * @return string
     * @throws LocalizedException
     */
    private function extractFromJsonList(string $jsonList, string $transactionId): string
    {
        try {
            $rows = $this->json->unserialize($jsonList);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error('MeInvoice download: invalid JSON in data', [
                'transaction_id' => $transactionId,
                'preview' => substr($jsonList, 0, 200),
            ]);
            throw new LocalizedException(__('MeInvoice download response is not valid JSON.'));
        }

        if (!is_array($rows)) {
            throw new LocalizedException(__('MeInvoice download response JSON must be an array.'));
        }

        $row = $this->pickResultRow($rows, $transactionId);
        if ($row === null) {
            throw new LocalizedException(
                __('MeInvoice download response has no data for transaction %1.', $transactionId)
            );
        }

        $errorCode = (string) ($row['ErrorCode'] ?? $row['errorCode'] ?? '');
        if ($errorCode !== '') {
            throw new LocalizedException(__('MeInvoice download error: %1', $errorCode));
        }

        $inner = (string) ($row['Data'] ?? $row['data'] ?? '');
        if ($inner === '') {
            throw new LocalizedException(__('MeInvoice download row has empty file content.'));
        }

        return $inner;
    }

    /**
     * @param array<int, mixed> $rows
     * @param string $transactionId
     * @return array<string, mixed>|null
     */
    private function pickResultRow(array $rows, string $transactionId): ?array
    {
        $normalizedId = strtolower($transactionId);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowId = (string) ($row['TransactionID'] ?? $row['transactionID'] ?? $row['transaction_id'] ?? '');
            if ($rowId !== '' && strtolower($rowId) === $normalizedId) {
                return $row;
            }
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $inner = (string) ($row['Data'] ?? $row['data'] ?? '');
            if ($inner !== '') {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param string $contents
     * @param string $downloadType
     * @return string
     * @throws LocalizedException
     */
    private function normalizeByType(string $contents, string $downloadType): string
    {
        if ($downloadType === InvoiceDocumentServiceInterface::DOWNLOAD_XML) {
            $contents = trim($contents);
            if (!str_starts_with($contents, '<')) {
                $decoded = base64_decode($contents, true);
                if ($decoded !== false && str_starts_with(trim($decoded), '<')) {
                    $contents = $decoded;
                }
            }

            if (!str_starts_with($contents, '<')) {
                throw new LocalizedException(__('MeInvoice did not return valid XML content.'));
            }

            return $contents;
        }

        if (str_starts_with($contents, '%PDF')) {
            return $contents;
        }

        $decoded = base64_decode($contents, true);
        if ($decoded !== false && str_starts_with($decoded, '%PDF')) {
            return $decoded;
        }

        throw new LocalizedException(__('MeInvoice did not return valid PDF content.'));
    }

    /**
     * @param array<string, mixed> $response
     * @return string
     */
    private function formatError(array $response): string
    {
        if (isset($response['descriptionErrorCode']) && is_string($response['descriptionErrorCode'])) {
            return $response['descriptionErrorCode'];
        }
        if (isset($response['errors']) && is_array($response['errors']) && $response['errors'] !== []) {
            return implode(', ', array_map(static fn ($value): string => (string) $value, $response['errors']));
        }
        if (isset($response['errorCode']) && is_string($response['errorCode']) && $response['errorCode'] !== '') {
            return $response['errorCode'];
        }

        return (string) __('MeInvoice API returned success=false.');
    }
}
