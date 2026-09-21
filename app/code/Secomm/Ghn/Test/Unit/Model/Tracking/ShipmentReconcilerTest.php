<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Tracking;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Tracking\GhnTrackingFetcher;
use Secomm\Ghn\Model\Tracking\ShipmentReconciler;
use Secomm\ShippingCore\Api\Tracking\TrackingUpdateInterface;
use Secomm\ShippingCore\Model\Tracking\ShipmentTrackingProcessor;

/**
 * TASK-PWHG0V (GHN-E3-B) — the per-order reconcile helper: exactly the E1 chain
 * (fetcher → processor), failures swallowed (non-fatal by contract — the webhook is the
 * authoritative lifecycle source).
 */
class ShipmentReconcilerTest extends TestCase
{
    private GhnTrackingFetcher&MockObject $fetcher;

    private ShipmentTrackingProcessor&MockObject $processor;

    private ShipmentReconciler $reconciler;

    protected function setUp(): void
    {
        $this->fetcher = $this->createMock(GhnTrackingFetcher::class);
        $this->processor = $this->createMock(ShipmentTrackingProcessor::class);
        $this->reconciler = new ShipmentReconciler(
            $this->fetcher,
            $this->processor,
            new GhnLogger($this->createMock(\Psr\Log\LoggerInterface::class))
        );
    }

    public function testBlankOrderCodeIsRejected(): void
    {
        $this->fetcher->expects($this->never())->method('fetch');

        $this->assertFalse($this->reconciler->reconcileByOrderCode('  '));
    }

    public function testFetchNullYieldsFalse(): void
    {
        $this->fetcher->method('fetch')->willReturn(null);
        $this->processor->expects($this->never())->method('process');

        $this->assertFalse($this->reconciler->reconcileByOrderCode('L8TAKR'));
    }

    public function testAppliedUpdateReturnsProcessorResult(): void
    {
        $update = $this->createMock(TrackingUpdateInterface::class);
        $this->fetcher->method('fetch')->willReturn($update);
        $this->processor->expects($this->once())->method('process')->with($update)->willReturn(true);

        $this->assertTrue($this->reconciler->reconcileByOrderCode('L8TAKR'));
    }

    public function testFetchFailureIsSwallowedAndReturnsFalse(): void
    {
        $this->fetcher->method('fetch')->willThrowException(new \RuntimeException('5xx'));
        $this->processor->expects($this->never())->method('process');

        $this->assertFalse($this->reconciler->reconcileByOrderCode('L8TAKR'));
    }

    public function testProcessorExceptionIsNonFatal(): void
    {
        // Reconciliation is best-effort for \Exception — the webhook owns eventual truth.
        $update = $this->createMock(TrackingUpdateInterface::class);
        $this->fetcher->method('fetch')->willReturn($update);
        $this->processor->method('process')->willThrowException(new \RuntimeException('db down'));

        $this->assertFalse($this->reconciler->reconcileByOrderCode('L8TAKR'));
    }

    public function testProgrammingErrorIsNotSwallowed(): void
    {
        // PHP Errors/TypeErrors must remain visible — never converted to a soft "false".
        $this->fetcher->method('fetch')->willThrowException(new \Error('bug in fetcher'));

        $this->expectException(\Error::class);
        $this->reconciler->reconcileByOrderCode('L8TAKR');
    }
}
