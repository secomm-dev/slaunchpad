<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Shipment;
use Secomm\Ghn\Model\Admin\GhnCreateOutcomeNotifier;
use Secomm\Ghn\Model\Carrier\Ghn;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Shipment\GhnCreateOutcome;
use Secomm\Ghn\Model\Shipment\GhnShipmentCreationService;
use Secomm\Ghn\Model\Shipment\PostedPhysicalPackages;
use Secomm\Ghn\Model\Shipment\ShipmentTrackAttacher;
use Secomm\ShippingCore\Api\Shipment\FulfillmentMode;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;

/**
 * TASK-9Q5ZAK (GHN-D) — the CREATE trigger: after a shipment COMMIT, create the GHN order for
 * shipments on the secomm_ghn carrier and attach the provider order code as a native Track.
 *
 * Design points (TL brief §24/§28/§34/§35):
 * - `save_commit_after` (not save_after): the GHN HTTP call must never run inside the sales
 *   transaction (row locks + orphan provider order on commit failure);
 * - the observer NEVER throws — a failed create leaves a FAILED/UNKNOWN persistence row and a
 *   log entry; the Magento shipment stays intact and is reconciled via the retry CLI with the
 *   same client_order_code (sandbox-proven idempotency);
 * - TASK-W5BW4F layer 2: non-SUCCESS outcomes are additionally LOUD — an admin error message
 *   (the attempt runs synchronously in the same request) plus a shipment comment, via
 *   {@see GhnCreateOutcomeNotifier}; deterministic parcel failures are blocked before any of
 *   this can happen by {@see GhnShipmentSaveValidationObserver};
 * - the service's own SUBMITTED-row guard runs first, so the event's guaranteed re-fire (the
 *   track save itself re-dispatches this event) is a cheap no-op.
 *
 * TASK-S52DGA (DEC-TASKS52DGA-001) — offline fulfillment gating: an OFFLINE shipment must never
 * reach the provider. Two arms, both required:
 * - REQUEST intent catches the fresh offline save (this event's first fire);
 * - PERSISTED metadata catches every LATER save of an offline shipment (comments, tracks) — an
 *   offline shipment has NO SUBMITTED anchor row, so the service idempotency guard alone would
 *   let a routine re-save create a real GHN order.
 */
class GhnShipmentCreateObserver implements ObserverInterface
{
    /** In-flight guard (per process): the persister's own save re-fires this event synchronously. */
    private static array $inFlight = [];

    public function __construct(
        private readonly GhnShipmentCreationService $creationService,
        private readonly ShipmentTrackAttacher $trackAttacher,
        private readonly GhnCreateOutcomeNotifier $notifier,
        private readonly HttpRequest $request,
        private readonly GhnLogger $logger,
        private readonly FulfillmentModeResolver $fulfillmentModeResolver
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $shipment = $observer->getData('shipment');
            if (!$shipment instanceof Shipment) {
                return;
            }

            $this->process($shipment);
        } catch (\Throwable $exception) {
            // Estimation/fulfilment must never crash on the provider: containment + evidence.
            $this->logger->error('GHN shipment create observer failed (graceful).', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function process(Shipment $shipment): void
    {
        if ((int) $shipment->getEntityId() <= 0) {
            return;
        }

        // Carrier gate on the RAW shipping method string: Order::getShippingMethod(true) splits
        // on the FIRST underscore, which would turn secomm_ghn_secomm_ghn into carrier "secomm".
        $shippingMethod = (string) $shipment->getOrder()->getShippingMethod();
        if (!str_starts_with($shippingMethod, Ghn::CARRIER_CODE . '_')) {
            return;
        }

        // TASK-S52DGA — offline fulfillment never reaches the provider (request intent on the
        // fresh offline save; persisted metadata on every later re-save).
        if ($this->fulfillmentModeResolver->isOfflineIntent()
            || $this->fulfillmentModeResolver->forShipment($shipment) === FulfillmentMode::OFFLINE
        ) {
            return;
        }

        // In-flight guard: persisting the physical snapshot re-saves the shipment, which
        // synchronously re-fires THIS event while the first invocation is still running —
        // without this guard the create would execute inside the nested save.
        $shipmentId = (int) $shipment->getEntityId();
        if (isset(self::$inFlight[$shipmentId])) {
            return;
        }
        self::$inFlight[$shipmentId] = true;

        try {
            $this->createAndAttach($shipment);
        } finally {
            unset(self::$inFlight[$shipmentId]);
        }
    }

    private function createAndAttach(Shipment $shipment): void
    {
        $outcome = $this->creationService->createForShipment(
            $shipment,
            PostedPhysicalPackages::fromRequest($this->request)
        );

        if ($outcome->getStatus() === GhnCreateOutcome::STATUS_COD_REJECTED) {
            // TASK-DFGFZ9 phase 2: the Secomm_Cod decision refused the collection — surface it
            // to the admin explicitly (the shipment itself is intact; nothing was submitted).
            $this->logger->error('GHN shipment create rejected by COD policy.', [
                'reason' => $outcome->getReason(),
                'message' => $outcome->getRejectionMessage(),
            ]);
            $shipment->addComment(
                __('GHN COD collection rejected (%1): %2', $outcome->getReason(), $outcome->getRejectionMessage())
            );
            $shipment->save();
            $this->notifier->notifyFailure($outcome, (int) $shipment->getEntityId());

            return;
        }

        if ($outcome->isSuccessful()) {
            $attached = $this->trackAttacher->attach($shipment, (string) $outcome->getOrderCode(), 'GHN');
            $this->logger->call('GHN shipment create finished', [
                'client_order_code' => $outcome->getClientOrderCode(),
                'order_code' => $outcome->getOrderCode(),
                'track_attached' => $attached,
            ]);

            return;
        }

        $context = ['status' => $outcome->getStatus(), 'reason' => $outcome->getReason()];
        if ($outcome->getStatus() === GhnCreateOutcome::STATUS_TECHNICAL_FAILURE) {
            $this->logger->warning('GHN shipment create technical failure; reconcile via retry.', $context);
        } else {
            $this->logger->call('GHN shipment create unavailable.', $context);
        }

        // TASK-W5BW4F layer 2 — the failure is never silent: admin message on the save redirect
        // (same request) + a durable shipment comment (the COD_REJECTED branch above already
        // comments; this covers UNAVAILABLE / TECHNICAL_FAILURE / UNKNOWN-shaped rows).
        $shipment->addComment($this->notifier->failureComment($outcome));
        $shipment->save();
        $this->notifier->notifyFailure($outcome, (int) $shipment->getEntityId());
    }
}
