<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Capability;

use Secomm\ShippingCore\Api\Address\AddressRepresentation;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-FMBBSD (GHN-C slice 2) — what Secomm_Ghn declares about its canonical address needs
 * PER OPERATION (ShippingCore v5 architecture §5 — the same carrier may need a different
 * scheme and representation per operation; GHN is the asymmetry that motivated the contract):
 *
 *   RATE   → VN_ADMIN_PRE_2025 + [UNIT_ID]  (Calculate Fee needs district_id + ward_code)
 *   CREATE → VN_ADMIN_2025 + [TEXT_NAME]    (Create Order sends mapped NEW names with
 *                                             is_new_to_address=true — DEC-FEATFQWEQ3-001)
 *
 * CREATE is DECLARED ONLY: no CREATE runtime ships in this slice (GHN-D owns create/cancel).
 * The declaration exists so the capability contract advertises the full provider truth and
 * a future CREATE slice cannot mis-read RATE's scheme.
 *
 * Stage-2 rendering (canonical unit_code → GHN district_id/ward_code for RATE, mapped names
 * for CREATE) lives in the carrier module and NEVER enters this contract (ShippingCore owns
 * the scheme catalog + representation CATEGORY only).
 */
final class GhnAddressCapability implements CarrierOperationAddressCapabilityInterface
{
    public function getRequiredScheme(string $operation): string
    {
        ShippingAddressOperation::assertKnown($operation);

        return $operation === ShippingAddressOperation::CREATE
            ? VnSchemes::VN_ADMIN_2025
            : VnSchemes::VN_ADMIN_PRE_2025;
    }

    public function getSupportedRepresentations(string $operation): array
    {
        ShippingAddressOperation::assertKnown($operation);

        return $operation === ShippingAddressOperation::CREATE
            ? [AddressRepresentation::TEXT_NAME]
            : [AddressRepresentation::UNIT_ID];
    }

    /**
     * Fail closed (SPEC §13): when canonical translation cannot resolve (AMBIGUOUS/UNMAPPED and
     * no external disambiguation on the shift-left path), GHN methods are hidden — never guessed,
     * never textually approximated. Holds for both operations.
     */
    public function supportsTextualFallback(string $operation): bool
    {
        ShippingAddressOperation::assertKnown($operation);

        return false;
    }
}
