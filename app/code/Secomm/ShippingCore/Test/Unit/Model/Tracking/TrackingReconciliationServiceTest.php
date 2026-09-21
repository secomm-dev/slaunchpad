<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Tracking;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingFetcherInterface;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingProcessorInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;
use Secomm\ShippingCore\Model\CarrierTrackingState;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\Collection as StateCollection;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\CollectionFactory as StateCollectionFactory;
use Secomm\ShippingCore\Model\Tracking\TrackingReconciliationService;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * TASK-7AJ3K8 — the SHARED reconciliation orchestration (moved from
 * Secomm_Ghtk\TrackingRefreshService): terminal exclusion + stale filter + batch + per-item
 * continue, feeding the SAME CarrierTrackingProcessorInterface as the webhook.
 */
class TrackingReconciliationServiceTest extends TestCase
{
    private const CARRIER = 'ghtk';

    private CarrierTrackingFetcherInterface&MockObject $fetcher;
    private CarrierTrackingProcessorInterface&MockObject $processor;
    /** @var array<int, CarrierTrackingState&MockObject> simulated state rows */
    private array $rows = [];
    /** @var array<int, array<int, string>> captured collection filters */
    private array $filters = [];

    private TrackingReconciliationService $service;

    protected function setUp(): void
    {
        $this->fetcher = $this->createMock(CarrierTrackingFetcherInterface::class);
        $this->processor = $this->createMock(CarrierTrackingProcessorInterface::class);
        $this->rows = [];
        $this->filters = [];

        $collection = $this->createMock(StateCollection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(function (string $field, $condition) use ($collection) {
            $this->filters[$field] = $condition;

            return $collection;
        });
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('setCurPage')->willReturnSelf();
        $collection->method('getItems')->willReturnCallback(fn (): array => array_values($this->rows));

        $collectionFactory = $this->createMock(StateCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $this->service = new TrackingReconciliationService(
            $collectionFactory,
            $this->fetcher,
            $this->processor,
            self::CARRIER,
            $this->createMock(LoggerInterface::class),
            50
        );
    }

    private function stateRow(string $number, ?string $syncedAt): CarrierTrackingState&MockObject
    {
        $row = $this->getMockBuilder(CarrierTrackingState::class)
            ->addMethods(['getTrackingNumber', 'getLastSyncedAt'])
            ->disableOriginalConstructor()
            ->getMock();
        $row->method('getTrackingNumber')->willReturn($number);
        $row->method('getLastSyncedAt')->willReturn($syncedAt);

        return $row;
    }

    public function testQueriesOnlyTheCarrierAndExcludesTerminalStates(): void
    {
        $this->rows = [$this->stateRow('S1', '2026-01-01 00:00:00')];
        $this->fetcher->method('fetch')->willReturn(null);

        $this->service->refresh(6 * 3600);

        $this->assertSame(self::CARRIER, $this->filters['carrier_code']);
        $this->assertSame(['nin' => NormalizedTrackingStatus::terminal()], $this->filters['normalized_status']);
    }

    public function testStaleStateSyncedThroughTheSharedProcessor(): void
    {
        $this->rows = [$this->stateRow('S1', '2026-01-01 00:00:00')];
        $this->fetcher->method('fetch')->with('S1')->willReturn($this->update('S1'));
        $this->processor->expects($this->once())->method('process')
            ->with($this->callback(fn (TrackingUpdate $u): bool => $u->getSource() === 'api'))
            ->willReturn(true);

        $this->assertSame(1, $this->service->refresh(6 * 3600));
    }

    public function testFreshStateIsNotRechecked(): void
    {
        $this->rows = [$this->stateRow('S1', date('Y-m-d H:i:s'))];
        $this->fetcher->expects($this->never())->method('fetch');

        $this->assertSame(0, $this->service->refresh(6 * 3600));
    }

    public function testNeverSyncedStateIsChecked(): void
    {
        $this->rows = [$this->stateRow('S2', null)];
        $this->fetcher->method('fetch')->willReturn($this->update('S2'));
        $this->processor->method('process')->willReturn(true);

        $this->assertSame(1, $this->service->refresh(6 * 3600));
    }

    public function testFetcherNullIsNotCountedAsSynced(): void
    {
        $this->rows = [$this->stateRow('S1', '2026-01-01 00:00:00')];
        $this->fetcher->method('fetch')->willReturn(null);
        $this->processor->expects($this->never())->method('process');

        $this->assertSame(0, $this->service->refresh(6 * 3600));
    }

    public function testPerItemFailureContinuesWithRemainingStates(): void
    {
        $this->rows = [
            $this->stateRow('S1', '2026-01-01 00:00:00'),
            $this->stateRow('S2', '2026-01-01 00:00:00'),
        ];
        $this->fetcher->method('fetch')->willReturnCallback(function (string $number) {
            if ($number === 'S1') {
                throw new \RuntimeException('carrier API down');
            }

            return $this->update($number);
        });
        $this->processor->method('process')->willReturn(true);

        $this->assertSame(1, $this->service->refresh(6 * 3600));
    }

    public function testProcessorRejectDoesNotCountAsSynced(): void
    {
        $this->rows = [$this->stateRow('S1', '2026-01-01 00:00:00')];
        $this->fetcher->method('fetch')->willReturn($this->update('S1'));
        $this->processor->method('process')->willReturn(false);

        $this->assertSame(0, $this->service->refresh(6 * 3600));
    }

    private function update(string $number): TrackingUpdate
    {
        return new TrackingUpdate(
            carrierCode: self::CARRIER,
            trackingNumber: $number,
            normalizedStatus: NormalizedTrackingStatus::DELIVERED,
            carrierStatusCode: '5',
            carrierStatusMessage: null,
            occurredAt: null,
            source: 'api',
            raw: []
        );
    }
}
