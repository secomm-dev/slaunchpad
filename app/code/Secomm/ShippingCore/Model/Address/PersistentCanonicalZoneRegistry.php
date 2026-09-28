<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRegistryInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRepositoryInterface;
use Secomm\ShippingCore\Cache\Type\Zone as ZoneCacheType;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — DB-backed canonical zone registry with deterministic
 * source precedence: a PERSISTED zone is authoritative for its code; a DI/static zone is a
 * fallback/bootstrap source used only when no persisted zone carries the same code
 * (SPEC §3.2). Lazy-loading: nothing touches the repository or the cache until the first
 * registry read (DI construction stays DB-free).
 *
 * Duplicate zone codes WITHIN one source fail fast (constructor for DI zones — unchanged
 * semantics; at load time for persisted rows — a direct-SQL data defect, never a normal CRUD
 * state). Duplicate codes ACROSS sources are the designed precedence case, never an error.
 * Zero zones in both sources remains a valid state (architecture §35.2).
 */
final class PersistentCanonicalZoneRegistry implements CanonicalZoneRegistryInterface
{
    private const CACHE_KEY = 'secomm_shippingcore_zones_all';

    /** @var CanonicalZoneInterface[] */
    private array $diZones;

    private CanonicalZoneRepositoryInterface $repository;

    private ZoneCacheType $cache;

    /** @var CanonicalZoneInterface[]|null lazily merged zones, keyed array in registration order */
    private ?array $mergedZones = null;

    /** @var array<string, CanonicalZoneInterface> */
    private array $mergedByCode = [];

    public function __construct(
        array $canonicalZones,
        CanonicalZoneRepositoryInterface $repository,
        ZoneCacheType $cache
    ) {
        $seen = [];
        foreach ($canonicalZones as $zone) {
            if (in_array($zone->getCode(), $seen, true)) {
                throw new \LogicException(sprintf('Duplicate canonical zone code "%s".', $zone->getCode()));
            }
            $seen[] = $zone->getCode();
        }
        $this->diZones = array_values($canonicalZones);
        $this->repository = $repository;
        $this->cache = $cache;
    }

    public function getByCode(string $zoneCode): ?CanonicalZoneInterface
    {
        $this->load();

        return $this->mergedByCode[$zoneCode] ?? null;
    }

    public function getAll(): array
    {
        $this->load();

        return $this->mergedZones;
    }

    public function getEnabled(): array
    {
        return array_values(array_filter($this->getAll(), static function (CanonicalZoneInterface $zone): bool {
            return $zone->isEnabled();
        }));
    }

    /**
     * Cache → repository. The cache payload is a plain scalar array (never object
     * serialization — readonly VOs must not rely on unserialize initialization).
     */
    private function load(): void
    {
        if ($this->mergedZones !== null) {
            return;
        }
        $persisted = $this->loadPersistedZones();
        $merged = [];
        $persistedCodes = [];
        foreach ($persisted as $zone) {
            $persistedCodes[$zone->getCode()] = true;
            $merged[] = $zone;
        }
        foreach ($this->diZones as $zone) {
            if (!isset($persistedCodes[$zone->getCode()])) {
                $merged[] = $zone; // DI fallback: only for codes with no persisted authority
            }
        }
        $this->mergedZones = $merged;
        foreach ($merged as $zone) {
            if (isset($this->mergedByCode[$zone->getCode()])) {
                throw new \LogicException(sprintf('Duplicate canonical zone code "%s".', $zone->getCode()));
            }
            $this->mergedByCode[$zone->getCode()] = $zone;
        }
    }

    /** @return CanonicalZoneInterface[] */
    private function loadPersistedZones(): array
    {
        $cached = $this->cache->load(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            $rows = unserialize($cached, ['allowed_classes' => false]);
            if (is_array($rows)) {
                return $this->zonesFromRows($rows);
            }
        }
        $zones = $this->repository->getAll();
        $this->cache->save(
            serialize($this->rowsFromZones($zones)),
            self::CACHE_KEY,
            [ZoneCacheType::TYPE_ID]
        );

        return $zones;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return CanonicalZoneInterface[]
     */
    private function zonesFromRows(array $rows): array
    {
        $zones = [];
        foreach ($rows as $row) {
            if (!isset($row['code'], $row['label'])) {
                continue;
            }
            $zones[] = new CanonicalZone(
                (string) $row['code'],
                (string) $row['label'],
                (bool) ($row['enabled'] ?? true),
                array_values(array_map('strval', (array) ($row['include_province_codes'] ?? []))),
                array_values(array_map('strval', (array) ($row['include_ward_codes'] ?? []))),
                array_values(array_map('strval', (array) ($row['exclude_ward_codes'] ?? [])))
            );
        }

        return $zones;
    }

    /**
     * @param CanonicalZoneInterface[] $zones
     * @return array<int, array<string, mixed>>
     */
    private function rowsFromZones(array $zones): array
    {
        $rows = [];
        foreach ($zones as $zone) {
            $rows[] = [
                'code' => $zone->getCode(),
                'label' => $zone->getLabel(),
                'enabled' => $zone->isEnabled(),
                'include_province_codes' => $zone->getIncludeProvinceCodes(),
                'include_ward_codes' => $zone->getIncludeWardCodes(),
                'exclude_ward_codes' => $zone->getExcludeWardCodes(),
            ];
        }

        return $rows;
    }
}
