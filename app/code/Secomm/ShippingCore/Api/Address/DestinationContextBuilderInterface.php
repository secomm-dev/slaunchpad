<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

use Magento\Quote\Model\Quote\Address;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;

interface DestinationContextBuilderInterface
{
    /**
     * @deprecated TASK-Y3X6H5 (architecture v4 §5): use `buildForOperation()` — address
     *             capability is per-operation now. Kept temporarily for existing consumers.
     */
    public function build(
        Address $destination,
        CarrierAddressCapabilityInterface $capability
    ): ShippingAddressResolutionContextInterface;

    /**
     * TASK-Y3X6H5 — per-operation build: the target scheme comes from the carrier's
     * per-operation capability (RATE and CREATE may target different schemes).
     *
     * @param string $operation ShippingAddressOperation::* (validated)
     * @throws \Magento\Framework\Exception\LocalizedException unknown operation
     */
    public function buildForOperation(
        Address $destination,
        CarrierOperationAddressCapabilityInterface $capability,
        string $operation
    ): ShippingAddressResolutionContextInterface;
}
