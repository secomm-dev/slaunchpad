<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Secomm\Ghn\Model\Exception\GhnRateEstimationException;

/**
 * TASK-FMBBSD (GHN-C slice 2), TASK-WAWNDS — translates a Magento RateRequest into the
 * normalized GHN rate input. Collection only: no canonical resolution (Stage 1 = ShippingCore
 * handoff downstream in GhnRateCalculator), no provider identity, no business policy.
 *
 * - Weight is normalized to GHN GRAMS through the shared
 *   {@see \Secomm\ShippingCore\Model\Physical\StoreWeightConverter} (store
 *   `general/locale/weight_unit` 'kgs' | 'lbs'; missing/unexpected unit → fail-closed
 *   LocalizedException at the carrier boundary — never a guess). Per-UNIT weights are
 *   converted once and multiplied by whole unit counts inside
 *   {@see QuoteParcelEstimator::estimate()} (PRODUCT_UNIT_AS_PACKAGE).
 * - Dimensions are deliberately NOT read at RATE: Magento carries no dimension-unit contract,
 *   and the sandbox Fee evidence shows unproven dimensions DISTORT pricing (type-2 root dims
 *   changed the fee materially) while type-5 items price fine weight-only. Omittance is
 *   contract-valid; dimensions come back when an upstream unit-aware parcel contract exists.
 * - No collection amount at RATE: whether the order is COD and how much is collected is decided
 *   upstream — the carrier never infers it from the Magento payment method (SPEC §16).
 * - No dest city node id exists on a RateRequest, so cityId is always null and the locality
 *   NAME (native `city` field — the AddressDropdown active locality level) drives the
 *   name-based canonical bridge downstream.
 *
 * @throws GhnRateEstimationException when the quote cannot be estimated safely (invalid parcel
 *         data or an adapter/data limitation — mapped to CarrierRateOutcome at the boundary)
 */
class GhnRateRequestMapper
{
    public function __construct(
        private readonly QuoteParcelEstimator $parcelEstimator
    ) {
    }

    public function map(RateRequest $request): GhnRateQuery
    {
        return new GhnRateQuery(
            $request->getDestCountryId() !== null ? (string) $request->getDestCountryId() : null,
            (int) $request->getDestRegionId(),
            null,
            $this->extractLocalityName($request),
            $this->parcelEstimator->estimate($request)
        );
    }

    private function extractLocalityName(RateRequest $request): ?string
    {
        $localityName = trim((string) $request->getDestCity());

        return $localityName !== '' ? $localityName : null;
    }
}
