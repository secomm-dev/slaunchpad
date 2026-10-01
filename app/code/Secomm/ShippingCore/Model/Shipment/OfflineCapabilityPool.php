<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Shipment;

use Secomm\ShippingCore\Api\Shipment\CarrierOfflineCapabilityInterface;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — registry of carrier offline capabilities. Carriers opt in
 * via DI item entries (never named in ShippingCore); zero capabilities is a valid state, in
 * which case no offline control renders and posted offline intent is rejected fail-closed.
 */
class OfflineCapabilityPool
{
    /**
     * @param CarrierOfflineCapabilityInterface[] $capabilities
     */
    public function __construct(private readonly array $capabilities = [])
    {
    }

    /**
     * Prefix match on the RAW order shipping method (the same convention as every carrier gate
     * in this codebase — Order::getShippingMethod(true) splits on the FIRST underscore and
     * would misattribute "secomm_ghn_secomm_ghn" to carrier "secomm"). Disabled capabilities
     * are indistinguishable from absent ones.
     */
    public function findForShippingMethod(string $rawShippingMethod): ?CarrierOfflineCapabilityInterface
    {
        foreach ($this->capabilities as $capability) {
            $carrierCode = $capability->getCarrierCode();
            if ($carrierCode === '') {
                continue;
            }
            if (str_starts_with($rawShippingMethod, $carrierCode . '_')
                && $capability->isOfflineCreationEnabled()
            ) {
                return $capability;
            }
        }

        return null;
    }

    /**
     * @return CarrierOfflineCapabilityInterface[] all ENABLED capabilities (for rendering surfaces)
     */
    public function getEnabled(): array
    {
        return array_values(array_filter(
            $this->capabilities,
            static fn (CarrierOfflineCapabilityInterface $capability): bool => $capability->isOfflineCreationEnabled()
        ));
    }
}
