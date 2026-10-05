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
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Magento\Framework\Phrase;
use Secomm\Cod\Model\CodCollectionDecision;
use Secomm\Ghn\Model\Admin\GhnCreateOutcomeNotifier;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Shipment\GhnCreateOutcome;
use Secomm\Ghn\Model\Shipment\GhnCreateReasonLabel;
use Secomm\Ghn\Model\Shipment\GhnShipmentCreationService;
use Secomm\Ghn\Model\Shipment\ShipmentTrackAttacher;
use Secomm\Ghn\Observer\GhnShipmentCreateObserver;
use Secomm\ShippingCore\Api\Shipment\FulfillmentMode;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;

/**
 * TASK-9Q5ZAK (GHN-D) — observer containment: non-GHN shipments are skipped, the creation
 * outcome drives the track attach, and NO failure path ever escapes the observer (a provider
 * outage must never break the Magento shipment save). TASK-W5BW4F layer 2: every non-SUCCESS
 * outcome is additionally loud — admin error message + durable shipment comment.
 * TASK-S52DGA: offline fulfillment never reaches the provider — neither on the fresh offline
 * save (request intent) nor on any later re-save (persisted metadata; an offline shipment has
 * no SUBMITTED anchor to idempotency-guard).
 */
class GhnShipmentCreateObserverTest extends TestCase
{
    private GhnShipmentCreationService&MockObject $creationService;

    private ShipmentTrackAttacher&MockObject $trackAttacher;

    private ManagerInterface&MockObject $messageManager;

    private LoggerInterface&MockObject $psrLogger;

    private HttpRequest&MockObject $request;

    private FulfillmentModeResolver&MockObject $fulfillmentModeResolver;

    private GhnShipmentCreateObserver $observer;

    protected function setUp(): void
    {
        $this->creationService = $this->createMock(GhnShipmentCreationService::class);
        $this->trackAttacher = $this->createMock(ShipmentTrackAttacher::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->psrLogger = $this->createMock(LoggerInterface::class);
        $this->request = $this->createMock(HttpRequest::class);
        $this->request->method('isPost')->willReturn(true);
        $this->fulfillmentModeResolver = $this->createMock(FulfillmentModeResolver::class);
        $this->observer = new GhnShipmentCreateObserver(
            $this->creationService,
            $this->trackAttacher,
            new GhnCreateOutcomeNotifier($this->messageManager, new GhnCreateReasonLabel()),
            $this->request,
            new GhnLogger($this->psrLogger),
            $this->fulfillmentModeResolver
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

    /**
     * TASK-W5BW4F layer 2 — a non-SUCCESS outcome must never be silent: admin error message
     * with the retry hint + a durable shipment comment.
     */
    public function testUnavailableOutcomeIsLoudMessageAndComment(): void
    {
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 42);
        $this->creationService->method('createForShipment')->willReturn(
            GhnCreateOutcome::unavailable('INVALID_PARCEL', 'GHNS42')
        );
        $this->messageManager->expects($this->once())->method('addErrorMessage')->with($this->callback(
            fn (Phrase $message): bool => str_contains((string) $message, 'secomm:ghn:shipment:retry 42')
        ));
        $shipment->expects($this->once())->method('addComment')->with($this->callback(
            fn ($message): bool => str_contains((string) $message, 'GHN shipment create failed (UNAVAILABLE)')
        ));
        $shipment->expects($this->once())->method('save');

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testSuccessOutcomeIsSilentOnFailureChannels(): void
    {
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 42);
        $this->creationService->method('createForShipment')->willReturn(
            GhnCreateOutcome::success('GHNS42', 'GHNORD9', 68200.0, null)
        );
        $this->messageManager->expects($this->never())->method('addErrorMessage');
        $shipment->expects($this->never())->method('addComment');

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testCodRejectedOutcomeLogsCommentsAndSavesWithoutTrackAttach(): void
    {
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 42);
        $this->creationService->method('createForShipment')->willReturn(
            GhnCreateOutcome::codRejected(
                CodCollectionDecision::REASON_COD_ALREADY_COLLECTED,
                'Order #100000007 already has a COD collection of 500000 VND via "shipment:41"',
                'GHNS42'
            )
        );
        $this->trackAttacher->expects($this->never())->method('attach');
        $shipment->expects($this->once())->method('addComment')->with($this->callback(
            fn ($message): bool => str_contains((string) $message, 'COD collection rejected')
        ));
        $shipment->expects($this->once())->method('save');

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

    // ---------- TASK-S52DGA (offline fulfillment gating) ----------

    public function testOfflineRequestIntentSkipsCreate(): void
    {
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 42);
        $this->fulfillmentModeResolver->method('isOfflineIntent')->willReturn(true);
        $this->creationService->expects($this->never())->method('createForShipment');
        $this->trackAttacher->expects($this->never())->method('attach');

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testPersistedOfflineMetadataSkipsCreateOnResave(): void
    {
        // A later re-save (comment/track) carries no request intent — the persisted OFFLINE
        // metadata is the arm that keeps a routine re-save from creating a real GHN order.
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 42);
        $this->fulfillmentModeResolver->method('isOfflineIntent')->willReturn(false);
        $this->fulfillmentModeResolver->method('forShipment')->with($shipment)->willReturn(FulfillmentMode::OFFLINE);
        $this->creationService->expects($this->never())->method('createForShipment');
        $this->trackAttacher->expects($this->never())->method('attach');

        $this->observer->execute(new Observer(['shipment' => $shipment]));
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
