<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Rate;

use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory;
use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRate;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;

/**
 * TASK-W8SH0N — translates parsed GHTK RATE results into the SHARED
 * `CarrierRateOutcome` semantics (v5). THE single classification point for the
 * carrier's RATE path — status drives orchestration (fallback eligibility),
 * failure reasons stay diagnostic:
 *
 * - SUCCESS payload + delivery=true + composed amount → SUCCESS;
 * - BUSINESS_REJECTION / delivery=false → UNAVAILABLE (SERVICE_UNAVAILABLE) —
 *   never fallback-eligible: a business-invalid address must not trigger
 *   emergency pricing;
 * - MALFORMED / transport NETWORK / SERVER_ERROR / TIMEOUT / INVALID_RESPONSE →
 *   TECHNICAL_FAILURE (TECHNICAL_ERROR) — the fallback contributor;
 * - transport CLIENT_ERROR (400 invalid, 403 auth/config, 404) and RATE_LIMIT
 *   (429 — undocumented by GHTK) → UNAVAILABLE (SERVICE_UNAVAILABLE): 403 is a
 *   merchant auth/config problem (scoped tokens expire — non-transient from the
 *   request's perspective); 429 is classified conservatively without retry —
 *   NEEDS_PROVIDER_VERIFICATION for any stricter semantics.
 *
 * GHTK error codes/messages never reach ShippingCore — only shared statuses and
 * shared failure reasons (plus a carrier detail string where the contract allows).
 */
class GhtkRateOutcomeFactory
{
    /** Transport categories representing unusable technical data. */
    private const TECHNICAL_CATEGORIES = [
        CarrierHttpErrorCategory::NETWORK,
        CarrierHttpErrorCategory::SERVER_ERROR,
        CarrierHttpErrorCategory::TIMEOUT,
        CarrierHttpErrorCategory::INVALID_RESPONSE,
    ];

    public function fromParsedResponse(GhtkFeeResponse $response, ?CarrierRateInterface $rate = null): CarrierRateOutcomeInterface
    {
        if ($response->getKind() === GhtkFeeResponse::KIND_MALFORMED) {
            return CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR);
        }

        if ($response->getKind() === GhtkFeeResponse::KIND_BUSINESS_REJECTION) {
            return CarrierRateOutcome::unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE);
        }

        $fee = $response->getFee();
        if ($fee !== null && !$fee->delivery) {
            // Business answer: GHTK does not deliver to this destination.
            return CarrierRateOutcome::unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE);
        }

        return $rate !== null
            ? CarrierRateOutcome::success($rate)
            : CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR);
    }

    /**
     * Transport-layer failure (raised by the shared client / retry executor).
     */
    public function fromTransportException(GhtkApiException $exception): CarrierRateOutcomeInterface
    {
        $category = $exception->getCategory();

        return in_array($category, self::TECHNICAL_CATEGORIES, true)
            ? CarrierRateOutcome::technicalFailure(ShippingFailureReason::TECHNICAL_ERROR)
            : CarrierRateOutcome::unavailable(ShippingFailureReason::SERVICE_UNAVAILABLE);
    }

    /**
     * A composed realtime rate (amount = RateComposer output, store currency).
     */
    public function success(float $amount): CarrierRateOutcomeInterface
    {
        return CarrierRateOutcome::success(new CarrierRate($amount));
    }
}
