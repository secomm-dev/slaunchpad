<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Export;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Secomm\FulfillmentCore\Model\Log\FulfillmentLogger;
use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Api\OrderExporterInterface;
use Secomm\FulfillmentCore\Model\Config\FulfillmentConfig;
use Secomm\FulfillmentCore\Model\FulfillmentExport;
use Secomm\FulfillmentCore\Model\FulfillmentExportFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport as ExportResource;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory as ExportCollectionFactory;
use Throwable;

/**
 * Drives outbound OMS export for Magento-origin orders. Never throws to callers.
 */
class ExportOrchestrator
{
    public function __construct(
        private readonly FulfillmentConfig $config,
        private readonly ExporterPool $exporterPool,
        private readonly FulfillmentExportFactory $exportFactory,
        private readonly ExportResource $exportResource,
        private readonly ExportCollectionFactory $exportCollectionFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly DateTime $dateTime,
        private readonly FulfillmentLogger $fulfillmentLogger
    ) {
    }

    /**
     * Export order through all enabled registered exporters.
     *
     * @param OrderInterface $order Placed Magento order
     */
    public function exportOrder(OrderInterface $order): void
    {
        try {
            $orderId = (int) $order->getEntityId();
            if ($orderId <= 0) {
                return;
            }

            $storeId = (int) $order->getStoreId();
            if (!$this->config->isEnabled($storeId)) {
                return;
            }

            $exported = false;
            foreach ($this->exporterPool->getExporters() as $exporter) {
                if (!$exporter->isEnabled($storeId)) {
                    continue;
                }
                $exported = true;
                $this->exportForService($order, $exporter);
            }

            if (!$exported) {
                return;
            }
        } catch (Throwable) {
            return;
        }
    }

    /**
     * Retry a single pending/failed mapping row by entity id.
     *
     * @param int $exportEntityId secomm_fulfillment_export.entity_id
     */
    public function retryExport(int $exportEntityId): void
    {
        $serviceCode = '';
        try {
            /** @var FulfillmentExport $row */
            $row = $this->exportFactory->create();
            $this->exportResource->load($row, $exportEntityId);
            if (!$row->getEntityId()) {
                return;
            }
            $serviceCode = $row->getServiceCode();
            if ($row->getOrigin() !== ExportPushStatus::ORIGIN_MAGENTO) {
                return;
            }
            if ($row->getPushStatus() === ExportPushStatus::SUCCESS) {
                return;
            }

            $order = $this->orderRepository->get($row->getMagentoOrderId());
            if (!$this->config->isEnabled((int) $order->getStoreId())) {
                return;
            }

            $exporter = $this->exporterPool->getExporter($row->getServiceCode());
            if ($exporter === null || !$exporter->isEnabled((int) $order->getStoreId())) {
                return;
            }

            $max = $this->config->getMaxAttempts((int) $order->getStoreId());
            if ($row->getAttemptCount() >= $max) {
                return;
            }

            $this->runExportAttempt($order, $exporter, $row);
        } catch (Throwable $e) {
            $this->fulfillmentLogger->error(
                $serviceCode,
                'FulfillmentCore retryExport failed',
                ['export_id' => $exportEntityId, 'error' => $e->getMessage()]
            );
        }
    }

    private function exportForService(OrderInterface $order, OrderExporterInterface $exporter): void
    {
        $row = $this->loadOrCreateRow($order, $exporter->getServiceCode());
        if ($row->getPushStatus() === ExportPushStatus::SUCCESS) {
            return;
        }

        $max = $this->config->getMaxAttempts((int) $order->getStoreId());
        if ($row->getAttemptCount() >= $max) {
            return;
        }

        $this->runExportAttempt($order, $exporter, $row);
    }

    private function runExportAttempt(
        OrderInterface $order,
        OrderExporterInterface $exporter,
        FulfillmentExport $row
    ): void {
        $row->setAttemptCount($row->getAttemptCount() + 1);
        $row->setLastPushedAt($this->dateTime->gmtDate());

        try {
            $result = $exporter->export($order);
        } catch (Throwable $e) {
            $this->fulfillmentLogger->error(
                $exporter->getServiceCode(),
                'FulfillmentCore exporter threw',
                [
                    'order_id' => (int) $order->getEntityId(),
                    'error' => $e->getMessage(),
                ]
            );
            $row->setPushStatus(ExportPushStatus::FAILED);
            $row->setLastError('exporter_exception');
            $this->exportResource->save($row);
            return;
        }

        if ($result->isSuccess()) {
            $row->setPushStatus(ExportPushStatus::SUCCESS);
            $row->setExternalOrderId($result->getExternalOrderId());
            $row->setLastError(null);
        } else {
            $row->setPushStatus(ExportPushStatus::FAILED);
            $row->setLastError($result->getErrorCode() ?: 'export_failed');
        }

        $this->exportResource->save($row);
    }

    private function loadOrCreateRow(OrderInterface $order, string $serviceCode): FulfillmentExport
    {
        $collection = $this->exportCollectionFactory->create();
        $collection->addFieldToFilter('magento_order_id', (int) $order->getEntityId());
        $collection->addFieldToFilter('service_code', $serviceCode);
        $collection->setPageSize(1);

        /** @var FulfillmentExport|null $existing */
        $existing = $collection->getFirstItem();
        if ($existing && $existing->getEntityId()) {
            return $existing;
        }

        /** @var FulfillmentExport $row */
        $row = $this->exportFactory->create();
        $row->setMagentoOrderId((int) $order->getEntityId());
        $row->setMagentoIncrementId((string) $order->getIncrementId());
        $row->setServiceCode($serviceCode);
        $row->setOrigin(ExportPushStatus::ORIGIN_MAGENTO);
        $row->setPushStatus(ExportPushStatus::PENDING);
        $row->setAttemptCount(0);
        $this->exportResource->save($row);

        return $row;
    }
}
