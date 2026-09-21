<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Capability;

use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;

/**
 * TASK-FMBBSD (GHN-C slice 2) — module-private bridge that adapts the per-operation
 * {@see GhnAddressCapability} to the deprecated legacy per-carrier capability SHAPE.
 *
 * Why this exists: Secomm_Ghn rates from RateRequest SCALARS, so the context is built with
 * `RuntimeAddressContextBuilderInterface::build(..., CarrierAddressCapabilityInterface ...)`
 * — an entry point that still takes the legacy shape and stamps the target scheme from
 * `getRequiredScheme()`. ShippingCore v5's per-operation adaptation for this exact wrap is
 * `Model\Address\OperationCapabilityAdapter`, which is @internal to ShippingCore and must not
 * be consumed cross-module (dependency hygiene); its only public per-op builder entry,
 * `DestinationContextBuilderInterface::buildForOperation(...)`, requires a Quote\Address that a
 * `collectRates(RateRequest)` carrier does not have.
 *
 * Scope pinned to ShippingAddressOperation::RATE — the only operation wired in this slice.
 * The handoff itself still goes through the per-operation entry point
 * `CarrierAddressHandoffServiceInterface::handoffContextForOperation(context, capability, RATE)`,
 * which validates the context target scheme against the raw per-operation capability and wraps
 * its own adapter internally — this bridge is consumed and discarded at the context-build step
 * only (verified: no double adaptation, no invariant break).
 *
 * Remove this class when ShippingCore ships a public per-operation scalar context builder.
 */
final class GhnRateCapabilityAdapter implements CarrierAddressCapabilityInterface
{
    public function __construct(private readonly GhnAddressCapability $capability)
    {
    }

    /**
     * The RATE operation's required canonical scheme (VN_ADMIN_PRE_2025).
     */
    public function getRequiredScheme(): string
    {
        return $this->capability->getRequiredScheme(ShippingAddressOperation::RATE);
    }

    /**
     * RATE is fail-closed — consulted only on the unresolved branch of the handoff.
     */
    public function supportsTextualFallback(): bool
    {
        return $this->capability->supportsTextualFallback(ShippingAddressOperation::RATE);
    }
}
