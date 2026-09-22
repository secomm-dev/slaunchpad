<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — destination city text → stable address-node code.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model\City;

use Magento\Framework\Exception\LocalizedException;
use Secomm\VietNamAddress\Api\Data\VnOperationalIdentityInterface;
use Secomm\VietNamAddress\Api\VnOperationalNameResolverInterface;

/**
 * Resolves the runtime destination (region_id + city TEXT as persisted by checkout) into the
 * stable address-node code (`directory_region_city.code` / Secomm unit_code) used by the city
 * dimension — strictly through the existing Secomm_VietNamAddress resolver, never by guessing:
 * AMBIGUOUS / UNMAPPED / non-VN / scheme-inactive destinations resolve to null, in which case
 * city-specific rate rows must NOT match (wildcard rows still participate).
 *
 * Memoized per request — RateRequest-based filtering runs more than once per collection cycle
 * (Mageplaza carrier + fallback coordinator consume the same destination).
 */
class DestinationCityResolver
{
    /** @var array<string, ?string> */
    private array $cache = [];

    public function __construct(
        private readonly VnOperationalNameResolverInterface $nameResolver
    ) {
    }

    public function resolveCityCode(int $regionId, string $destCity): ?string
    {
        $destCity = trim($destCity);
        if ($regionId <= 0 || $destCity === '') {
            return null;
        }

        $cacheKey = $regionId . '|' . $destCity;
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $code = null;
        try {
            $resolution = $this->nameResolver->resolveWardByName($regionId, $destCity);
            $identity = $resolution->isResolved() ? $resolution->getIdentity() : null;
            $unitCode = $identity instanceof VnOperationalIdentityInterface ? $identity->getUnitCode() : null;
            $code = ($unitCode !== null && $unitCode !== '') ? $unitCode : null;
        } catch (LocalizedException $exception) {
            // Unresolved destination is a normal business state — never a guess, never an error.
        }

        $this->cache[$cacheKey] = $code;

        return $code;
    }
}
