<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Cron;

use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceLog\Api\IssueLogRepositoryInterface;
use Secomm\EInvoiceMisa\Model\Client\MisaApiClient;
use Secomm\EInvoiceMisa\Model\Client\MisaTokenProvider;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;

/**
 * Optional reconciliation: compare MeInvoice paging vs local issue_log (§13.2).
 */
class ReconcileIssuedInvoices
{
    public function __construct(
        private readonly MisaConfig $misaConfig,
        private readonly MisaTokenProvider $tokenProvider,
        private readonly MisaApiClient $apiClient,
        private readonly IssueLogRepositoryInterface $issueLogRepository,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->misaConfig->isInvoiceWithCode()) {
            return;
        }

        try {
            $token = $this->tokenProvider->getToken(null);
            $today = date('Y-m-d');
            $response = $this->apiClient->getInvoicePaging([
                'fromDate' => $today,
                'toDate' => $today,
                'page' => '1',
                'pageSize' => '100',
            ], $token, null);

            if (!(bool) ($response['success'] ?? false)) {
                $this->logger->warning('EInvoice reconcile paging failed', ['response' => $response]);
                return;
            }

            $remoteIds = $this->extractTransactionIds($response);
            $this->logger->info('EInvoice reconcile completed', [
                'remote_count' => count($remoteIds),
                'date' => $today,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('EInvoice reconcile error', ['message' => $exception->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $response
     * @return list<string>
     */
    private function extractTransactionIds(array $response): array
    {
        $data = $response['data'] ?? [];
        if (is_string($data) && $data !== '') {
            try {
                $data = $this->json->unserialize($data);
            } catch (\InvalidArgumentException) {
                return [];
            }
        }

        if (!is_array($data)) {
            return [];
        }

        $ids = [];
        foreach ($data as $row) {
            if (is_array($row) && isset($row['TransactionID'])) {
                $ids[] = (string) $row['TransactionID'];
            }
        }

        return $ids;
    }
}
