<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;
use Secomm\ShippingCore\Model\Shipment\OfflineRecordingState;
use Secomm\ShippingCore\Observer\ShipmentOfflineIntentValidationObserver;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — the generic offline decision seam: intent without a
 * capable carrier (or on a re-save) is rejected fail-closed with ZERO writes; accepted intent
 * adds the durable history comment that persists with the main transaction. Carrier rules
 * (package validation) stay OUT of this observer by design.
 */
class ShipmentOfflineIntentValidationObserverTest extends TestCase
{
    private FulfillmentModeResolver&MockObject $resolver;

    private HttpRequest&MockObject $request;

    private ShipmentOfflineIntentValidationObserver $observer;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(FulfillmentModeResolver::class);
        $this->request = $this->createMock(HttpRequest::class);
        $this->observer = new ShipmentOfflineIntentValidationObserver($this->resolver, $this->request);
    }

    public function testNoIntentIsNoOpEvenForNonCapableCarriers(): void
    {
        $this->resolver->method('isOfflineIntent')->willReturn(false);
        $this->resolver->expects($this->never())->method('resolveCarrierCode');
        $shipment = $this->shipment('flatrate_flatrate', 0);
        $shipment->expects($this->never())->method('addComment');

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testOfflineIntentOnCapableCarrierAddsTheHistoryComment(): void
    {
        $this->givenOfflineIntentWithCarrier('secomm_ghn');
        $this->givenPosted(['offline_reason_code' => 'INVALID_PARCEL']);
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 0);
        $shipment->expects($this->once())->method('addComment')->with($this->callback(
            fn ($comment): bool => str_contains((string) $comment, 'Offline shipment created')
                && str_contains((string) $comment, 'INVALID_PARCEL')
        ));

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testOfflineIntentWithoutReasonAddsThePlainComment(): void
    {
        $this->givenOfflineIntentWithCarrier('secomm_ghn');
        $this->givenPosted([]);
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 0);
        $shipment->expects($this->once())->method('addComment')->with($this->callback(
            fn ($comment): bool => str_contains((string) $comment, 'Offline shipment created')
                && !str_contains((string) $comment, 'reason')
        ));

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testOfflineIntentOnNonCapableCarrierIsRejected(): void
    {
        $this->resolver->method('isOfflineIntent')->willReturn(true);
        $this->resolver->method('resolveCarrierCode')->willReturn(null);
        $shipment = $this->shipment('flatrate_flatrate', 0);
        $shipment->expects($this->never())->method('addComment');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not available for this shipping method');

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testOfflineIntentOnAResaveIsRejected(): void
    {
        // A crafted intent on an existing (possibly provider-live) shipment must never flip it
        // to OFFLINE — and must never duplicate the comment.
        $this->givenOfflineIntentWithCarrier('secomm_ghn');
        $shipment = $this->shipment('secomm_ghn_secomm_ghn', 42);
        $shipment->expects($this->never())->method('addComment');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('only be set while creating the shipment');

        $this->observer->execute(new Observer(['shipment' => $shipment]));
    }

    public function testIntentOnAResaveDuringRecordingIsAllowed(): void
    {
        // The commit_after recorder's own persistence re-fires this observer with intent + an
        // already-saved shipment — it must stand down so the metadata persist can land.
        $this->givenOfflineIntentWithCarrier('secomm_ghn');
        OfflineRecordingState::begin(42);
        try {
            $shipment = $this->shipment('secomm_ghn_secomm_ghn', 42);
            $shipment->expects($this->never())->method('addComment');

            $this->observer->execute(new Observer(['shipment' => $shipment]));
            $this->addToAssertionCount(1); // no exception = pass
        } finally {
            OfflineRecordingState::end(42);
        }
    }

    public function testMissingShipmentObjectIsIgnored(): void
    {
        $this->resolver->expects($this->never())->method('isOfflineIntent');

        $this->observer->execute(new Observer([]));
    }

    // ---------- helpers ----------

    private function givenOfflineIntentWithCarrier(string $carrierCode): void
    {
        $this->resolver->method('isOfflineIntent')->willReturn(true);
        $this->resolver->method('resolveCarrierCode')->willReturn($carrierCode);
    }

    private function givenPosted(array $value): void
    {
        $this->request->method('getParam')->with('shipment')->willReturn($value);
    }

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
