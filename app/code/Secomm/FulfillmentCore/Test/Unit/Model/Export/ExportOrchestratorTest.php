<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Test\Unit\Model\Export;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\FulfillmentCore\Model\Log\FulfillmentLogger;
use Secomm\FulfillmentCore\Api\Data\ExportResult;
use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Api\OrderExporterInterface;
use Secomm\FulfillmentCore\Model\Config\FulfillmentConfig;
use Secomm\FulfillmentCore\Model\Export\ExporterPool;
use Secomm\FulfillmentCore\Model\Export\ExportOrchestrator;
use Secomm\FulfillmentCore\Model\FulfillmentExport;
use Secomm\FulfillmentCore\Model\FulfillmentExportFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport as ExportResource;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\Collection;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory as ExportCollectionFactory;

class ExportOrchestratorTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $rowData = [];

    public function testExportOrderCreatesMappingAndCallsExporter(): void
    {
        $config = $this->createMock(FulfillmentConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getMaxAttempts')->willReturn(5);

        $exporter = $this->createMock(OrderExporterInterface::class);
        $exporter->method('getServiceCode')->willReturn('stub_oms');
        $exporter->method('isEnabled')->willReturn(true);
        $exporter->expects($this->once())
            ->method('export')
            ->willReturn(ExportResult::ok('EXT-1'));

        $pool = new ExporterPool([$exporter]);

        $row = $this->createExportRowMock();
        $factory = $this->createMock(FulfillmentExportFactory::class);
        $factory->method('create')->willReturn($row);

        $emptyCollection = $this->createMock(Collection::class);
        $emptyCollection->method('addFieldToFilter')->willReturnSelf();
        $emptyCollection->method('setPageSize')->willReturnSelf();
        $emptyItem = $this->createMock(FulfillmentExport::class);
        $emptyItem->method('getEntityId')->willReturn(null);
        $emptyCollection->method('getFirstItem')->willReturn($emptyItem);

        $collectionFactory = $this->createMock(ExportCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($emptyCollection);

        $resource = $this->createMock(ExportResource::class);
        $resource->expects($this->atLeastOnce())->method('save')->with($row);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(10);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getStoreId')->willReturn(1);

        $orchestrator = new ExportOrchestrator(
            $config,
            $pool,
            $factory,
            $resource,
            $collectionFactory,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createDateTimeMock(),
            $this->createMock(FulfillmentLogger::class)
        );

        $orchestrator->exportOrder($order);

        $this->assertSame(ExportPushStatus::ORIGIN_MAGENTO, $this->rowData['origin'] ?? null);
        $this->assertSame(ExportPushStatus::SUCCESS, $this->rowData['push_status'] ?? null);
        $this->assertSame('EXT-1', $this->rowData['external_order_id'] ?? null);
    }

    public function testSkipsDisabledExporter(): void
    {
        $config = $this->createMock(FulfillmentConfig::class);
        $config->method('isEnabled')->willReturn(true);

        $exporter = $this->createMock(OrderExporterInterface::class);
        $exporter->method('getServiceCode')->willReturn('stub_oms');
        $exporter->method('isEnabled')->willReturn(false);
        $exporter->expects($this->never())->method('export');

        $orchestrator = new ExportOrchestrator(
            $config,
            new ExporterPool([$exporter]),
            $this->createMock(FulfillmentExportFactory::class),
            $this->createMock(ExportResource::class),
            $this->createMock(ExportCollectionFactory::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->createDateTimeMock(),
            $this->createMock(FulfillmentLogger::class)
        );

        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $orchestrator->exportOrder($order);
    }

    /**
     * @return FulfillmentExport&MockObject
     */
    private function createExportRowMock(): FulfillmentExport
    {
        $this->rowData = [];
        $row = $this->getMockBuilder(FulfillmentExport::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getEntityId',
                'getMagentoOrderId',
                'setMagentoOrderId',
                'getMagentoIncrementId',
                'setMagentoIncrementId',
                'getServiceCode',
                'setServiceCode',
                'getExternalOrderId',
                'setExternalOrderId',
                'getOrigin',
                'setOrigin',
                'getPushStatus',
                'setPushStatus',
                'getAttemptCount',
                'setAttemptCount',
                'getLastError',
                'setLastError',
                'setLastPushedAt',
            ])
            ->getMock();

        $data = &$this->rowData;
        $row->method('getEntityId')->willReturnCallback(fn () => $data['entity_id'] ?? 1);
        $row->method('getMagentoOrderId')->willReturnCallback(fn () => (int) ($data['magento_order_id'] ?? 10));
        $row->method('setMagentoOrderId')->willReturnCallback(function (int $id) use (&$data, $row) {
            $data['magento_order_id'] = $id;
            return $row;
        });
        $row->method('setMagentoIncrementId')->willReturnCallback(function (string $id) use (&$data, $row) {
            $data['magento_increment_id'] = $id;
            return $row;
        });
        $row->method('getServiceCode')->willReturnCallback(fn () => (string) ($data['service_code'] ?? 'stub_oms'));
        $row->method('setServiceCode')->willReturnCallback(function (string $code) use (&$data, $row) {
            $data['service_code'] = $code;
            return $row;
        });
        $row->method('getExternalOrderId')->willReturnCallback(fn () => $data['external_order_id'] ?? null);
        $row->method('setExternalOrderId')->willReturnCallback(function (?string $id) use (&$data, $row) {
            $data['external_order_id'] = $id;
            return $row;
        });
        $row->method('getOrigin')->willReturnCallback(fn () => $data['origin'] ?? null);
        $row->method('setOrigin')->willReturnCallback(function (string $origin) use (&$data, $row) {
            $data['origin'] = $origin;
            return $row;
        });
        $row->method('getPushStatus')->willReturnCallback(fn () => $data['push_status'] ?? ExportPushStatus::PENDING);
        $row->method('setPushStatus')->willReturnCallback(function (string $status) use (&$data, $row) {
            $data['push_status'] = $status;
            return $row;
        });
        $row->method('getAttemptCount')->willReturnCallback(fn () => (int) ($data['attempt_count'] ?? 0));
        $row->method('setAttemptCount')->willReturnCallback(function (int $count) use (&$data, $row) {
            $data['attempt_count'] = $count;
            return $row;
        });
        $row->method('setLastError')->willReturnCallback(function (?string $error) use (&$data, $row) {
            $data['last_error'] = $error;
            return $row;
        });
        $row->method('setLastPushedAt')->willReturnCallback(function (?string $dt) use (&$data, $row) {
            $data['last_pushed_at'] = $dt;
            return $row;
        });

        return $row;
    }

    private function createDateTimeMock(): DateTime
    {
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-08-20 00:00:00');
        return $dateTime;
    }
}
