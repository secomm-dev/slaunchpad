<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;

/**
 * TASK-Y3X6H5 — internal BC bridge: adapts a PER-OPERATION capability to the legacy
 * per-carrier `CarrierAddressCapabilityInterface` shape so the existing resolution manager
 * and runtime context builder (which read `getRequiredScheme()`) keep working unchanged on
 * the per-operation path.
 *
 * Internal to ShippingCore — carriers implement the per-operation capability directly, never
 * this adapter.
 */
final class OperationCapabilityAdapter implements CarrierAddressCapabilityInterface
{
    public function __construct(
        private readonly CarrierOperationAddressCapabilityInterface $capability,
        private readonly string $operation
    ) {
        ShippingAddressOperation::assertKnown($operation);
    }

    /**
     * The operation's required scheme (e.g. RATE → PRE-2025, CREATE → 2025 for GHN).
     */
    public function getRequiredScheme(): string
    {
        return $this->capability->getRequiredScheme($this->operation);
    }

    /**
     * The operation's textual-fallback declaration — consulted only on the unresolved branch.
     */
    public function supportsTextualFallback(): bool
    {
        return $this->capability->supportsTextualFallback($this->operation);
    }
}
