<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Document;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Secomm\EInvoiceCore\Api\InvoiceDocumentServiceInterface;
use Secomm\EInvoiceMisa\Model\Client\MisaApiClient;
use Secomm\EInvoiceMisa\Model\Client\MisaTokenProvider;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;

/**
 * MeInvoice post-publish operations: status, published view and file download.
 */
class MisaInvoiceDocumentService implements InvoiceDocumentServiceInterface
{
    /**
     * @param MisaApiClient $apiClient
     * @param MisaTokenProvider $tokenProvider
     * @param MisaDownloadResponseParser $downloadResponseParser
     * @param MisaConfig $misaConfig
     * @param Json $json
     */
    public function __construct(
        private readonly MisaApiClient $apiClient,
        private readonly MisaTokenProvider $tokenProvider,
        private readonly MisaDownloadResponseParser $downloadResponseParser,
        private readonly MisaConfig $misaConfig,
        private readonly Json $json
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getStatus(string $transactionId, ?int $storeId = null): array
    {
        return $this->getStatusByInput($transactionId, 1, $storeId);
    }

    /**
     * Query status by TransactionID (1) or RefID (2).
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function getStatusByInput(string $identifier, int $inputType, ?int $storeId = null): array
    {
        if ($identifier === '') {
            throw new LocalizedException(__('Missing MeInvoice status identifier.'));
        }

        return $this->tokenProvider->executeWithToken(
            $storeId,
            fn (string $token): array => $this->apiClient->getInvoiceStatusByInput(
                $identifier,
                $inputType,
                $token,
                $storeId
            )
        );
    }

    /**
     * Preview unpublished invoice and return view link from response.
     *
     * @param array<string, mixed> $invoiceData
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function previewUnpublished(array $invoiceData, ?int $storeId = null): array
    {
        return $this->tokenProvider->executeWithToken(
            $storeId,
            fn (string $token): array => $this->apiClient->previewUnpublishedInvoice($invoiceData, $token, $storeId)
        );
    }

    /**
     * @inheritdoc
     */
    public function getPublishedView(string $transactionId, ?int $storeId = null): array
    {
        $this->assertTransactionId($transactionId);
        $token = $this->tokenProvider->getToken($storeId);

        return $this->apiClient->viewPublishedInvoice($transactionId, $token, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function download(string $transactionId, string $type, ?int $storeId = null): array
    {
        $this->assertTransactionId($transactionId);
        $downloadType = $type === self::DOWNLOAD_XML ? self::DOWNLOAD_XML : self::DOWNLOAD_PDF;
        $token = $this->tokenProvider->getToken($storeId);

        $response = $this->apiClient->downloadInvoice($transactionId, $downloadType, $token, $storeId);

        return $this->downloadResponseParser->parse($response, $transactionId, $downloadType, $token, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function cancel(
        string $transactionId,
        string $invSeries,
        string $reason,
        ?int $storeId = null
    ): array {
        $this->assertTransactionId($transactionId);
        if ($invSeries === '') {
            throw new LocalizedException(__('Missing MeInvoice InvSeries for this order.'));
        }
        if (trim($reason) === '') {
            throw new LocalizedException(__('Cancel reason is required.'));
        }

        $token = $this->tokenProvider->getToken($storeId);

        $response = $this->apiClient->cancelInvoice($transactionId, $invSeries, $reason, $token, $storeId);

        return [
            'success' => (bool) ($response['success'] ?? false),
            'message' => $this->formatErrorMessage($response),
            'raw' => $response,
        ];
    }

    /**
     * @inheritdoc
     */
    public function sendEmail(
        string $transactionId,
        string $receiverName,
        string $receiverEmail,
        ?string $ccEmail = null,
        ?string $replyEmail = null,
        ?int $storeId = null
    ): array {
        $this->assertTransactionId($transactionId);
        $receiverEmail = trim($receiverEmail);
        if ($receiverEmail === '') {
            throw new LocalizedException(__('Receiver email is required.'));
        }

        $receiverName = trim($receiverName) !== '' ? trim($receiverName) : __('Customer')->render();

        $token = $this->tokenProvider->getToken($storeId);

        $emailRow = [
            'TransactionID' => $transactionId,
            'ReceiverName' => $receiverName,
            'ReceiverEmail' => $receiverEmail,
        ];
        if ($ccEmail !== null && $ccEmail !== '') {
            $emailRow['CCEmail'] = $ccEmail;
        }
        if ($replyEmail !== null && $replyEmail !== '') {
            $emailRow['ReplyEmail'] = $replyEmail;
        }

        $response = $this->apiClient->sendInvoiceEmail(
            [$emailRow],
            $this->misaConfig->isInvoiceWithCode($storeId),
            $token,
            $storeId
        );

        $outcome = $this->resolveSendEmailOutcome($response);

        return [
            'success' => $outcome['success'],
            'message' => $outcome['message'],
            'raw' => $response,
        ];
    }

    /**
     * Interpret MeInvoice send-email API response into success flag and message.
     *
     * @param array<string, mixed> $response
     * @return array{success: bool, message: string}
     */
    private function resolveSendEmailOutcome(array $response): array
    {
        if (!(bool) ($response['success'] ?? false)) {
            $message = $this->formatErrorMessage($response);

            return [
                'success' => false,
                'message' => $message !== '' ? $message : (string) __('MeInvoice send email failed.'),
            ];
        }

        $items = $this->decodeSendEmailResults($response['data'] ?? null);
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((int) ($item['SendEmailStatus'] ?? 0) === 2) {
                $error = (string) ($item['ErrorCode'] ?? '');

                return [
                    'success' => false,
                    'message' => $error !== ''
                        ? $error
                        : (string) __('MeInvoice reported send email error for this transaction.'),
                ];
            }
        }

        return ['success' => true, 'message' => ''];
    }

    /**
     * Decode per-recipient send-email status rows from API data field.
     *
     * @param mixed $data JSON string or array from MeInvoice response.
     * @return array<int, mixed>
     */
    private function decodeSendEmailResults(mixed $data): array
    {
        if (is_string($data) && $data !== '') {
            try {
                $decoded = $this->json->unserialize($data);
            } catch (\InvalidArgumentException) {
                return [];
            }

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Extract human-readable error text from a MeInvoice API response.
     *
     * @param array<string, mixed> $response
     * @return string
     */
    private function formatErrorMessage(array $response): string
    {
        if (isset($response['errors']) && is_array($response['errors']) && $response['errors'] !== []) {
            $fromErrors = implode(', ', array_map(static fn ($value): string => (string) $value, $response['errors']));
            if ($fromErrors !== '') {
                return $fromErrors;
            }
        }

        $description = isset($response['descriptionErrorCode']) && is_string($response['descriptionErrorCode'])
            ? trim($response['descriptionErrorCode'])
            : '';
        if ($description !== '' && strtolower($description) !== 'exception') {
            return $description;
        }

        if (isset($response['errorCode']) && is_string($response['errorCode'])) {
            $code = trim($response['errorCode']);
            if ($code !== '' && strtolower($code) !== 'exception') {
                return $code;
            }
        }

        return $description !== '' ? $description : '';
    }

    /**
     * @param string $transactionId
     * @return void
     * @throws LocalizedException
     */
    private function assertTransactionId(string $transactionId): void
    {
        if ($transactionId === '') {
            throw new LocalizedException(__('Missing MeInvoice transaction id for this order.'));
        }
    }
}
