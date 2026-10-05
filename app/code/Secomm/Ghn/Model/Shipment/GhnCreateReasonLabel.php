<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Secomm\Ghn\Model\Admin\GhnCreateOutcomeNotifier;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;

/**
 * TASK-W5BW4F — reason token → admin-facing text for the CREATE failure surface (layer 2:
 * the save-time error message and the shipment-view status section). Presentation only —
 * tokens stay the diagnostic contract ({@see GhnCreateValidationException}, provider rows);
 * unknown tokens render verbatim so new provider reasons are never swallowed.
 */
final class GhnCreateReasonLabel
{
    /**
     * @return string human-readable reason; the raw token when unmapped
     */
    public function label(?string $token): string
    {
        return match ($token) {
            GhnCreateValidationException::REASON_INVALID_PARCEL => (string) __(
                'Package data missing, empty, or above the GHN per-package limits (weight / per-dimension cm).'
            ),
            GhnCreateValidationException::REASON_INVALID_CONFIGURATION => (string) __(
                'GHN configuration is invalid (payment type / required note / size limit).'
            ),
            ShippingFailureReason::PROVIDER_MAPPING_MISSING => (string) __(
                'No approved GHN address mapping for the destination.'
            ),
            ShippingFailureReason::CANONICAL_UNRESOLVED => (string) __(
                'The shipping address could not be resolved to the GHN address scheme.'
            ),
            ShippingFailureReason::UNSUPPORTED_DESTINATION => (string) __(
                'The destination is outside the GHN-supported area.'
            ),
            ShippingFailureReason::TECHNICAL_ERROR => (string) __(
                'Uncertain result — connection or provider error. Verify on the GHN portal before retrying.'
            ),
            ShippingFailureReason::SERVICE_UNAVAILABLE => (string) __(
                'GHN rejected the request (business or availability).'
            ),
            GhnShipmentCreationService::REASON_COD_AMOUNT_EXCEEDS_PROVIDER_LIMIT => (string) __(
                'The COD amount exceeds the GHN provider cap.'
            ),
            default => (string) $token,
        };
    }
}
