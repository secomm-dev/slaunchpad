<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Sales\Model\Order\Shipment\TrackFactory;

/**
 * TASK-9Q5ZAK (GHN-D) — attaches the provider order code as a native shipment Track
 * (mirror of the core AddTrack controller's persistence path: parent_id/order_id are set by
 * Shipment::addTrack, saved by the shipment save; no email side effects).
 *
 * Tracks have NO unique index on (carrier_code, track_number), so retries dedupe by scanning
 * the existing collection before adding — an idempotent retry never duplicates rows.
 */
class ShipmentTrackAttacher
{
    public function __construct(
        private readonly TrackFactory $trackFactory
    ) {
    }

    public function attach(Shipment $shipment, string $trackNumber, string $title): bool
    {
        foreach ($shipment->getTracksCollection() as $existingTrack) {
            /** @var ShipmentTrackInterface|Track $existingTrack */
            if ((string) $existingTrack->getTrackNumber() === $trackNumber) {
                return false;
            }
        }

        $track = $this->trackFactory->create()
            ->setNumber($trackNumber)
            ->setCarrierCode('secomm_ghn')
            ->setTitle($title);
        $shipment->addTrack($track);
        $shipment->save();

        return true;
    }
}
