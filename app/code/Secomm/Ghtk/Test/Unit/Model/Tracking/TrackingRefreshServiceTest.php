<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Tracking;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\Ghtk\Model\Tracking\GhtkStatusMapper;
use Secomm\Ghtk\Model\Tracking\TrackingRefreshService;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingProcessorInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;
use Secomm\ShippingCore\Model\CarrierTrackingState;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\Collection as StateCollection;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\CollectionFactory as StateCollectionFactory;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * SL-017 §10: the API fallback feeds the SAME processor as the webhook.
 */
class TrackingRefreshServiceTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $apiClient;
    private \PHPUnit\Framework\MockObject\MockObject $processor;
    private \PHPUnit\Framework\MockObject\MockObject $config;
    /** @var array[] Simulated state rows. */
    private array $rows = [];

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(GhtkApiClient::class);
        $this->processor = $this->createMock(CarrierTrackingProcessorInterface::class);
        $this->config = $this->createMock(GhtkConfig::class);
        $this->config->method('isTrackingRefreshEnabled')->willReturn(true);
        $this->config->method('getTrackingRefreshThresholdHours')->willReturn(6);
        $this->rows = [];
    }

    private function stateRow(string $number, string $status, ?string $syncedAt): CarrierTrackingState
    {
        $row = $this->getMockBuilder(CarrierTrackingState::class)
            ->addMethods(['getTrackingNumber', 'getNormalizedStatus', 'getLastSyncedAt'])
            ->disableOriginalConstructor()
            ->getMock();
        $row->method('getTrackingNumber')->willReturn($number);
        $row->method('getNormalizedStatus')->willReturn($status);
        $row->method('getLastSyncedAt')->willReturn($syncedAt);

        return $row;
    }

    private function service(): TrackingRefreshService
    {
        $collection = $this->createMock(StateCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('setCurPage')->willReturnSelf();
        $rows = &$this->rows;
        $collection->method('getItems')->willReturnCallback(fn () => array_values($rows));
        $collection->method('getIterator')->willReturnCallback(fn () => new \ArrayIterator(array_values($rows)));

        $collectionFactory = $this->createMock(StateCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        return new TrackingRefreshService(
            $collectionFactory,
            $this->apiClient,
            new GhtkStatusMapper(),
            $this->processor,
            $this->config,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testStaleNonTerminalStateSyncedThroughSamePipeline(): void
    {
        $this->rows = [
            'stale' => $this->stateRow('S1', NormalizedTrackingStatus::IN_TRANSIT, '2026-01-01 00:00:00'),
        ];
        $this->apiClient->method('getOrderStatus')
            ->with('S1')
            ->willReturn(['success' => true, 'order' => ['status' => 5]]);

        $this->processor->expects($this->once())
            ->method('process')
            ->with($this->callback(function (TrackingUpdate $u) {
                return $u->getTrackingNumber() === 'S1'
                    && $u->getNormalizedStatus() === NormalizedTrackingStatus::DELIVERED
                    && $u->getSource() === 'api';
            }))
            ->willReturn(true);

        $this->assertSame(1, $this->service()->refresh());
    }

    public function testFreshStateNotRechecked(): void
    {
        $this->rows = [
            'fresh' => $this->stateRow('S1', NormalizedTrackingStatus::IN_TRANSIT, date('Y-m-d H:i:s')),
        ];
        $this->apiClient->expects($this->never())->method('getOrderStatus');

        $this->assertSame(0, $this->service()->refresh());
    }

    public function testNeverSyncedStateIsChecked(): void
    {
        $this->rows = [
            'never' => $this->stateRow('S2', NormalizedTrackingStatus::PICKING, null),
        ];
        $this->apiClient->method('getOrderStatus')->willReturn(['order' => ['status' => 3]]);
        $this->processor->method('process')->willReturn(true);

        $this->assertSame(1, $this->service()->refresh());
    }

    public function testApiFailureContinuesWithRemainingStates(): void
    {
        $this->rows = [
            'bad' => $this->stateRow('S1', NormalizedTrackingStatus::IN_TRANSIT, '2026-01-01 00:00:00'),
            'good' => $this->stateRow('S2', NormalizedTrackingStatus::PICKING, '2026-01-01 00:00:00'),
        ];

        $this->apiClient->method('getOrderStatus')->willReturnCallback(function (string $label) {
            if ($label === 'S1') {
                throw new GhtkApiException('GHTK status request failed (status 500).', true);
            }
            return ['order' => ['status' => 3]];
        });
        $this->processor->method('process')->willReturn(true);

        $this->assertSame(1, $this->service()->refresh());
    }

    public function testDisabledConfigNoOps(): void
    {
        $config = $this->createMock(GhtkConfig::class);
        $config->method('isTrackingRefreshEnabled')->willReturn(false);

        $collectionFactory = $this->createMock(StateCollectionFactory::class);
        $service = new TrackingRefreshService(
            $collectionFactory,
            $this->apiClient,
            new GhtkStatusMapper(),
            $this->processor,
            $config,
            $this->createMock(LoggerInterface::class)
        );

        $this->apiClient->expects($this->never())->method('getOrderStatus');
        $this->assertSame(0, $service->refresh());
    }
}
