<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Physical;

use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Secomm\ShippingCore\Api\Physical\ShipmentPhysicalDataInterface;

/**
 * DEC-TASK9Q5ZAK-001 — persistence of the physical facts on the MAGENTO-NATIVE
 * `sales_shipment.packages` column (text, JSON-serialized by the sales resource via
 * `_serializableFields`), under a self-identifying marker key `secomm_physical`:
 *
 *   {'secomm_physical': [[weightG, lengthCm, widthCm, heightCm], ...]}
 *
 * Collision-safe by construction: the label popup writes its own shape under numeric keys and
 * only runs when a carrier's isShippingLabelsAvailable() is true; carrier adapters that disable
 * labels never race with it. Snapshot semantics — once persisted, retries read THIS data back
 * so a re-submission never recalculates from modified product data.
 *
 * Physical facts are ShippingCore state: carrier persistence tables (e.g. secomm_ghn_shipment)
 * keep provider state only and must not mirror the physical payload.
 */
class ShipmentPhysicalPersister
{
    /** Marker key inside the packages column — identifies Secomm physical-facts rows. */
    public const PACKAGES_KEY = 'secomm_physical';

    public function __construct(private readonly ShipmentRepositoryInterface $shipmentRepository)
    {
    }

    /**
     * Snapshots the physical facts onto the shipment and persists it (UPDATE — the shipment is
     * already saved when this runs, post-commit). MERGE semantics: non-`secomm_physical` entries
     * (e.g. a future native label-popup shape) are PRESERVED — only the marker is updated, so
     * enabling the Magento label flow in a later slice never silently destroys the snapshot.
     */
    public function persist(ShipmentInterface $shipment, ShipmentPhysicalDataInterface $physicalData): void
    {
        $marker = [];
        foreach ($physicalData->getPackages() as $package) {
            $marker[] = [
                $package->getWeightG(),
                $package->getLengthCm(),
                $package->getWidthCm(),
                $package->getHeightCm(),
            ];
        }

        $existing = $shipment->getPackages();
        $merged = is_array($existing) ? $existing : [];
        foreach ($merged as $key => $value) {
            if (is_string($key) && str_starts_with($key, self::PACKAGES_KEY)) {
                unset($merged[$key]);
            }
        }
        $merged[self::PACKAGES_KEY] = $marker;

        $shipment->setPackages($merged);
        $this->shipmentRepository->save($shipment);
    }

    /**
     * @return ShipmentPhysicalDataInterface|null null = no Secomm physical snapshot on this shipment
     */
    public function read(ShipmentInterface $shipment): ?ShipmentPhysicalDataInterface
    {
        $packages = $shipment->getPackages();
        if (!is_array($packages) || !isset($packages[self::PACKAGES_KEY])) {
            return null;
        }

        $physicalPackages = [];
        foreach ($packages[self::PACKAGES_KEY] as $tuple) {
            if (!is_array($tuple) || count($tuple) !== 4) {
                return null; // malformed marker data — treat as absent, fail closed upstream
            }
            $physicalPackages[] = new PhysicalPackage(
                (int) $tuple[0],
                (int) $tuple[1],
                (int) $tuple[2],
                (int) $tuple[3]
            );
        }

        if ($physicalPackages === []) {
            return null;
        }

        return ShipmentPhysicalData::fromPackages($physicalPackages);
    }
}
