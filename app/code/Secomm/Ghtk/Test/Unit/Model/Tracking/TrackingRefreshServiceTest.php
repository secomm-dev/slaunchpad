<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Tracking;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Tracking\TrackingRefreshService;
use Secomm\ShippingCore\Model\Tracking\TrackingReconciliationService;

/**
 * SL-017 / TASK-7AJ3K8 — the GHTK service is now a THIN carrier-policy shell: config gate +
 * staleness threshold only. The orchestration itself (state query, terminal exclusion, stale
 * filter, batch, per-item continue) is shared ShippingCore behavior covered by
 * TrackingReconciliationServiceTest.
 */
class TrackingRefreshServiceTest extends TestCase
{
    private TrackingReconciliationService&MockObject $reconciliation;
    private GhtkConfig&MockObject $config;

    protected function setUp(): void
    {
        $this->reconciliation = $this->createMock(TrackingReconciliationService::class);
        $this->config = $this->createMock(GhtkConfig::class);
        $this->config->method('getTrackingRefreshThresholdHours')->willReturn(6);
    }

    public function testDisabledConfigNoOpsWithoutTouchingTheSharedService(): void
    {
        $this->config->method('isTrackingRefreshEnabled')->willReturn(false);
        $this->reconciliation->expects($this->never())->method('refresh');

        $service = new TrackingRefreshService($this->reconciliation, $this->config);

        $this->assertSame(0, $service->refresh());
    }

    public function testEnabledConfigDelegatesThresholdHoursAsSeconds(): void
    {
        $this->config->method('isTrackingRefreshEnabled')->willReturn(true);
        $this->reconciliation->expects($this->once())
            ->method('refresh')
            ->with(6 * 3600)
            ->willReturn(3);

        $service = new TrackingRefreshService($this->reconciliation, $this->config);

        $this->assertSame(3, $service->refresh());
    }
}
