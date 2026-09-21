<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

/**
 * TASK-FMBBSD — normalized input for one GHN rate operation (architecture v3 §6 path:
 * ShippingCore rate request → canonical PRE_2025 → provider mapping → Calculate Fee).
 *
 * $collectionAmount is the COD amount ALREADY COMPUTED upstream (Secomm_Ghn never owns COD
 * policy — architecture v3 §8); the adapter renders it to the fee API's `cod_value` field.
 * Note the deliberate asymmetry: fee = `cod_value`, Create Order = `cod_amount` (current docs).
 *
 * TASK-WAWNDS: the single-parcel {@see GhnParcel} rate input became a quote-time
 * {@see QuoteParcelEstimate} (PRODUCT_UNIT_AS_PACKAGE) so heavy/multi-parcel quotes serialize
 * the type-5 `items[]` payload. GhnParcel stays CREATE-only.
 */
final class GhnRateQuery
{
    public function __construct(
        private readonly ?string $countryId,
        private readonly int $regionId,
        private readonly ?int $cityId,
        private readonly ?string $localityName,
        private readonly QuoteParcelEstimate $estimate,
        private readonly ?int $collectionAmount = null
    ) {
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function getRegionId(): int
    {
        return $this->regionId;
    }

    public function getCityId(): ?int
    {
        return $this->cityId;
    }

    public function getLocalityName(): ?string
    {
        return $this->localityName;
    }

    public function getEstimate(): QuoteParcelEstimate
    {
        return $this->estimate;
    }

    public function getCollectionAmount(): ?int
    {
        return $this->collectionAmount;
    }
}
