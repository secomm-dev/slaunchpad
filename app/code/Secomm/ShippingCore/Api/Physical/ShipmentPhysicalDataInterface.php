<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Physical;

/**
 * DEC-TASK9Q5ZAK-001 — carrier-neutral PHYSICAL facts of one Magento shipment: what was actually
 * packed and handed to the carrier. Facts ONLY — no service types, no provider ids, no volumetric
 * formulas, no carrier business rules (carriers interpret these facts at their own boundary).
 *
 * One Magento Shipment may contain N physical packages (N boxes = ONE fulfillment event);
 * multiple Magento Shipments mean separate fulfillment events, not multiple boxes.
 */
interface ShipmentPhysicalDataInterface
{
    /**
     * Total physical weight in grams (sum of the packages — a derived fact, not a Magento field).
     */
    public function getTotalWeightG(): int;

    /**
     * @return PhysicalPackageInterface[] the real packages handed to the carrier (>= 1)
     */
    public function getPackages(): array;
}
