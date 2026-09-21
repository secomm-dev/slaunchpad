<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Fallback;

/**
 * TASK-XXBN5X (Phase E-SL0, SPIKE-WHHEZV §8/§9) — provider-neutral fallback rate request.
 *
 * Deliberately SCALAR-ONLY and third-party-neutral: no Magento RateRequest crosses the provider
 * API (the future ShippingCore orchestration maps Magento rate-flow data onto this contract), no
 * carrier code, no provider method identifiers, no customer PII, no canonical address candidates.
 *
 * Field set maps 1-1 to the audited fallback-engine matching dimensions (country + region +
 * postcode + cart weight/subtotal/qty + store/customer-group scope). Region/postcode granularity
 * is SUFFICIENT by design — fallback pricing is not the service-eligibility engine; ward/district
 * fields are added only if a concrete generic fallback provider ever requires them.
 *
 * Product shipping-group data is deliberately absent: fallback profiles that match-all on the
 * shipping-group dimension are sufficient for the Launchpad use case; a follow-up adds it only
 * with concrete evidence.
 */
interface FallbackRateRequestInterface
{
    /** Destination ISO country id, e.g. "VN"; null when unknown. */
    public function getCountryId(): ?string;

    /** Destination Magento region id; null when unknown. */
    public function getRegionId(): ?int;

    /** Destination postcode as supplied (providers apply their own format handling); null when unknown. */
    public function getPostcode(): ?string;

    /** Total cart weight in the store's weight unit (>= 0). */
    public function getWeight(): float;

    /** Cart subtotal used for rate conditions (>= 0). */
    public function getSubtotal(): float;

    /** Total item quantity (>= 0). */
    public function getQty(): float;

    /** Store scope the fallback is calculated for (rate profiles are store/customer-group scoped). */
    public function getStoreId(): int;

    /** Customer group id for scoped rate profiles; null when unknown (caller resolves a concrete group). */
    public function getCustomerGroupId(): ?int;
}
