<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Address;

use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;

/**
 * TASK-6YG3HP — DOCUMENTED COMPATIBILITY SHIM (the ONLY remaining GHTK reference to the
 * deprecated per-carrier address contract).
 *
 * Why it exists: ShippingCore's scalar `RuntimeAddressContextBuilderInterface` still
 * type-hints the deprecated `CarrierAddressCapabilityInterface`, and Secomm_ShippingCore
 * is under a hard stop (amending it for GHTK's temporary scheme uncertainty is
 * forbidden — architecture §28/§35). GHTK's real capability is the per-operation
 * `GhtkOperationAddressCapability`; this shim adapts it for that single legacy call.
 *
 * REMOVAL CONDITION: delete this class + the adapter's builder-capability argument as
 * soon as ShippingCore exposes a per-operation context-builder entry (GHTK v5 follow-up
 * after the address scheme freeze — TASK-44F7V7).
 */
final class GhtkLegacyCapabilityShim implements CarrierAddressCapabilityInterface
{
    public function __construct(
        private readonly GhtkOperationAddressCapability $operationCapability
    ) {
    }

    /**
     * GHTK's scheme/representation are currently identical across RATE and CREATE,
     * so the RATE values faithfully represent the (pending-freeze) capability.
     */
    public function getRequiredScheme(): string
    {
        return $this->operationCapability->getRequiredScheme(ShippingAddressOperation::RATE);
    }

    public function supportsTextualFallback(): bool
    {
        return $this->operationCapability->supportsTextualFallback(ShippingAddressOperation::RATE);
    }
}
