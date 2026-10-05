<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Shipment;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — carrier opt-in to the generic offline shipment flow.
 *
 * Implementing this interface is the carrier module's own declaration that an admin may record
 * an OFFLINE shipment (Magento shipment without a provider order) for an order travelling on
 * this carrier. ShippingCore never names carriers: implementations register themselves via the
 * `OfflineCapabilityPool` DI array (zero registered capabilities is a valid state), mirroring
 * `CarrierPhysicalLimitInterface` / `CoverageTargetRegistry`.
 *
 * The carrier still owns every provider-specific rule (when its create fails deterministically,
 * which reasons are offline-eligible); ShippingCore only needs the identity to render the admin
 * control and to gate posted offline intent fail-closed.
 */
interface CarrierOfflineCapabilityInterface
{
    /**
     * Carrier code as it appears as the PREFIX of the raw order shipping method
     * (e.g. "secomm_ghn" for "secomm_ghn_secomm_ghn").
     */
    public function getCarrierCode(): string;

    /**
     * Whether the admin "Create Offline Shipment" path is currently offered for this carrier.
     * A disabled capability is indistinguishable from no capability (button hidden + posted
     * intent rejected).
     */
    public function isOfflineCreationEnabled(): bool;
}
