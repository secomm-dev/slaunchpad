<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Mapping;

use Secomm\Ghn\Model\Address\GhnSchemes;
use Secomm\Ghn\Model\Cache\MappingCache;
use Secomm\Ghn\Model\Exception\GhnMappingNotFoundException;
use Secomm\Ghn\Model\ResourceModel\AddressMapping;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;

/**
 * SPEC-FEAT-FQWEQ3 §7 — THE runtime resolution path: canonical code → approved mapping → GHN
 * identity. Deterministic, name-free, fail-closed (GhnMappingNotFoundException for miss /
 * disabled unit / incomplete triple consumers). Only HITS are cached — a miss must stay a miss
 * until curation approves a mapping.
 */
class GhnMappingResolver
{
    private const CACHE_PREFIX = 'secomm_ghn_map|';

    public function __construct(
        private readonly AddressMapping $mappingResource,
        private readonly AddressUnit $unitResource,
        private readonly MappingCache $cache
    ) {
    }

    /**
     * Resolve the canonical unit into the GHN identity of its own scheme's bridge.
     *
     * @throws GhnMappingNotFoundException no approved mapping / unit missing / unit disabled
     */
    public function resolve(string $secommScheme, string $unitCode): GhnLocation
    {
        $cacheKey = self::CACHE_PREFIX . $secommScheme . '|' . $unitCode;
        $cached = $this->cache->load($cacheKey);
        if (is_string($cached)) {
            /** @var array<string, mixed> $data */
            $data = json_decode($cached, true);
            if (is_array($data)) {
                return GhnLocation::fromArray($data);
            }
        }

        $location = $this->load($secommScheme, $unitCode);
        $this->cache->save(
            (string) json_encode($location->toArray(), JSON_UNESCAPED_UNICODE),
            $cacheKey,
            [MappingCache::CACHE_TAG]
        );

        return $location;
    }

    /**
     * @throws GhnMappingNotFoundException
     */
    private function load(string $secommScheme, string $unitCode): GhnLocation
    {
        $mapping = $this->mappingResource->findApproved($secommScheme, $unitCode);
        if ($mapping === null) {
            throw new GhnMappingNotFoundException(
                __('No approved GHN mapping for canonical unit %1/%2.', $secommScheme, $unitCode)
            );
        }

        $unit = $this->unitResource->fetchUnit((int) $mapping['ghn_address_unit_id']);
        if ($unit === null || (string) $unit['status'] !== GhnSchemes::STATUS_ACTIVE) {
            throw new GhnMappingNotFoundException(
                __('GHN unit for canonical unit %1/%2 is missing or disabled.', $secommScheme, $unitCode)
            );
        }

        $location = $this->buildLocation($secommScheme, $unitCode, $unit);
        if ($this->requiresLegacyTriple($unit) && !$location->hasCompleteLegacyTriple()) {
            throw new GhnMappingNotFoundException(
                __('GHN unit for canonical unit %1/%2 does not yield a complete legacy address triple.', $secommScheme, $unitCode)
            );
        }

        return $location;
    }

    /**
     * @param array<string, mixed> $unit
     */
    private function buildLocation(string $secommScheme, string $unitCode, array $unit): GhnLocation
    {
        return match ((int) $unit['depth']) {
            1 => $this->buildProvince($secommScheme, $unitCode, $unit),
            2 => $this->buildDepthTwo($secommScheme, $unitCode, $unit),
            default => $this->buildWard($secommScheme, $unitCode, $unit),
        };
    }

    /**
     * @param array<string, mixed> $unit
     */
    private function buildProvince(string $secommScheme, string $unitCode, array $unit): GhnLocation
    {
        if ((string) $unit['scheme_code'] === GhnSchemes::GHN_ADMIN_2025) {
            return new GhnLocation($secommScheme, $unitCode, provinceName: (string) $unit['name']);
        }

        return new GhnLocation($secommScheme, $unitCode, provinceId: (string) ($unit['provider_id'] ?? ''), provinceName: (string) $unit['name']);
    }

    /**
     * Depth 2: district (PRE_2025) or ward (2025).
     *
     * @param array<string, mixed> $unit
     */
    private function buildDepthTwo(string $secommScheme, string $unitCode, array $unit): GhnLocation
    {
        $parent = $this->requireParent($unit);

        if ((string) $unit['scheme_code'] === GhnSchemes::GHN_ADMIN_2025) {
            // 2025 ward: verbatim names for is_new_to_address=true (DEC-FEATFQWEQ3-001).
            return new GhnLocation($secommScheme, $unitCode, provinceName: (string) $parent['name'], wardName: (string) $unit['name']);
        }

        return new GhnLocation(
            $secommScheme,
            $unitCode,
            provinceId: (string) ($parent['provider_id'] ?? ''),
            districtId: (string) ($unit['provider_id'] ?? ''),
            provinceName: (string) $parent['name'],
            wardName: (string) $unit['name']
        );
    }

    /**
     * Depth 3: legacy ward — walk district → province for the full operational triple.
     *
     * @param array<string, mixed> $unit
     */
    private function buildWard(string $secommScheme, string $unitCode, array $unit): GhnLocation
    {
        $district = $this->requireParent($unit);
        $province = $district['parent_id'] !== null
            ? $this->unitResource->fetchUnit((int) $district['parent_id'])
            : null;
        if ($province === null) {
            throw new GhnMappingNotFoundException(
                __('GHN ward "%1" has no resolvable province ancestor.', (string) $unit['provider_key'])
            );
        }

        return new GhnLocation(
            $secommScheme,
            $unitCode,
            provinceId: (string) ($province['provider_id'] ?? ''),
            districtId: (string) ($district['provider_id'] ?? ''),
            wardCode: (string) ($unit['provider_code'] ?? ''),
            provinceName: (string) $province['name'],
            wardName: (string) $unit['name']
        );
    }

    /**
     * @param array<string, mixed> $unit
     * @return array<string, mixed>
     * @throws GhnMappingNotFoundException
     */
    private function requireParent(array $unit): array
    {
        $parent = $unit['parent_id'] !== null
            ? $this->unitResource->fetchUnit((int) $unit['parent_id'])
            : null;
        if ($parent === null || (string) $parent['status'] !== GhnSchemes::STATUS_ACTIVE) {
            throw new GhnMappingNotFoundException(
                __('GHN unit "%1" has a missing or disabled parent.', (string) $unit['provider_key'])
            );
        }

        return $parent;
    }

    /**
     * Legacy-model units must resolve to the full district_id + ward_code operational triple.
     *
     * @param array<string, mixed> $unit
     */
    private function requiresLegacyTriple(array $unit): bool
    {
        return (string) $unit['scheme_code'] === GhnSchemes::GHN_ADMIN_PRE_2025
            && (int) $unit['depth'] === 3;
    }
}
