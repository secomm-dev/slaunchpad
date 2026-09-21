<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Shipment\GhnCreateOutcome;
use Secomm\Ghn\Model\Shipment\GhnShipmentCreationService;
use Secomm\Ghn\Model\Shipment\ShipmentTrackAttacher;
use Secomm\Ghn\Observer\GhnShipmentCreateObserver;

/**
 * TASK-9Q5ZAK (GHN-D) — observer containment: non-GHN shipments are skipped, the creation
 * outcome drives the track attach, and NO failure path ever escapes the observer (a provider
 * outage must never break the Magento shipment save).
 */
class GhnShipmentCreateObserverTest extends TestCase
{
    private GhnShipmentCreationService&MockObject $creationService;

    private ShipmentTrackAttacher&MockObject $trackAttacher;

    private LoggerInterface&MockObject $psrLogger;

    private HttpRequest&MockObject $request;

    private GhnShipmentCreateObserver $observer;

    protected function setUp(): void
    {
        $this->creationService = $this->createMock(GhnShipmentCreationService::class);
        $this->trackAttacher = $this->createMock(ShipmentTrackAttacher::class);
        $this->psrLogger = $this->createMock(LoggerInterface::class);
        $this->request = $this->createMock(HttpRequest::class);
        $this->request->method('isPost')->willReturn(true);
        $this->observer = new GhnShipmentCreateObserver(
            $this->creationService,
            $this->trackAttacher,
            $this->request,
            new GhnLogger($this->psrLogger)
        );
    }

    public function testPostedPackagesArePassedToTheService(): void
    {
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 42);
        // The observer passes whatever the confirmed POST carried (null here — the mock has no
        // POST payload); the SERVICE owns reading/normalising it through the request.
        $this->creationService->expects($this->once())->method('createForShipment')->with(
            $this->isInstanceOf(Shipment::class),
            null
        )->willReturn(GhnCreateOutcome::unavailable('INVALID_PARCEL', 'GHNS42'));

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testGhnShipmentSuccessAttachesTrack(): void
    {
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 42);
        $this->creationService->method('createForShipment')->willReturn(
            GhnCreateOutcome::success('GHNS42', 'GHNORD9', 68200.0, null)
        );
        $this->trackAttacher->expects($this->once())->method('attach')->with(
            $this->isInstanceOf(Shipment::class),
            'GHNORD9',
            'GHN'
        )->willReturn(true);

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testNonGhnCarrierIsSkippedWithoutAnyCall(): void
    {
        $shipment = $this->shipment('flatrate_flatrate', 42);
        $this->creationService->expects($this->never())->method('createForShipment');

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testUnsavedShipmentIsSkipped(): void
    {
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 0);
        $this->creationService->expects($this->never())->method('createForShipment');

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testUnavailableOutcomeDoesNotAttachTrackAndDoesNotThrow(): void
    {
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 42);
        $this->creationService->method('createForShipment')->willReturn(
            GhnCreateOutcome::unavailable('PROVIDER_MAPPING_MISSING', 'GHNS42')
        );
        $this->trackAttacher->expects($this->never())->method('attach');

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testServiceThrowableIsContained(): void
    {
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 42);
        $this->creationService->method('createForShipment')->willThrowException(
            new \RuntimeException('unexpected')
        );
        $this->psrLogger->expects($this->once())->method('error')->with(
            'GHN shipment create observer failed (graceful).',
            $this->callback(fn (array $context): bool => $context['exception'] === 'unexpected')
        );
        $this->trackAttacher->expects($this->never())->method('attach');

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testMissingShipmentObjectIsIgnored(): void
    {
        $this->creationService->expects($this->never())->method('createForShipment');

        $this->observer->execute(new Observer([]));
    }

    // ---------- helpers ----------

    private function shipment(string $shippingMethod, int $entityId): Shipment&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getShippingMethod')->willReturn($shippingMethod);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getEntityId')->willReturn($entityId);
        $shipment->method('getOrder')->willReturn($order);

        return $shipment;
    }
}
