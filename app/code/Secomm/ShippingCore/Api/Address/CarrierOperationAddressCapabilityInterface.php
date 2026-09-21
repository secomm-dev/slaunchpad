<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * TASK-Y3X6H5 (address-shipping architecture Revision v4 §5) — what a carrier declares about
 * its address needs PER OPERATION (the same carrier may need a different scheme and a different
 * representation for RATE vs CREATE; GHN proves the asymmetry).
 *
 * ShippingCore owns the SCHEME (from the Secomm_VietNamAddress catalog) and the REPRESENTATION
 * CATEGORY (UNIT_ID | TEXT_NAME) only. Provider-specific values (GHN district_id, GHN ward_code,
 * GHN `is_new_to_address`, GHTK payloads…) are rendered by the carrier module at Stage 2 and are
 * NEVER part of this contract.
 *
 * Replaces the per-carrier `CarrierAddressCapabilityInterface` (deprecated): carriers should
 * implement THIS interface and consume the `handoffForOperation()`/`handoffContextForOperation()`
 * entry points of the handoff service.
 */
interface CarrierOperationAddressCapabilityInterface
{
    /**
     * Canonical administrative scheme the given operation expects
     * (e.g. RATE → VN_ADMIN_PRE_2025, CREATE → VN_ADMIN_2025 for GHN).
     *
     * Must be a known scheme from the Secomm_VietNamAddress scheme catalog.
     */
    public function getRequiredScheme(string $operation): string;

    /**
     * Address representation categories the given operation accepts.
     *
     * @param string $operation ShippingAddressOperation::*
     * @return string[] AddressRepresentation::* values (non-empty)
     */
    public function getSupportedRepresentations(string $operation): array;

    /**
     * Whether the carrier can safely continue with textual/current address data for this
     * operation when canonical translation cannot be resolved.
     */
    public function supportsTextualFallback(string $operation): bool;
}
