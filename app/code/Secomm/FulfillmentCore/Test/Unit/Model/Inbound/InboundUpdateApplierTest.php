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
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\FulfillmentCore\Api\Data\InboundUpdate;
use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Api\FulfillmentStatusMapperInterface;
use Secomm\FulfillmentCore\Api\NormalizedFulfillmentStatus;
use Secomm\FulfillmentCore\Model\FulfillmentExport;
use Secomm\FulfillmentCore\Model\FulfillmentState;
use Secomm\FulfillmentCore\Model\FulfillmentStateFactory;
use Secomm\FulfillmentCore\Model\Inbound\InboundUpdateApplier;
use Secomm\FulfillmentCore\Model\Inbound\MapperPool;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\Collection as ExportCollection;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory as ExportCollectionFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState as StateResource;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState\Collection as StateCollection;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState\CollectionFactory as StateCollectionFactory;

class InboundUpdateApplierTest extends TestCase
{
    public function testApplyPersistsStateAndCommentForMagentoOrigin(): void
    {
        $mapper = $this->createMock(FulfillmentStatusMapperInterface::class);
        $mapper->method('getServiceCode')->willReturn('stub_oms');
        $mapper->method('map')->willReturn(NormalizedFulfillmentStatus::SHIPPED);

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
        $stateResource->expects($this->once())->method('save')->with($state);

        $order = $this->createMock(Order::class);
        $order->expects($this->once())->method('addCommentToStatusHistory');
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->willReturn($order);
        $orderRepository->expects($this->once())->method('save')->with($order);

        $applier = new InboundUpdateApplier(
            new MapperPool([$mapper]),
            $exportCollectionFactory,
            $stateCollectionFactory,
            $stateFactory,
            $stateResource,
            $orderRepository,
            $this->createMock(LoggerInterface::class)
        );

        $applier->apply(new InboundUpdate(
            'stub_oms',
            'EXT-9',
            '11',
            'evt-1',
            'GHTK',
            'TRK1',
            'https://track.example/TRK1'
        ));
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
            $exportCollectionFactory,
            $this->createMock(StateCollectionFactory::class),
            $this->createMock(FulfillmentStateFactory::class),
            $stateResource,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(LoggerInterface::class)
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
            $exportCollectionFactory,
            $stateCollectionFactory,
            $this->createMock(FulfillmentStateFactory::class),
            $stateResource,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $applier->apply(new InboundUpdate('stub_oms', 'EXT-9', '11', 'evt-dup'));
    }
}
