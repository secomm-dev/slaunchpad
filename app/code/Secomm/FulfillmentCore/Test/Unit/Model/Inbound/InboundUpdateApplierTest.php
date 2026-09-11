<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Test\Unit\Model\Inbound;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use PHPUnit\Framework\TestCase;
use Secomm\FulfillmentCore\Api\Data\InboundUpdate;
use Secomm\FulfillmentCore\Api\Data\StatusMapInterface;
use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Api\FulfillmentStatusMapperInterface;
use Secomm\FulfillmentCore\Api\NormalizedFulfillmentStatus;
use Secomm\FulfillmentCore\Api\StatusMapResolverInterface;
use Secomm\FulfillmentCore\Model\FulfillmentExport;
use Secomm\FulfillmentCore\Model\FulfillmentState;
use Secomm\FulfillmentCore\Model\FulfillmentStateFactory;
use Secomm\FulfillmentCore\Model\Inbound\InboundUpdateApplier;
use Secomm\FulfillmentCore\Model\Inbound\MapperPool;
use Secomm\FulfillmentCore\Model\Inbound\OrderDocumentsApplier;
use Secomm\FulfillmentCore\Model\Log\FulfillmentLogger;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\Collection as ExportCollection;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory as ExportCollectionFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState as StateResource;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState\Collection as StateCollection;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState\CollectionFactory as StateCollectionFactory;

class InboundUpdateApplierTest extends TestCase
{
    public function testMappedStatusChangesOrderStatusAndComment(): void
    {
        $mapper = $this->createMock(FulfillmentStatusMapperInterface::class);
        $mapper->method('getServiceCode')->willReturn('stub_oms');
        $mapper->method('map')->willReturn(NormalizedFulfillmentStatus::SHIPPED);

        $statusMap = $this->createMock(StatusMapInterface::class);
        $statusMap->method('getMagentoOrderStatus')->willReturn(Order::STATE_PROCESSING);

        $statusMapResolver = $this->createMock(StatusMapResolverInterface::class);
        $statusMapResolver->method('resolve')->with('stub_oms', '11')->willReturn($statusMap);

        $export = $this->createMock(FulfillmentExport::class);
        $export->method('getEntityId')->willReturn(1);
        $export->method('getMagentoOrderId')->willReturn(10);
        $export->method('getOrigin')->willReturn(ExportPushStatus::ORIGIN_MAGENTO);

        $exportCollection = $this->createMock(ExportCollection::class);
        $exportCollection->method('addFieldToFilter')->willReturnSelf();
        $exportCollection->method('setPageSize')->willReturnSelf();
        $exportCollection->method('getFirstItem')->willReturn($export);

        $exportCollectionFactory = $this->createMock(ExportCollectionFactory::class);
        $exportCollectionFactory->method('create')->willReturn($exportCollection);

        $emptyState = $this->createMock(FulfillmentState::class);
        $emptyState->method('getEntityId')->willReturn(null);

        $stateCollection = $this->createMock(StateCollection::class);
        $stateCollection->method('addFieldToFilter')->willReturnSelf();
        $stateCollection->method('setPageSize')->willReturnSelf();
        $stateCollection->method('getFirstItem')->willReturn($emptyState);

        $stateCollectionFactory = $this->createMock(StateCollectionFactory::class);
        $stateCollectionFactory->method('create')->willReturn($stateCollection);

        $state = $this->createMock(FulfillmentState::class);
        $state->method('getEntityId')->willReturn(null);
        $state->expects($this->once())->method('setNormalizedStatus')
            ->with(NormalizedFulfillmentStatus::SHIPPED)
            ->willReturnSelf();
        $state->method('setMagentoOrderId')->willReturnSelf();
        $state->method('setServiceCode')->willReturnSelf();
        $state->method('setExternalOrderId')->willReturnSelf();
        $state->method('setRawStatus')->willReturnSelf();
        $state->method('setLastEventId')->willReturnSelf();
        $state->method('setCarrierName')->willReturnSelf();
        $state->method('setTrackingNumber')->willReturnSelf();
        $state->method('setTrackingUrl')->willReturnSelf();

        $stateFactory = $this->createMock(FulfillmentStateFactory::class);
        $stateFactory->method('create')->willReturn($state);

        $stateResource = $this->createMock(StateResource::class);
        $stateResource->expects($this->exactly(2))->method('save')->with($state);

        $order = $this->createMock(Order::class);
        $order->expects($this->once())->method('setState')->with(Order::STATE_PROCESSING)->willReturnSelf();
        $order->expects($this->once())->method('addCommentToStatusHistory')
            ->with($this->anything(), Order::STATE_PROCESSING, true);
        $order->method('canInvoice')->willReturn(false);
        $order->method('canShip')->willReturn(false);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->willReturn($order);
        $orderRepository->expects($this->once())->method('save')->with($order);

        $orderConfig = $this->createMock(OrderConfig::class);
        $orderConfig->method('getStates')->willReturn([
            Order::STATE_PROCESSING => ['statuses' => [Order::STATE_PROCESSING => true]],
        ]);

        $documents = $this->createMock(OrderDocumentsApplier::class);
        $documents->expects($this->once())->method('apply')->willReturn($order);

        $applier = new InboundUpdateApplier(
            new MapperPool([$mapper]),
            $statusMapResolver,
            $orderConfig,
            $documents,
            $exportCollectionFactory,
            $stateCollectionFactory,
            $stateFactory,
            $stateResource,
            $orderRepository,
            $this->createMock(FulfillmentLogger::class)
        );

        $result = $applier->applyToExport($export, new InboundUpdate(
            'stub_oms',
            'EXT-9',
            '11',
            'evt-1',
            'GHTK',
            'TRK1',
            'https://track.example/TRK1'
        ));
        $this->assertSame('applied_status', $result);
    }

    public function testUnmappedStatusCommentOnly(): void
    {
        $mapper = $this->createMock(FulfillmentStatusMapperInterface::class);
        $mapper->method('getServiceCode')->willReturn('stub_oms');
        $mapper->method('map')->willReturn(NormalizedFulfillmentStatus::UNKNOWN);

        $statusMapResolver = $this->createMock(StatusMapResolverInterface::class);
        $statusMapResolver->method('resolve')->willReturn(null);

        $export = $this->createMock(FulfillmentExport::class);
        $export->method('getEntityId')->willReturn(1);
        $export->method('getMagentoOrderId')->willReturn(10);

        $exportCollection = $this->createMock(ExportCollection::class);
        $exportCollection->method('addFieldToFilter')->willReturnSelf();
        $exportCollection->method('setPageSize')->willReturnSelf();
        $exportCollection->method('getFirstItem')->willReturn($export);

        $exportCollectionFactory = $this->createMock(ExportCollectionFactory::class);
        $exportCollectionFactory->method('create')->willReturn($exportCollection);

        $emptyState = $this->createMock(FulfillmentState::class);
        $emptyState->method('getEntityId')->willReturn(null);

        $stateCollection = $this->createMock(StateCollection::class);
        $stateCollection->method('addFieldToFilter')->willReturnSelf();
        $stateCollection->method('setPageSize')->willReturnSelf();
        $stateCollection->method('getFirstItem')->willReturn($emptyState);

        $stateCollectionFactory = $this->createMock(StateCollectionFactory::class);
        $stateCollectionFactory->method('create')->willReturn($stateCollection);

        $state = $this->createMock(FulfillmentState::class);
        $state->method('getEntityId')->willReturn(null);
        $state->method('setNormalizedStatus')->willReturnSelf();
        $state->method('setMagentoOrderId')->willReturnSelf();
        $state->method('setServiceCode')->willReturnSelf();
        $state->method('setExternalOrderId')->willReturnSelf();
        $state->method('setRawStatus')->willReturnSelf();
        $state->method('setLastEventId')->willReturnSelf();
        $state->method('setCarrierName')->willReturnSelf();
        $state->method('setTrackingNumber')->willReturnSelf();
        $state->method('setTrackingUrl')->willReturnSelf();

        $stateFactory = $this->createMock(FulfillmentStateFactory::class);
        $stateFactory->method('create')->willReturn($state);

        $stateResource = $this->createMock(StateResource::class);
        $stateResource->expects($this->exactly(2))->method('save');

        $order = $this->createMock(Order::class);
        $order->expects($this->never())->method('setState');
        $order->expects($this->once())->method('addCommentToStatusHistory')
            ->with($this->anything(), false, true);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->willReturn($order);
        $orderRepository->expects($this->once())->method('save')->with($order);

        $applier = new InboundUpdateApplier(
            new MapperPool([$mapper]),
            $statusMapResolver,
            $this->createMock(OrderConfig::class),
            $this->createMock(OrderDocumentsApplier::class),
            $exportCollectionFactory,
            $stateCollectionFactory,
            $stateFactory,
            $stateResource,
            $orderRepository,
            $this->createMock(FulfillmentLogger::class)
        );

        $result = $applier->applyToExport(
            $export,
            new InboundUpdate('stub_oms', 'EXT-9', '4242', 'evt-2')
        );
        $this->assertSame('applied_comment_only', $result);
    }

    public function testNoOpWithoutMagentoOriginExport(): void
    {
        $emptyExport = $this->createMock(FulfillmentExport::class);
        $emptyExport->method('getEntityId')->willReturn(null);

        $exportCollection = $this->createMock(ExportCollection::class);
        $exportCollection->method('addFieldToFilter')->willReturnSelf();
        $exportCollection->method('setPageSize')->willReturnSelf();
        $exportCollection->method('getFirstItem')->willReturn($emptyExport);

        $exportCollectionFactory = $this->createMock(ExportCollectionFactory::class);
        $exportCollectionFactory->method('create')->willReturn($exportCollection);

        $stateResource = $this->createMock(StateResource::class);
        $stateResource->expects($this->never())->method('save');

        $applier = new InboundUpdateApplier(
            new MapperPool([]),
            $this->createMock(StatusMapResolverInterface::class),
            $this->createMock(OrderConfig::class),
            $this->createMock(OrderDocumentsApplier::class),
            $exportCollectionFactory,
            $this->createMock(StateCollectionFactory::class),
            $this->createMock(FulfillmentStateFactory::class),
            $stateResource,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(FulfillmentLogger::class)
        );

        $applier->apply(new InboundUpdate('stub_oms', 'MANUAL-1', '11'));
    }

    public function testIdempotentEventIdSkips(): void
    {
        $export = $this->createMock(FulfillmentExport::class);
        $export->method('getEntityId')->willReturn(1);
        $export->method('getMagentoOrderId')->willReturn(10);

        $exportCollection = $this->createMock(ExportCollection::class);
        $exportCollection->method('addFieldToFilter')->willReturnSelf();
        $exportCollection->method('setPageSize')->willReturnSelf();
        $exportCollection->method('getFirstItem')->willReturn($export);

        $exportCollectionFactory = $this->createMock(ExportCollectionFactory::class);
        $exportCollectionFactory->method('create')->willReturn($exportCollection);

        $state = $this->createMock(FulfillmentState::class);
        $state->method('getEntityId')->willReturn(5);
        $state->method('getLastEventId')->willReturn('evt-dup');

        $stateCollection = $this->createMock(StateCollection::class);
        $stateCollection->method('addFieldToFilter')->willReturnSelf();
        $stateCollection->method('setPageSize')->willReturnSelf();
        $stateCollection->method('getFirstItem')->willReturn($state);

        $stateCollectionFactory = $this->createMock(StateCollectionFactory::class);
        $stateCollectionFactory->method('create')->willReturn($stateCollection);

        $stateResource = $this->createMock(StateResource::class);
        $stateResource->expects($this->never())->method('save');

        $applier = new InboundUpdateApplier(
            new MapperPool([]),
            $this->createMock(StatusMapResolverInterface::class),
            $this->createMock(OrderConfig::class),
            $this->createMock(OrderDocumentsApplier::class),
            $exportCollectionFactory,
            $stateCollectionFactory,
            $this->createMock(FulfillmentStateFactory::class),
            $stateResource,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(FulfillmentLogger::class)
        );

        $applier->apply(new InboundUpdate('stub_oms', 'EXT-9', '11', 'evt-dup'));
    }
}
