<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

use Magento\Quote\Model\Quote\Address;

/**
 * TASK-T78YH6 (Phase E-C0, SPIKE-YH439T §15) — THE single carrier-facing entry point for
 * canonical destination resolution. Never throws for domain states: the non-VN
 * UnsupportedDestinationException is translated into a non-applicable handoff so carriers
 * never catch ShippingCore exceptions individually.
 *
 * One runtime path only: builder → ShippingAddressResolutionManager (request-cached) — the
 * service never touches Secomm_VietNamAddress resolvers directly, never invokes the external
 * resolver pool, and never decides Magento checkout/rate outcomes (E-C1 owns those).
 */
interface CarrierAddressHandoffServiceInterface
{
    /**
     * Resolve the destination into the scheme required by the carrier capability and hand the
     * carrier a clean, carrier-facing result.
     */
    public function handoff(
        Address $destination,
        CarrierAddressCapabilityInterface $capability
    ): CarrierAddressHandoffInterface;

    /**
     * TASK-7AJ3K8 — same handoff for a PREBUILT context (scalar runtime address sources:
     * RateRequest destination fields, sales/order addresses — shapes that are not
     * Quote\Address and must not be faked into one). Same single path: the (request-cached)
     * resolution manager; the same exception translation and fallback semantics as handoff().
     */
    public function handoffContext(
        ShippingAddressResolutionContextInterface $context,
        CarrierAddressCapabilityInterface $capability
    ): CarrierAddressHandoffInterface;

    /**
     * TASK-Y3X6H5 (architecture v4 §5/§6) — PER-OPERATION handoff for a Magento destination:
     * the target scheme, textual-fallback permission and supported representations all come
     * from the carrier's per-operation capability (RATE and CREATE may differ).
     *
     * @param string $operation ShippingAddressOperation::* (validated)
     * @throws \Magento\Framework\Exception\LocalizedException unknown operation
     */
    /**
     * TASK-MD2BD3 v10 — optional Address Resolution Policy (AddressResolutionPolicy::*, default
     * FALLBACK) evaluated for AMBIGUOUS results: STRICT → unresolved + no ambiguity-driven
     * fallback; FALLBACK → unresolved + fallback eligible per capability; PICK_PRIMARY →
     * deterministic curated-primary selection (Secomm_VietNamAddress) → resolved handoff.
     */
    public function handoffForOperation(
        Address $destination,
        CarrierOperationAddressCapabilityInterface $capability,
        string $operation,
        string $addressResolutionPolicy = AddressResolutionPolicy::FALLBACK
    ): CarrierAddressHandoffInterface;

    /**
     * TASK-Y3X6H5 — per-operation handoff for a PREBUILT context. The context's target scheme
     * must match the capability's scheme for the given operation (fail-fast otherwise).
     *
     * TASK-MD2BD3 (v10 wiring completion) — the same optional Address Resolution Policy the
     * Address-based `handoffForOperation()` accepts now also applies to the scalar/context
     * entry (legacy-scheme RATE consumers such as Secomm_Ghn reach resolution through this
     * method): STRICT → AMBIGUOUS becomes an unresolved, non-textual-fallback handoff;
     * FALLBACK → legacy AMBIGUOUS shape (candidates + textual-fallback per capability);
     * PICK_PRIMARY → deterministic curated-primary selection via the shared
     * Secomm_VietNamAddress selector (NO_DESIGNATED_PRIMARY/multi-primary fail closed to an
     * unresolved, non-textual-fallback handoff). Default FALLBACK preserves the pre-v10 shape.
     *
     * @param string $operation ShippingAddressOperation::* (validated)
     * @throws \Magento\Framework\Exception\LocalizedException unknown operation
     * @throws \LogicException context/capability scheme mismatch or unknown policy
     */
    public function handoffContextForOperation(
        ShippingAddressResolutionContextInterface $context,
        CarrierOperationAddressCapabilityInterface $capability,
        string $operation,
        string $addressResolutionPolicy = AddressResolutionPolicy::FALLBACK
    ): CarrierAddressHandoffInterface;
}
