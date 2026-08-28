<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Cron;

use Psr\Log\LoggerInterface;
use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Model\Inbound\InboundUpdateApplier;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory as ExportCollectionFactory;
use Secomm\Pancake\Model\Client\PosClient;
use Secomm\Pancake\Model\Client\PosClientException;
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
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isPollEnabled()) {
            return;
        }

        $collection = $this->exportCollectionFactory->create();
        $collection->addFieldToFilter('service_code', PancakeOrderExporter::SERVICE_CODE)
            ->addFieldToFilter('origin', ExportPushStatus::ORIGIN_MAGENTO)
            ->addFieldToFilter('push_status', ExportPushStatus::SUCCESS)
            ->addFieldToFilter('external_order_id', ['notnull' => true])
            ->setPageSize(40)
            ->setCurPage(1);

        foreach ($collection as $row) {
            $externalId = (string) $row->getExternalOrderId();
            if ($externalId === '') {
                continue;
            }
            try {
                $order = $this->posClient->getOrder($externalId);
                $update = $this->parser->parse($this->unwrapOrder($order));
                if ($update === null) {
                    continue;
                }
                $this->applier->apply($update);
            } catch (PosClientException $e) {
                $this->logger->warning(
                    'Pancake poll skipped one order.',
                    ['increment_id' => (string) $row->getMagentoIncrementId(), 'error' => $e->getMessage()]
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function unwrapOrder(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }
}
