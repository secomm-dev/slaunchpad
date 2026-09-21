<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * DEC-FEATYA2C0W-004 (D2/D4) / TASK-AQT7V3 — scalar-only, immutable transport view of the
 * destination being resolved (same philosophy as Api\ShippingContextInterface: free of mutable
 * Magento models — the quote/address object is never exposed).
 *
 * Carries the minimum an external disambiguation provider (Secomm_VietMap, a Google-based
 * resolver, …) will eventually need: the full address text, the current canonical unit
 * identity, the target scheme and the AMBIGUOUS candidate list. No geocoding, no address
 * normalization/fingerprinting happens here.
 *
 * External address disambiguation may use address-related textual context such as street
 * text and canonical candidates. Recipient identity is not part of the address-resolution
 * contract — receiver/customer name, phone or email are never carried here
 * (getReceiverText() removed in the TASK-5XDG1P TL review, before contract freeze).
 */
interface ShippingAddressResolutionContextInterface
{
    /** ISO country id of the destination (e.g. "VN"); null when unknown. Non-VN destinations skip resolution. */
    public function getCountryId(): ?string;

    /** Canonical scheme of the current identity (active scheme bridge output); null when not yet established. */
    public function getSourceScheme(): ?string;

    /** Canonical unit code currently known for the destination; null when not yet established. */
    public function getSourceUnitCode(): ?string;

    /** Scheme resolution must produce (the carrier-required scheme); recorded for resolver consumption. */
    public function getTargetScheme(): string;

    /** Full shipping street/address text as supplied by the customer; null when unavailable. */
    public function getStreetText(): ?string;

    /**
     * AMBIGUOUS candidate target codes (in the target scheme) awaiting disambiguation;
     * empty when the context does not carry candidates.
     *
     * @return string[]
     */
    public function getCandidateCodes(): array;
}
