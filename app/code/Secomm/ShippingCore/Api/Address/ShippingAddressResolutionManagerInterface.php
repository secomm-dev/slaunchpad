<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * DEC-FEATYA2C0W-004 (D4) / TASK-AQT7V3 + TASK-5XDG1P — orchestration entry point.
 *
 * Phase E-B delivers the concrete LOCAL canonical manager
 * (Secomm\ShippingCore\Model\Address\ShippingAddressResolutionManager): it validates the
 * context, serves request-scoped cached results, and delegates canonical graph resolution to
 * Secomm_VietNamAddress (VnAdminAddressResolverInterface). External disambiguation
 * (ExternalAddressResolverPool) and the textual-fallback decision
 * (CarrierAddressCapabilityInterface::supportsTextualFallback()) remain LATER phases — the
 * local manager never invokes them.
 */
interface ShippingAddressResolutionManagerInterface
{
    /**
     * Resolve the destination described by $context into the scheme required by $capability.
     *
     * @return ResolvedShippingAddressInterface never null — AMBIGUOUS/UNMAPPED are statuses,
     *              not exceptions (DEC-FEATYA2C0W-003 semantics).
     * @throws \Secomm\ShippingCore\Model\Address\Exception\UnsupportedDestinationException
     *              the destination is outside the Vietnam canonical scope (non-VN countryId) —
     *              explicit not-applicable bypass; a non-Vietnam destination is never mislabeled
     *              UNMAPPED and no fifth canonical status exists for it.
     * @throws \Magento\Framework\Exception\LocalizedException unknown source/target scheme code
     *              (configuration fault, propagated unchanged from the Secomm_VietNamAddress resolver).
     */
    public function resolve(
        ShippingAddressResolutionContextInterface $context,
        CarrierAddressCapabilityInterface $capability
    ): ResolvedShippingAddressInterface;
}
