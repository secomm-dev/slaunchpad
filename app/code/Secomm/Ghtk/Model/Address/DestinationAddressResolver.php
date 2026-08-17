<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Address;

use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Api\Data\GhtkAddressMapInterface;
use Secomm\Ghtk\Model\GhtkAddressMapRepository;

/**
 * Maps a Magento address (country_id, region_id, ward_id) to GHTK names.
 *
 * Mapping-first: a hit on secomm_ghtk_address_map (active) returns exact GHTK names.
 * On a miss the resolver falls back to best-effort vi_VN names (DEC-020) so a valid
 * request can still be built — this does NOT guarantee GHTK recognition. Never throws;
 * on any failure it logs masked context and returns null so the caller degrades gracefully.
 *
 * Path B (DEC-020): when only a ward name is available (legacy payload), the stable
 * ward_id is recovered via WardIdBridge before lookup.
 */
class DestinationAddressResolver
{
    public const CACHE_TAG = 'secomm_ghtk_address_map';
    private const CACHE_PREFIX = 'ghtk_dest_';
    private const CACHE_TTL = 3600;

    public function __construct(
        private GhtkAddressMapRepository $repository,
        private WardIdBridge $wardIdBridge,
        private BestEffortViVnResolver $bestEffort,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @return GhtkAddress|null Null when nothing usable could be resolved (caller must handle gracefully).
     */
    public function resolve(
        string $countryId,
        int $regionId,
        ?int $wardId = null,
        ?string $wardName = null
    ): ?GhtkAddress {
        try {
            $effectiveWardId = $this->resolveEffectiveWardId($regionId, $wardId, $wardName);
            if ($effectiveWardId === null) {
                $this->logger->warning(
                    'GHTK destination resolve: no ward identifier available; cannot resolve.',
                    ['country_id' => $countryId, 'region_id' => $regionId]
                );

                return null;
            }

            $cacheId = self::CACHE_PREFIX . $countryId . '_' . $regionId . '_' . $effectiveWardId;
            $cached = $this->cache->load($cacheId);
            if ($cached !== false) {
                return $this->unserialize($cached);
            }

            $address = $this->resolveUncached($countryId, $regionId, $effectiveWardId);
            if ($address !== null) {
                $this->cache->save(
                    $this->serialize($address),
                    $cacheId,
                    [self::CACHE_TAG],
                    self::CACHE_TTL
                );
            }

            return $address;
        } catch (\Throwable $e) {
            // AC-5 / DEC-020: never throw out of the rate path.
            $this->logger->error(
                'GHTK destination resolve failed; returning null (graceful).',
                ['country_id' => $countryId, 'region_id' => $regionId, 'exception' => $e->getMessage()]
            );

            return null;
        }
    }

    private function resolveEffectiveWardId(int $regionId, ?int $wardId, ?string $wardName): ?int
    {
        if ($wardId !== null) {
            return $wardId;
        }

        return $wardName !== null && $wardName !== ''
            ? $this->wardIdBridge->resolveWardId($regionId, $wardName)
            : null;
    }

    private function resolveUncached(string $countryId, int $regionId, int $wardId): ?GhtkAddress
    {
        $row = $this->repository->findActive($countryId, $regionId, $wardId);
        if ($row !== null) {
            return new GhtkAddress(
                $row->getGhtkProvince(),
                $row->getGhtkDistrict(),
                $row->getGhtkWard(),
                true
            );
        }

        // Best-effort vi_VN fallback — GHTK may still reject this.
        $province = $this->bestEffort->getProvinceName($regionId);
        $ward = $this->bestEffort->getWardName($wardId);

        if ($province === null || $ward === null) {
            $this->logger->warning(
                'GHTK destination resolve: mapping miss and best-effort produced incomplete data.',
                ['country_id' => $countryId, 'region_id' => $regionId, 'ward_id' => $wardId]
            );

            return null;
        }

        $this->logger->warning(
            'GHTK destination resolve: mapping miss, using best-effort vi_VN (GHTK may reject).',
            ['country_id' => $countryId, 'region_id' => $regionId, 'ward_id' => $wardId]
        );

        return new GhtkAddress($province, null, $ward, false);
    }

    private function serialize(GhtkAddress $address): string
    {
        return (string) json_encode([
            'province' => $address->province,
            'district' => $address->district,
            'ward' => $address->ward,
            'isExact' => $address->isExact,
        ], JSON_THROW_ON_ERROR);
    }

    private function unserialize(string $payload): ?GhtkAddress
    {
        $data = json_decode($payload, true, 4, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            return null;
        }

        return new GhtkAddress(
            (string) ($data['province'] ?? ''),
            isset($data['district']) ? (string) $data['district'] : null,
            (string) ($data['ward'] ?? ''),
            (bool) ($data['isExact'] ?? false)
        );
    }
}
