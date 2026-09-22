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
use Secomm\Ghn\Model\Carrier\Ghn;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Shipment\GhnCreateOutcome;
use Secomm\Ghn\Model\Shipment\GhnShipmentCreationService;
use Secomm\Ghn\Model\Shipment\ShipmentTrackAttacher;

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
 * - the service's own SUBMITTED-row guard runs first, so the event's guaranteed re-fire (the
 *   track save itself re-dispatches this event) is a cheap no-op.
 */
class GhnShipmentCreateObserver implements ObserverInterface
{
    /** In-flight guard (per process): the persister's own save re-fires this event synchronously. */
    private static array $inFlight = [];

    public function __construct(
        private readonly GhnShipmentCreationService $creationService,
        private readonly ShipmentTrackAttacher $trackAttacher,
        private readonly HttpRequest $request,
        private readonly GhnLogger $logger
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
        $outcome = $this->creationService->createForShipment($shipment, $this->postedPhysicalPackages());

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

            return;
        }

        $this->logger->call('GHN shipment create unavailable.', $context);
    }

    /**
     * The confirmed package rows from the admin package-information section — the ONLY
     * authoritative physical source on a fresh save (retry path reads the persisted snapshot).
     *
     * @return array|null null when this request carries no package information
     */
    private function postedPhysicalPackages(): ?array
    {
        if (!$this->request->isPost()) {
            return null;
        }

        $packages = $this->request->getParam('shipment')['physical_packages'] ?? null;

        return is_array($packages) && $packages !== [] ? $packages : null;
    }
}
