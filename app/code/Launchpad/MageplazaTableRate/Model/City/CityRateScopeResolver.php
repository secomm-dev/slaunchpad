<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — city precedence over an already-matched rate collection.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model\City;

use Launchpad\MageplazaTableRate\Model\MethodSettingsProvider;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Narrowing semantics (SPEC-TASK-5XQXZK §3, directive §13 — precedence narrows LOCATION scope
 * only, never the in-tier SUM/MIN/MAX aggregation):
 *
 *   No city-constrained row among the matched rows      → untouched (100% legacy behavior).
 *   Destination resolves to unit U:
 *     tier 1  rows constrained to exactly U              → win, everything else removed.
 *     tier 2  rows WITHOUT city constraint on the exact region  → win if no tier-1 row exists.
 *     tier 3  remaining wildcard rows                    → win only when nothing narrower exists.
 *   Destination does NOT resolve (AMBIGUOUS/UNMAPPED/non-VN/blank):
 *     city-constrained rows never match; the wildcard tiers keep Mageplaza's own aggregation.
 *
 * Rows within the winning tier are left exactly as Mageplaza's filterByRequest produced them —
 * all of them stay in the collection so the method's calculate_rule (SUM/MIN/MAX) combines them
 * unchanged.
 */
class CityRateScopeResolver
{
    public function __construct(
        private readonly MethodSettingsProvider $settingsProvider,
        private readonly DestinationCityResolver $destinationCityResolver
    ) {
    }

    /**
     * @param \Mageplaza\TableRateShipping\Model\ResourceModel\Rate\Collection $collection
     *        already filtered by Mageplaza's filterByRequest — mutated in place
     */
    public function apply($collection, RateRequest $request): void
    {
        $items = $collection->getItems();
        if ($items === []) {
            return;
        }

        $cityByRateId = $this->settingsProvider->fetchCityCodes(
            array_map(static fn ($item) => (int) $item->getId(), $items)
        );
        if ($cityByRateId === []) {
            return;
        }

        $keepIds = $this->resolveKeepIds($items, $cityByRateId, $request);

        foreach ($items as $item) {
            $rateId = (int) $item->getId();
            if (!isset($keepIds[$rateId])) {
                $collection->removeItemByKey($rateId);
            }
        }
    }

    /**
     * @param array<int, mixed> $items
     * @param array<int, string> $cityByRateId
     * @return array<int, int> rate_id => rate_id to keep
     */
    private function resolveKeepIds(array $items, array $cityByRateId, RateRequest $request): array
    {
        $unitCode = $this->destinationCityResolver->resolveCityCode(
            (int) $request->getDestRegionId(),
            (string) $request->getDestCity()
        );

        // Tier 1 — exact address-node match.
        if ($unitCode !== null) {
            $tier1 = [];
            foreach ($cityByRateId as $rateId => $cityCode) {
                if ($cityCode === $unitCode) {
                    $tier1[$rateId] = $rateId;
                }
            }
            if ($tier1 !== []) {
                return $tier1;
            }
        }

        // Tier 2 — no city constraint, but the row constrains exactly this region. Only applies
        // when the destination DID resolve to a known address node: with no resolvable city
        // identity there is no basis for narrowing, so every wildcard tier keeps Mageplaza's
        // own aggregation (directive §12 — unresolved: city rows never match, wildcards
        // participate untouched).
        $regionId = $request->getDestRegionId();
        if ($unitCode !== null && $regionId !== null && (string) $regionId !== '' && (string) $regionId !== '0') {
            $tier2 = [];
            foreach ($items as $item) {
                $rateId = (int) $item->getId();
                if (!isset($cityByRateId[$rateId]) && (string) $item->getRegion() === (string) $regionId) {
                    $tier2[$rateId] = $rateId;
                }
            }
            if ($tier2 !== []) {
                return $tier2;
            }
        }

        // Tier 3 — every remaining wildcard row (legacy behavior for this destination).
        $tier3 = [];
        foreach ($items as $item) {
            $rateId = (int) $item->getId();
            if (!isset($cityByRateId[$rateId])) {
                $tier3[$rateId] = $rateId;
            }
        }

        return $tier3;
    }
}
