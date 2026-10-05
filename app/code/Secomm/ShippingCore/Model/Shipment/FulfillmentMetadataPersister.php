<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Shipment;

use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Secomm\ShippingCore\Api\Shipment\FulfillmentMode;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — persistence of the generic fulfillment metadata on the
 * MAGENTO-NATIVE `sales_shipment.packages` column (text, JSON-serialized by the sales resource
 * via `_serializableFields`), under a self-identifying marker key `secomm_fulfillment`
 * (user decision 2026-09-30: marker instead of a dedicated table — zero schema migration, same
 * pattern as the `secomm_physical` physical-facts marker of DEC-TASK9Q5ZAK-001):
 *
 *   {'secomm_fulfillment': {
 *       'fulfillment_mode': 'OFFLINE',
 *       'intended_carrier_code': 'secomm_ghn',
 *       'offline_reason_code': 'INVALID_PARCEL',
 *       'offline_reason_message': '…',
 *       'offline_note': '…',
 *       'created_at': 'YYYY-MM-DD HH:ii:ss'
 *   }}
 *
 * Rows are written ONLY for OFFLINE — absence of the marker means ONLINE (per-shipment scope:
 * one order may hold Shipment #1 ONLINE and Shipment #2 OFFLINE). MERGE semantics preserve every
 * non-marker entry (the secomm_physical snapshot, native label-popup shapes). Idempotency is
 * read-marker-then-write — event re-fires cannot duplicate. NOT collision-safe on read: the
 * admin packaging surfaces render every `packages` entry unguarded, so the marker is stripped
 * at display time by Plugin\Shipping\PackagingBlockPlugin (BUG-74VGQX pattern).
 */
class FulfillmentMetadataPersister
{
    /** Marker key inside the packages column — identifies Secomm fulfillment metadata. */
    public const PACKAGES_KEY = 'secomm_fulfillment';

    public const MODE = 'fulfillment_mode';
    public const INTENDED_CARRIER = 'intended_carrier_code';
    public const REASON_CODE = 'offline_reason_code';
    public const REASON_MESSAGE = 'offline_reason_message';
    public const NOTE = 'offline_note';
    public const CREATED_AT = 'created_at';

    public function __construct(private readonly ShipmentRepositoryInterface $shipmentRepository)
    {
    }

    /**
     * Records the OFFLINE metadata onto the shipment and persists it (UPDATE — the shipment is
     * already saved when this runs, post-commit). Idempotent: a shipment already recorded
     * OFFLINE is left untouched.
     *
     * @param array<string, string> $metadata self::KEY => value (only scalar strings)
     */
    public function persist(ShipmentInterface $shipment, array $metadata): void
    {
        $existing = $this->read($shipment);
        if ($existing !== null) {
            return;
        }

        $packages = $shipment->getPackages();
        $merged = is_array($packages) ? $packages : [];
        foreach ($merged as $key => $value) {
            if (is_string($key) && str_starts_with($key, self::PACKAGES_KEY)) {
                unset($merged[$key]);
            }
        }
        $merged[self::PACKAGES_KEY] = $metadata;

        $shipment->setPackages($merged);
        $this->shipmentRepository->save($shipment);
    }

    /**
     * @return array<string, string>|null null = no OFFLINE fulfillment record on this shipment
     */
    public function read(ShipmentInterface $shipment): ?array
    {
        $packages = $shipment->getPackages();
        if (!is_array($packages)
            || !isset($packages[self::PACKAGES_KEY])
            || !is_array($packages[self::PACKAGES_KEY])
        ) {
            return null;
        }

        $marker = $packages[self::PACKAGES_KEY];
        if (!FulfillmentMode::isOffline(isset($marker[self::MODE]) ? (string) $marker[self::MODE] : null)) {
            return null; // malformed / non-offline marker — treat as absent
        }

        return $marker;
    }
}
