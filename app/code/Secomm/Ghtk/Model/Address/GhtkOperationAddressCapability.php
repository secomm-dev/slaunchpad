<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Address;

use Secomm\Ghtk\Model\GhtkApiProfile;
use Secomm\ShippingCore\Api\Address\AddressRepresentation;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;

/**
 * TASK-6YG3HP — GHTK's PER-OPERATION address capability (ShippingCore v5,
 * `CarrierOperationAddressCapabilityInterface`). GHTK is a primary TEXT_NAME
 * carrier: both RATE and CREATE speak the canonical Vietnamese text of ONE
 * candidate scheme, textual fallback is never allowed (no guessed address).
 *
 * ⚠ ADDRESS SCHEME FREEZE = PENDING (TASK-44F7V7 BLOCKED_BY_CREDENTIAL):
 * the scheme value below is the DEC-TASK7AJ3K8-002 CANDIDATE currently in
 * effect — it is NOT a runtime-verified fact. When the staging probe lands,
 * this class is the SINGLE SEAM to update (~2 lines: per-op requiredScheme).
 * Do not treat the value as architecture fact anywhere else.
 *
 * (The deprecated per-carrier contract is adapted for the one legacy builder call by
 * `GhtkLegacyCapabilityShim` — see that class for the removal condition.)
 */
final class GhtkOperationAddressCapability implements CarrierOperationAddressCapabilityInterface
{
    public function __construct(
        private readonly GhtkApiProfile $profile
    ) {
    }

    /**
     * CANDIDATE value pending TASK-44F7V7 staging probe — single freeze seam.
     */
    public function getRequiredScheme(string $operation): string
    {
        ShippingAddressOperation::assertKnown($operation);
        $scheme = $this->profile->getAddressScheme();
        if ($scheme === null || trim($scheme) === '') {
            throw new \LogicException('GHTK address capability requires a profile-bound address scheme.');
        }

        return $scheme;
    }

    /**
     * GHTK speaks canonical Vietnamese TEXT — never carrier administrative IDs
     * (official API is text-based; SPIKE-A1DGPY).
     *
     * @return string[] AddressRepresentation::*
     */
    public function getSupportedRepresentations(string $operation): array
    {
        ShippingAddressOperation::assertKnown($operation);

        return [AddressRepresentation::TEXT_NAME];
    }

    /**
     * Canonical AMBIGUOUS/UNMAPPED → no GHTK request (no guessed address) — for
     * BOTH operations; TEXT_NAME is the PRIMARY representation, not a fallback.
     */
    public function supportsTextualFallback(string $operation): bool
    {
        ShippingAddressOperation::assertKnown($operation);

        return false;
    }

}
