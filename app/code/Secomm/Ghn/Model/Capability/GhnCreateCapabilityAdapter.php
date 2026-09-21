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
 * TASK-9Q5ZAK (GHN-D) — module-private bridge that adapts the per-operation
 * {@see GhnAddressCapability} to the deprecated legacy per-carrier capability SHAPE, pinned to
 * the CREATE operation. Mirror of {@see GhnRateCapabilityAdapter} (RATE): ShippingCore's
 * `RuntimeAddressContextBuilderInterface::build(...)` stamps the target scheme from the legacy
 * `getRequiredScheme()`, and its per-operation adaptation for scalar sources is @internal —
 * this bridge keeps Secomm_Ghn off ShippingCore internals.
 *
 * The handoff itself goes through the per-operation entry point
 * `handoffContextForOperation(context, capability, CREATE)`, which validates the context target
 * scheme (VN_ADMIN_2025) against the raw per-operation capability and wraps its own adapter
 * internally — this bridge is consumed and discarded at the context-build step only.
 *
 * Remove this class when ShippingCore ships a public per-operation scalar context builder.
 */
final class GhnCreateCapabilityAdapter implements CarrierAddressCapabilityInterface
{
    public function __construct(private readonly GhnAddressCapability $capability)
    {
    }

    /**
     * The CREATE operation's required canonical scheme (VN_ADMIN_2025 — current names).
     */
    public function getRequiredScheme(): string
    {
        return $this->capability->getRequiredScheme(ShippingAddressOperation::CREATE);
    }

    /**
     * CREATE is fail-closed — consulted only on the unresolved branch of the handoff.
     */
    public function supportsTextualFallback(): bool
    {
        return $this->capability->supportsTextualFallback(ShippingAddressOperation::CREATE);
    }
}
