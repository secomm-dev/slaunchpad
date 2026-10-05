<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Issuer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceCore\Api\Data\IssueResultInterface;
use Secomm\EInvoiceCore\Api\InvoiceIssuerInterface;
use Secomm\EInvoiceCore\Model\Data\IssueResultFactory;
use Secomm\EInvoiceMisa\Model\Client\MisaApiClient;
use Secomm\EInvoiceMisa\Model\Client\MisaTokenProvider;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;

/**
 * MeInvoice Integration API invoice issuer.
 */
class MisaInvoiceIssuer implements InvoiceIssuerInterface
{
    public function __construct(
        private readonly MisaApiClient $apiClient,
        private readonly MisaTokenProvider $tokenProvider,
        private readonly MisaConfig $misaConfig,
        private readonly PublishResponseValidator $publishResponseValidator,
        private readonly InvoicePayloadTotalsValidator $payloadTotalsValidator,
        private readonly IssueResultFactory $issueResultFactory,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function issue(IssueRequestInterface $request): IssueResultInterface
    {
        $result = $this->issueResultFactory->create();
        $storeId = $request->getStoreId();

        try {
            $invoiceData = $request->getPayload();
            $this->payloadTotalsValidator->assertConsistent($invoiceData);
            $rawResponses = [];

            if ($this->misaConfig->usePreviewBeforePublish($storeId)) {
                $rawResponses['preview'] = $this->tokenProvider->executeWithToken(
                    $storeId,
                    fn (string $token): array => $this->apiClient->previewUnpublishedInvoice($invoiceData, $token, $storeId)
                );
            }

            $response = $this->tokenProvider->executeWithToken(
                $storeId,
                fn (string $token): array => $this->apiClient->publishInvoiceHsm($invoiceData, $token, $storeId)
            );
            $rawResponses['publish'] = $response;

            $this->publishResponseValidator->assertPublishSuccess($response);

            $publishResult = $this->normalizePublishResultItem($response);
            $transactionId = (string) ($publishResult['TransactionID'] ?? '');

            if ($this->misaConfig->requiresStatusPollAfterPublish($storeId) && $transactionId !== '') {
                $rawResponses['status_poll'] = $this->pollPublishStatus($transactionId, $storeId);
            }

            $rawResponses['meta'] = [
                'ref_id' => (string) ($invoiceData['RefID'] ?? ''),
                'transaction_id' => $transactionId,
                'inv_no' => (string) ($publishResult['InvNo'] ?? ''),
                'inv_series' => (string) ($invoiceData['InvSeries'] ?? ''),
                'invoice_template_id' => (string) ($invoiceData['InvoiceTemplateID'] ?? ''),
                'sign_type' => $this->misaConfig->getSignType($storeId),
            ];
            $result->setSuccess(true);
            $result->setRawResponse($rawResponses);

            if ($transactionId === '') {
                $result->setSuccess(false);
                $result->setMessage(
                    (string) __('MeInvoice publish succeeded but TransactionID is missing from the response.')
                );
            } else {
                $result->setExternalId($transactionId);
                $result->setMessage((string) __('Invoice issued successfully via MeInvoice.'));
            }
        } catch (LocalizedException $exception) {
            $this->logger->error('MISA invoice issue failed', [
                'order_id' => $request->getOrderId(),
                'message' => $exception->getMessage(),
            ]);
            $result->setSuccess(false)->setMessage($exception->getMessage());
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function pollPublishStatus(string $transactionId, ?int $storeId): array
    {
        $attempts = 5;
        $last = [];
        for ($i = 0; $i < $attempts; $i++) {
            $last = $this->tokenProvider->executeWithToken(
                $storeId,
                fn (string $token): array => $this->apiClient->getInvoiceStatus($transactionId, $token, $storeId)
            );
            if ((bool) ($last['success'] ?? false)) {
                return $last;
            }
            usleep(500000);
        }

        throw new LocalizedException(__('MeInvoice async publish status was not ready after polling.'));
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function normalizePublishResultItem(array $response): array
    {
        $raw = $response['publishInvoiceResult'] ?? [];
        if (is_string($raw) && $raw !== '') {
            try {
                $decoded = $this->json->unserialize($raw);
            } catch (\InvalidArgumentException) {
                return [];
            }
            $raw = $decoded;
        }

        if (!is_array($raw)) {
            return [];
        }

        if (isset($raw[0]) && is_array($raw[0])) {
            return $raw[0];
        }

        if (isset($raw['TransactionID']) || isset($raw['InvNo']) || isset($raw['ErrorCode'])) {
            return $raw;
        }

        return [];
    }
}
