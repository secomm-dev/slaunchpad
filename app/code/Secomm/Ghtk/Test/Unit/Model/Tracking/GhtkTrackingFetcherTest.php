<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Tracking;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\Tracking\GhtkStatusMapper;
use Secomm\Ghtk\Model\Tracking\GhtkTrackingFetcher;
use Secomm\ShippingCore\Api\Tracking\CarrierStatusMapperInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * TASK-7AJ3K8 — the GHTK fetcher: tracking number → GHTK status API → normalized
 * TrackingUpdate through the carrier-owned status mapper (UNKNOWN stays UNKNOWN).
 */
class GhtkTrackingFetcherTest extends TestCase
{
    private GhtkApiClient&MockObject $apiClient;
    private GhtkTrackingFetcher $fetcher;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(GhtkApiClient::class);
        $this->fetcher = new GhtkTrackingFetcher($this->apiClient, new GhtkStatusMapper());
    }

    public function testMapsNumericStatusThroughTheCarrierMapper(): void
    {
        $this->apiClient->method('getOrderStatus')->with('S1')
            ->willReturn(['order' => ['status' => 5, 'message' => 'Đã giao']]);

        $update = $this->fetcher->fetch('S1');

        $this->assertNotNull($update);
        $this->assertSame('S1', $update->getTrackingNumber());
        $this->assertSame(NormalizedTrackingStatus::DELIVERED, $update->getNormalizedStatus());
        $this->assertSame('5', $update->getCarrierStatusCode());
        $this->assertSame('Đã giao', $update->getCarrierStatusMessage());
        $this->assertSame('api', $update->getSource());
        $this->assertSame('ghtk', $update->getCarrierCode());
    }

    public function testStringStatusIdFallbackIsUsed(): void
    {
        $this->apiClient->method('getOrderStatus')
            ->willReturn(['order' => ['status_id' => 123]]);

        $update = $this->fetcher->fetch('S2');

        $this->assertNotNull($update);
        $this->assertSame('123', $update->getCarrierStatusCode());
    }

    public function testUnknownCarrierStatusMapsToUnknown(): void
    {
        $this->apiClient->method('getOrderStatus')
            ->willReturn(['order' => ['status' => 'mystery-status']]);

        $update = $this->fetcher->fetch('S3');

        $this->assertNotNull($update);
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $update->getNormalizedStatus());
    }

    public function testMissingStatusYieldsNullNotAnUpdate(): void
    {
        $this->apiClient->method('getOrderStatus')
            ->willReturn(['order' => ['message' => 'no status field']]);

        $this->assertNull($this->fetcher->fetch('S4'));
    }

    public function testWhitespaceOnlyStatusYieldsNull(): void
    {
        $this->apiClient->method('getOrderStatus')
            ->willReturn(['order' => ['status' => '   ']]);

        $this->assertNull($this->fetcher->fetch('S5'));
    }

    public function testCustomMapperIsConsulted(): void
    {
        $mapper = $this->createMock(CarrierStatusMapperInterface::class);
        $mapper->expects($this->once())->method('map')->with(5)->willReturn(NormalizedTrackingStatus::IN_TRANSIT);
        $fetcher = new GhtkTrackingFetcher($this->apiClient, $mapper);
        $this->apiClient->method('getOrderStatus')->willReturn(['order' => ['status' => 5]]);

        $update = $fetcher->fetch('S6');

        $this->assertInstanceOf(TrackingUpdate::class, $update);
        $this->assertSame(NormalizedTrackingStatus::IN_TRANSIT, $update?->getNormalizedStatus());
    }
}
