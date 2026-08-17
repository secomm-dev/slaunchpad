<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Tracking;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track as TrackResource;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track\Collection as TrackCollection;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory as TrackCollectionFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;
use Secomm\ShippingCore\Model\CarrierTrackingState;
use Secomm\ShippingCore\Model\CarrierTrackingStateFactory;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState as StateResource;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\Collection as StateCollection;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\CollectionFactory as StateCollectionFactory;
use Secomm\ShippingCore\Model\Tracking\ShipmentTrackingProcessor;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

class ShipmentTrackingProcessorTest extends TestCase
{
    /** @var array|null Simulated stored state row (null = fresh). */
    private ?array $stored = null;
    private Track $track;
    private \PHPUnit\Framework\MockObject\MockObject $eventManager;
    private \PHPUnit\Framework\MockObject\MockObject $shipmentRepository;
    private \PHPUnit\Framework\MockObject\MockObject $stateResource;
    private \PHPUnit\Framework\MockObject\MockObject $trackResource;
    /** @var string[] Dispatched event names. */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->stored = null;
        $this->dispatched = [];

        $this->track = $this->createMock(Track::class);
        $this->track->method('getParentId')->willReturn(77);

        $this->eventManager = $this->createMock(ManagerInterface::class);
        $this->eventManager->method('dispatch')->willReturnCallback(function (string $name) {
            $this->dispatched[] = $name;
            return null;
        });

        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->stateResource = $this->createMock(StateResource::class);
        $this->trackResource = $this->createMock(TrackResource::class);
    }

    private function stateMock(): CarrierTrackingState
    {
        $state = $this->getMockBuilder(CarrierTrackingState::class)
            ->addMethods(['getNormalizedStatus', 'getCarrierStatusCode', 'getCarrierStatusUpdatedAt', 'getLastSyncedAt'])
            ->onlyMethods(['getId'])
            ->disableOriginalConstructor()
            ->getMock();

        $stored = &$this->stored;
        $state->method('getId')->willReturnCallback(fn () => $stored !== null ? 12 : null);
        $state->method('getNormalizedStatus')->willReturnCallback(fn () => $stored['status'] ?? null);
        $state->method('getCarrierStatusCode')->willReturnCallback(fn () => $stored['code'] ?? null);
        $state->method('getCarrierStatusUpdatedAt')->willReturnCallback(fn () => $stored['at'] ?? null);

        return $state;
    }

    private function stateCollectionMock(CarrierTrackingState $state): StateCollection
    {
        $collection = $this->createMock(StateCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('setCurPage')->willReturnSelf();
        $stored = &$this->stored;
        $collection->method('getIterator')->willReturnCallback(
            fn () => new \ArrayIterator($stored !== null ? [$state] : [])
        );

        return $collection;
    }

    private function trackCollectionMock(?Track $track): TrackCollection
    {
        $collection = $this->createMock(TrackCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('setCurPage')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($track !== null ? [$track] : []));

        return $collection;
    }

    private function processor(?Track $track, CarrierTrackingState $state): ShipmentTrackingProcessor
    {
        $trackCollectionFactory = $this->createMock(TrackCollectionFactory::class);
        $trackCollectionFactory->method('create')->willReturn($this->trackCollectionMock($track));

        $stateCollectionFactory = $this->createMock(StateCollectionFactory::class);
        $stateCollectionFactory->method('create')->willReturn($this->stateCollectionMock($state));

        $stateFactory = $this->createMock(CarrierTrackingStateFactory::class);
        $stateFactory->method('create')->willReturn($state);

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(strtotime('2026-08-17 16:00:00'));

        return new ShipmentTrackingProcessor(
            $trackCollectionFactory,
            $this->trackResource,
            $this->shipmentRepository,
            $stateCollectionFactory,
            $this->stateResource,
            $stateFactory,
            $this->eventManager,
            $dateTime,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function update(
        string $status,
        ?string $code = '4',
        ?int $occurredAt = null,
        string $source = 'webhook'
    ): TrackingUpdate {
        return new TrackingUpdate('ghtk', 'S10001.P1.XXXX', $status, $code, 'Dang giao', $occurredAt, $source, []);
    }

    public function testUnknownTrackReturnsFalseGracefully(): void
    {
        $this->assertFalse(
            $this->processor(null, $this->stateMock())->process($this->update(NormalizedTrackingStatus::IN_TRANSIT))
        );
        $this->assertSame([], $this->dispatched);
    }

    public function testValidUpdateAppliesStateTrackCommentAndEvents(): void
    {
        $state = $this->stateMock();
        $this->track->expects($this->once())->method('setDescription')->with($this->stringContains('DELIVERED'));
        $this->trackResource->expects($this->once())->method('save')->with($this->track);
        $this->stateResource->expects($this->once())->method('save')->with($state);

        $shipment = $this->createMock(Shipment::class);
        $shipment->expects($this->once())->method('addComment')->with($this->stringContains('DELIVERED'), false, false);
        $this->shipmentRepository->method('get')->willReturn($shipment);
        $this->shipmentRepository->expects($this->once())->method('save')->with($shipment);

        $this->assertTrue($this->processor($this->track, $state)->process($this->update(NormalizedTrackingStatus::DELIVERED, '5')));
        $this->assertSame(
            ['secomm_shipping_tracking_updated', 'secomm_shipment_carrier_delivered'],
            $this->dispatched
        );
    }

    public function testDuplicateUpdateIsNoOp(): void
    {
        $this->stored = ['status' => NormalizedTrackingStatus::IN_TRANSIT, 'code' => '4', 'at' => '2026-08-17 10:00:00'];

        $this->stateResource->expects($this->never())->method('save');
        $this->trackResource->expects($this->never())->method('save');

        $state = $this->stateMock();
        $this->assertTrue($this->processor($this->track, $state)->process($this->update(NormalizedTrackingStatus::IN_TRANSIT, '4')));
        $this->assertSame([], $this->dispatched);
    }

    public function testOutOfOrderUpdateCannotDowngradeTerminal(): void
    {
        $this->stored = ['status' => NormalizedTrackingStatus::DELIVERED, 'code' => '5', 'at' => '2026-08-17 15:00:00'];

        $this->stateResource->expects($this->never())->method('save');

        // A late IN_TRANSIT webhook (older ts) must not roll DELIVERED back.
        $state = $this->stateMock();
        $this->assertTrue(
            $this->processor($this->track, $state)->process(
                $this->update(NormalizedTrackingStatus::IN_TRANSIT, '4', strtotime('2026-08-17 14:00:00'))
            )
        );
        $this->assertSame([], $this->dispatched);
    }

    public function testStickyTerminalBlocksEvenWithoutTimestamp(): void
    {
        $this->stored = ['status' => NormalizedTrackingStatus::RETURNED, 'code' => '13', 'at' => '2026-08-17 15:00:00'];
        $this->stateResource->expects($this->never())->method('save');

        $state = $this->stateMock();
        $this->assertTrue(
            $this->processor($this->track, $state)->process($this->update(NormalizedTrackingStatus::IN_TRANSIT, '4', null))
        );
    }

    public function testOlderTimestampThanStoredIsSkipped(): void
    {
        $this->stored = ['status' => NormalizedTrackingStatus::PICKING, 'code' => '2', 'at' => '2026-08-17 15:00:00'];
        $this->stateResource->expects($this->never())->method('save');

        $state = $this->stateMock();
        $this->assertTrue(
            $this->processor($this->track, $state)->process(
                $this->update(NormalizedTrackingStatus::OUT_FOR_DELIVERY, '10', strtotime('2026-08-17 14:00:00'))
            )
        );
    }

    public function testDeliveryFailedToInTransitReattemptAllowed(): void
    {
        $this->stored = ['status' => NormalizedTrackingStatus::DELIVERY_FAILED, 'code' => '6', 'at' => '2026-08-17 12:00:00'];
        $this->stateResource->expects($this->once())->method('save');

        $state = $this->stateMock();
        $this->assertTrue(
            $this->processor($this->track, $state)->process(
                $this->update(NormalizedTrackingStatus::IN_TRANSIT, '4', strtotime('2026-08-17 13:00:00'))
            )
        );
        $this->assertContains('secomm_shipping_tracking_updated', $this->dispatched);
    }

    public function testSameNormalizedDifferentRawCodeStillPersists(): void
    {
        $this->stored = ['status' => NormalizedTrackingStatus::PICKING, 'code' => '2', 'at' => '2026-08-17 09:00:00'];
        $this->stateResource->expects($this->once())->method('save');

        // GHTK 7/8/9 also map to PICKING — raw code refresh without dup-comment spam.
        $state = $this->stateMock();
        $this->assertTrue(
            $this->processor($this->track, $state)->process(
                $this->update(NormalizedTrackingStatus::PICKING, '9', strtotime('2026-08-17 10:00:00'))
            )
        );
    }

    public function testOrderStateIsNeverTouched(): void
    {
        // Only the shipment repository (comments) is used; no order repository,
        // no order status writes exist in the processor — pinned by the
        // Shipment-only interaction above and this non-delivered transition:
        $this->shipmentRepository->expects($this->never())->method('get');

        $state = $this->stateMock();
        $this->assertTrue(
            $this->processor($this->track, $state)->process($this->update(NormalizedTrackingStatus::PICKED_UP, '3'))
        );
        $this->assertContains('secomm_shipping_tracking_updated', $this->dispatched);
        $this->assertNotContains('secomm_shipment_carrier_delivered', $this->dispatched);
    }
}
