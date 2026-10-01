<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Secomm\Ghn\Model\Carrier\Ghn;
use Secomm\ShippingCore\Api\Shipment\CarrierOfflineCapabilityInterface;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — GHN's opt-in to the generic offline shipment flow: an
 * admin may record an OFFLINE shipment (Magento shipment without a GHN order) for an order
 * travelling on the secomm_ghn carrier, e.g. when a package deterministically violates the GHN
 * create constraints. GHN keeps every provider-specific rule (the offline-eligible reason
 * tokens are frozen in GhnShipmentSaveValidationObserver); ShippingCore only sees the identity.
 */
class GhnOfflineCapability implements CarrierOfflineCapabilityInterface
{
    public function getCarrierCode(): string
    {
        return Ghn::CARRIER_CODE;
    }

    public function isOfflineCreationEnabled(): bool
    {
        return true;
    }
}
