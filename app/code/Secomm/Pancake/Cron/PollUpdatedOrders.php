<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Cron;

use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Secomm\FulfillmentCore\Api\Data\InboundUpdate;
use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Model\FulfillmentExport;
use Secomm\FulfillmentCore\Model\Inbound\InboundUpdateApplier;
use Secomm\FulfillmentCore\Model\Log\FulfillmentLogger;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory as ExportCollectionFactory;
use Secomm\Pancake\Model\Client\PosClient;
use Secomm\Pancake\Model\Config\PancakeConfig;
use Secomm\Pancake\Model\Inbound\OrderPayloadParser;
use Secomm\Pancake\Model\Order\PancakeOrderExporter;

/**
 * Poll GET order only for Magento-origin mapping rows (never pulls manual POS orders).
 */
class PollUpdatedOrders
{
    public function __construct(
        private readonly PancakeConfig $config,
        private readonly ExportCollectionFactory $exportCollectionFactory,
        private readonly PosClient $posClient,
        private readonly OrderPayloadParser $parser,
        private readonly InboundUpdateApplier $applier,
        private readonly FulfillmentLogger $fulfillmentLogger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $systemLogger
    ) {
    }

    public function execute(): void
    {
        $this->fulfillmentLogger->info(
            PancakeOrderExporter::SERVICE_CODE,
            'Pancake poll cron started.'
        );

        $collection = $this->exportCollectionFactory->create();
        $collection->addFieldToFilter('service_code', PancakeOrderExporter::SERVICE_CODE)
            ->addFieldToFilter('origin', ExportPushStatus::ORIGIN_MAGENTO)
            ->addFieldToFilter('push_status', ExportPushStatus::SUCCESS)
            ->addFieldToFilter('external_order_id', ['notnull' => true])
            ->setOrder('entity_id', 'DESC')
            ->setPageSize(40)
            ->setCurPage(1);

        $scanned = 0;
        $applied = 0;
        $failed = 0;
        $skippedConfig = 0;


        foreach ($collection as $row) {
            if (!$row instanceof FulfillmentExport) {
                continue;
            }
            $externalId = (string) $row->getExternalOrderId();
            if ($externalId === '') {
                continue;
            }

            $storeId = $this->resolveStoreId($row);
            if (!$this->config->isEnabled($storeId) || !$this->config->isPollEnabled($storeId)) {
                $skippedConfig++;
                $this->note(
                    'Pancake poll skipped one row: Enable or Poll is No for order store.',
                    [
                        'increment_id' => (string) $row->getMagentoIncrementId(),
                        'store_id' => $storeId,
                        'enabled' => $this->config->isEnabled($storeId) ? 1 : 0,
                        'poll' => $this->config->isPollEnabled($storeId) ? 1 : 0,
                    ]
                );
                continue;
            }

            $scanned++;
            try {
                $payload = $this->unwrapOrder($this->posClient->getOrder($externalId, $storeId));
                $rawStatus = $this->extractRawStatus($payload);
                $this->writePosStatus($row, $rawStatus);
                $update = $this->parser->parse($payload);
                if ($update === null) {
                    $this->note(
                        'Pancake poll ignored payload (no id/status parse).',
                        [
                            'increment_id' => (string) $row->getMagentoIncrementId(),
                            'external_order_id' => $externalId,
                            'keys' => implode(',', array_keys($payload)),
                        ]
                    );
                    continue;
                }
                $this->rememberPosOrderId($row, $update->getExternalOrderId());
                $this->applier->applyToExport($row, $this->bindToExportRow($row, $update));
                $applied++;
                $this->fulfillmentLogger->info(
                    PancakeOrderExporter::SERVICE_CODE,
                    'Pancake poll row finished.',
                    [
                        'increment_id' => (string) $row->getMagentoIncrementId(),
                        'external_order_id' => (string) $row->getExternalOrderId(),
                        'raw_status' => $update->getRawStatus(),
                    ]
                );
            } catch (\Throwable $e) {
                $failed++;
                $this->note(
                    'Pancake poll skipped one order.',
                    [
                        'increment_id' => (string) $row->getMagentoIncrementId(),
                        'error' => $e->getMessage(),
                    ],
                    true
                );
            }
        }

        $summary = [
            'scanned' => $scanned,
            'applied' => $applied,
            'failed' => $failed,
            'skipped_config' => $skippedConfig,
        ];
        $this->fulfillmentLogger->info(
            PancakeOrderExporter::SERVICE_CODE,
            'Pancake poll cron finished.',
            $summary
        );
        $this->systemLogger->info('Pancake poll cron finished.', $summary);

        if ($scanned === 0 && $skippedConfig > 0) {
            throw new \RuntimeException(
                'Pancake poll: all rows skipped because Enable/Poll is No for those order stores. '
                . 'Set Stores → Configuration → Pancake POS (correct website/store) Enable=Yes and Poll=Yes.'
            );
        }
    }

    /**
     * Always write fulfillment file log (if enabled) and Magento system.log for cron visibility.
     *
     * @param array<string, scalar|null> $context
     */
    private function note(string $message, array $context = [], bool $error = false): void
    {
        if ($error) {
            $this->fulfillmentLogger->error(PancakeOrderExporter::SERVICE_CODE, $message, $context);
            $this->systemLogger->error($message, $context);
            return;
        }
        $this->fulfillmentLogger->info(PancakeOrderExporter::SERVICE_CODE, $message, $context);
        $this->systemLogger->info($message, $context);
    }

    /**
     * @param FulfillmentExport $row Export mapping row
     */
    private function resolveStoreId(FulfillmentExport $row): ?int
    {
        try {
            $order = $this->orderRepository->get($row->getMagentoOrderId());
            return $order->getStoreId() !== null ? (int) $order->getStoreId() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Stamp secomm_fulfillment_export.current_status from the POS order payload.
     *
     * @param FulfillmentExport $row Export mapping row
     * @param string $rawStatus POS status code; empty skips the write
     */
    private function writePosStatus(FulfillmentExport $row, string $rawStatus): void
    {
        if ($rawStatus === '' || $row->getCurrentStatus() === $rawStatus) {
            return;
        }
        $row->setCurrentStatus($rawStatus);
        $row->getResource()->save($row);
        $this->fulfillmentLogger->info(
            PancakeOrderExporter::SERVICE_CODE,
            'Pancake poll stored current_status.',
            [
                'increment_id' => (string) $row->getMagentoIncrementId(),
                'current_status' => $rawStatus,
            ]
        );
    }

    /**
     * @param array<string, mixed> $order POS order object
     */
    private function extractRawStatus(array $order): string
    {
        foreach (['status', 'order_status'] as $key) {
            if (isset($order[$key]) && $order[$key] !== '' && !is_array($order[$key])) {
                return (string) $order[$key];
            }
        }
        if (isset($order['order']) && is_array($order['order'])) {
            return $this->extractRawStatus($order['order']);
        }

        return '';
    }

    /**
     * Keep the POS numeric id when the create response only echoed Magento increment_id.
     */
    private function rememberPosOrderId(FulfillmentExport $row, string $parsedId): void
    {
        $stored = (string) $row->getExternalOrderId();
        $incrementId = (string) $row->getMagentoIncrementId();
        if ($parsedId === '' || $parsedId === $stored || $parsedId === $incrementId || !ctype_digit($parsedId)) {
            return;
        }
        $row->setExternalOrderId($parsedId);
        $row->getResource()->save($row);
    }

    private function bindToExportRow(FulfillmentExport $row, InboundUpdate $update): InboundUpdate
    {
        return new InboundUpdate(
            $update->getServiceCode(),
            (string) $row->getExternalOrderId(),
            $update->getRawStatus(),
            $update->getEventId(),
            $update->getCarrierName(),
            $update->getTrackingNumber(),
            $update->getTrackingUrl()
        );
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function unwrapOrder(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            $data = $response['data'];
            if ($this->looksLikeOrder($data)) {
                return $data;
            }
            if (isset($data['order']) && is_array($data['order'])) {
                return $data['order'];
            }
            if (isset($data[0]) && is_array($data[0]) && $this->looksLikeOrder($data[0])) {
                return $data[0];
            }
            return $data;
        }
        if (isset($response['order']) && is_array($response['order'])) {
            return $response['order'];
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function looksLikeOrder(array $node): bool
    {
        return isset($node['id'])
            || isset($node['order_id'])
            || isset($node['custom_id'])
            || isset($node['status']);
    }
}
