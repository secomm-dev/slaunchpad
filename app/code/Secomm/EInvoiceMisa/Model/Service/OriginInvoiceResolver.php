<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Secomm\EInvoiceCore\Api\OriginInvoiceResolverInterface;
use Secomm\EInvoiceCore\Model\InvoiceDocumentServicePool;
use Secomm\EInvoiceLog\Model\IssueLog;

/**
 * Resolves original invoice org fields for adjustment/replace payloads (§10).
 */
class OriginInvoiceResolver implements OriginInvoiceResolverInterface
{
    public function __construct(
        private readonly InvoiceDocumentServicePool $documentServicePool,
        private readonly Json $json
    ) {
    }

    /**
     * @return array<string, string>
     * @throws LocalizedException
     */
    public function resolve(IssueLog $originLog): array
    {
        $storeId = (int) $originLog->getData('store_id');
        $transactionId = (string) $originLog->getData('transaction_id');
        $refId = (string) $originLog->getData('ref_id');
        $invSeries = (string) $originLog->getData('inv_series');
        $invDate = substr((string) ($originLog->getData('issued_at') ?: ''), 0, 10);

        $org = [
            'transaction_id' => $transactionId,
            'ref_id' => $refId,
            'inv_series' => $invSeries,
            'inv_date' => $invDate,
            'inv_no' => (string) ($originLog->getData('inv_no') ?? ''),
            'template_no' => '',
        ];

        $org = array_merge($org, $this->extractFromRequestPayload($originLog));

        if ($org['inv_no'] === '' && $transactionId !== '') {
            $org = $this->mergeOriginFields($org, $this->fetchFromStatus($transactionId, $storeId, 1));
        }
        if ($org['inv_no'] === '' && $refId !== '') {
            $org = $this->mergeOriginFields($org, $this->fetchFromStatus($refId, $storeId, 2));
        }

        return $org;
    }

    /**
     * @return array<string, string>
     */
    private function extractFromRequestPayload(IssueLog $log): array
    {
        $raw = (string) $log->getData('request_payload');
        if ($raw === '') {
            return [];
        }

        try {
            $payload = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException) {
            return [];
        }

        if (!is_array($payload)) {
            return [];
        }

        return [
            'inv_no' => (string) ($payload['InvNo'] ?? ''),
            'template_no' => (string) ($payload['InvoiceTemplateID'] ?? $payload['InvTemplateNo'] ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     * @throws LocalizedException
     */
    private function fetchFromStatus(string $identifier, int $storeId, int $inputType): array
    {
        /** @var \Secomm\EInvoiceMisa\Model\Document\MisaInvoiceDocumentService $documentService */
        $documentService = $this->documentServicePool->get($storeId);
        $response = $documentService->getStatusByInput($identifier, $inputType, $storeId);

        $row = $this->extractStatusRow($response);

        return [
            'inv_no' => (string) ($row['InvNo'] ?? $row['InvoiceNo'] ?? ''),
            'template_no' => (string) ($row['InvTemplateNo'] ?? $row['InvoiceTemplateID'] ?? ''),
            'inv_series' => (string) ($row['InvSeries'] ?? $row['InvoiceSeries'] ?? ''),
            'inv_date' => substr((string) ($row['InvDate'] ?? $row['InvoiceDate'] ?? $row['PublishedTime'] ?? ''), 0, 10),
            'transaction_id' => (string) ($row['TransactionID'] ?? $identifier),
        ];
    }

    /**
     * @param array<string, string> $org
     * @param array<string, string> $fetched
     * @return array<string, string>
     */
    private function mergeOriginFields(array $org, array $fetched): array
    {
        foreach ($fetched as $key => $value) {
            if ($value !== '' && ($org[$key] ?? '') === '') {
                $org[$key] = $value;
            }
        }

        return $org;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function extractStatusRow(array $response): array
    {
        $data = $response['data'] ?? null;
        if (is_string($data) && $data !== '') {
            try {
                $decoded = $this->json->unserialize($data);
            } catch (\InvalidArgumentException) {
                return [];
            }
            $data = $decoded;
        }

        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }

        return is_array($data) ? $data : [];
    }
}
