<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Locale\ResolverInterface;
use Secomm\AddressDropdown\Api\Data\LocationNodeInterface;
use Secomm\AddressDropdown\Api\LocationHierarchyProviderInterface;
use Secomm\AddressDropdown\Model\Data\LocationNodeData;
use Zend_Db_Expr;

/**
 * FEAT-2PZQKJ / TASK-J49PRZ — recursive-hierarchy traversal on directory_region_city with
 * Address Profile membership filtering (DEC-FEAT2PZQKJ-001 D5: subtree-claim + inheritance;
 * profile without membership rows = all-nodes BC mode).
 *
 * Query shape: one listing statement per call (children + locale name + EXISTS has_children);
 * membership decisions use at most a bounded ancestor walk (one indexed lookup per level,
 * guarded by MAX_WALK_DEPTH) plus one IN-lookup for claimed child ids. No N+1 over result rows.
 */
class LocationHierarchyProvider implements LocationHierarchyProviderInterface
{
    private const TYPE_REGION = 'region';
    private const TYPE_CITY = 'city';
    private const MAX_WALK_DEPTH = 16;

    /**
     * Per-request memo: profile_code => has any membership rows.
     *
     * @var array<string, bool>
     */
    private array $profileHasMembership = [];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ResolverInterface $localeResolver
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getRootLocations(int $regionId, string $profileCode): array
    {
        $rows = $this->fetchChildren($regionId, null);
        $grantsSubtree = !$this->profileHasMembership($profileCode)
            || $this->regionClaimGrantsSubtree($profileCode, $regionId);

        return $this->hydrateList($this->filterByMembership($rows, $profileCode, $grantsSubtree), 1);
    }

    /**
     * @inheritDoc
     */
    public function getChildLocations(int $parentCityId, string $profileCode): array
    {
        $parent = $this->fetchLight($parentCityId);
        if ($parent === null) {
            return [];
        }

        $parentDepth = count($this->getLocationPath($parentCityId));
        $rows = $this->fetchChildren((int)$parent['region_id'], $parentCityId);
        $grantsSubtree = !$this->profileHasMembership($profileCode)
            || $this->nearestClaimGrantsSubtree($profileCode, $parentCityId);

        return $this->hydrateList($this->filterByMembership($rows, $profileCode, $grantsSubtree), $parentDepth + 1);
    }

    /**
     * @inheritDoc
     */
    public function hasChildren(int $cityId, string $profileCode): bool
    {
        if ($this->fetchLight($cityId) === null) {
            return false;
        }

        $connection = $this->connection();
        if (!$this->profileHasMembership($profileCode)
            || $this->nearestClaimGrantsSubtree($profileCode, $cityId)
        ) {
            $select = $connection->select()
                ->from($this->table('directory_region_city'), [new Zend_Db_Expr('1')])
                ->where('parent_city_id = ?', $cityId)
                ->limit(1);

            return (bool)$connection->fetchOne($select);
        }

        // Children qualify only via their own claim entries.
        $select = $connection->select()
            ->from(['c' => $this->table('directory_region_city')], [new Zend_Db_Expr('1')])
            ->join(
                ['m' => $this->table('secomm_address_profile_location')],
                'm.location_id = c.city_id',
                []
            )
            ->where('c.parent_city_id = ?', $cityId)
            ->where('m.profile_code = ?', $profileCode)
            ->where('m.location_type = ?', self::TYPE_CITY)
            ->limit(1);

        return (bool)$connection->fetchOne($select);
    }

    /**
     * @inheritDoc
     */
    public function getLocationPath(int $cityId): array
    {
        $chain = [];
        $currentId = $cityId;
        $visited = [];
        while ($currentId !== null) {
            if (isset($visited[$currentId]) || count($visited) >= self::MAX_WALK_DEPTH) {
                return []; // cycle / runaway guard — no partial paths
            }
            $visited[$currentId] = true;

            $node = $this->fetchNode((int)$currentId);
            if ($node === null) {
                return []; // broken chain or unknown id
            }
            array_unshift($chain, $node);
            $currentId = $node['parent_city_id'] === null ? null : (int)$node['parent_city_id'];
        }

        return $this->hydratePath($chain);
    }

    // ------------------------------------------------------------------ membership helpers

    /**
     * A profile that declares no membership rows at all is in all-nodes (BC) mode.
     */
    private function profileHasMembership(string $profileCode): bool
    {
        if (!isset($this->profileHasMembership[$profileCode])) {
            $select = $this->connection()->select()
                ->from($this->table('secomm_address_profile_location'), [new Zend_Db_Expr('1')])
                ->where('profile_code = ?', $profileCode)
                ->limit(1);
            $this->profileHasMembership[$profileCode] = (bool)$this->connection()->fetchOne($select);
        }

        return $this->profileHasMembership[$profileCode];
    }

    /**
     * include_subtree of the region claim; false when the region carries no entry.
     */
    private function regionClaimGrantsSubtree(string $profileCode, int $regionId): bool
    {
        return $this->membershipEntry($profileCode, self::TYPE_REGION, $regionId) === true;
    }

    /**
     * Nearest-entry walk up the ancestor chain (city chain, then region claim): the closest
     * claim decides whether the subtree below the given node qualifies.
     */
    private function nearestClaimGrantsSubtree(string $profileCode, int $cityId): bool
    {
        $currentId = $cityId;
        $steps = 0;
        $lastRegionId = null;
        while ($currentId !== null && $steps < self::MAX_WALK_DEPTH) {
            $steps++;
            $node = $this->fetchLight((int)$currentId);
            if ($node === null) {
                return false;
            }
            $lastRegionId = (int)$node['region_id'];

            $entry = $this->membershipEntry($profileCode, self::TYPE_CITY, (int)$currentId);
            if ($entry !== null) {
                return $entry;
            }
            $currentId = $node['parent_city_id'] === null ? null : (int)$node['parent_city_id'];
        }

        // Reached a root city (or exhausted the walk at a root) — the region claim is nearest.
        return $lastRegionId !== null && $this->regionClaimGrantsSubtree($profileCode, $lastRegionId);
    }

    /**
     * @return bool|null include_subtree value, or null when no entry exists
     */
    private function membershipEntry(string $profileCode, string $type, int $locationId): ?bool
    {
        $select = $this->connection()->select()
            ->from($this->table('secomm_address_profile_location'), ['include_subtree'])
            ->where('profile_code = ?', $profileCode)
            ->where('location_type = ?', $type)
            ->where('location_id = ?', $locationId);

        $value = $this->connection()->fetchOne($select);

        return $value === false ? null : (bool)$value;
    }

    /**
     * Keep rows qualifying for the profile: all when the parent grants subtree (or all-nodes
     * BC mode); otherwise only rows carrying their own claim entry.
     */
    private function filterByMembership(array $rows, string $profileCode, bool $grantsSubtree): array
    {
        if ($grantsSubtree) {
            return $rows;
        }

        $ids = array_map(static fn (array $row): int => (int)$row['city_id'], $rows);
        if (empty($ids)) {
            return [];
        }

        $select = $this->connection()->select()
            ->from($this->table('secomm_address_profile_location'), ['location_id'])
            ->where('profile_code = ?', $profileCode)
            ->where('location_type = ?', self::TYPE_CITY)
            ->where('location_id IN (?)', $ids);
        $claimed = array_flip(array_map('intval', $this->connection()->fetchCol($select)));

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => isset($claimed[(int)$row['city_id']])
        ));
    }

    // ------------------------------------------------------------------ data helpers

    /**
     * Direct children of a region (null parent) or of a city — single statement with locale
     * name + structural has_children via EXISTS. Fully parameterized (bind params).
     */
    private function fetchChildren(int $regionId, ?int $parentCityId): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from(
                ['c' => $this->table('directory_region_city')],
                [
                    'city_id' => 'c.city_id',
                    'default_name' => 'c.default_name',
                    'region_id' => 'c.region_id',
                    'parent_city_id' => 'c.parent_city_id',
                    'name' => new Zend_Db_Expr('COALESCE(n.name, c.default_name)'),
                    'has_children' => new Zend_Db_Expr(
                        'EXISTS(SELECT 1 FROM ' . $this->table('directory_region_city')
                        . ' cc WHERE cc.parent_city_id = c.city_id)'
                    ),
                ]
            )
            ->joinLeft(
                ['n' => $this->table('directory_region_city_name')],
                'n.city_id = c.city_id AND ' . $connection->quoteInto('n.locale = ?', $this->localeResolver->getLocale()),
                []
            )
            ->where('c.region_id = ?', $regionId)
            ->where($parentCityId === null
                ? new Zend_Db_Expr('c.parent_city_id IS NULL')
                : $connection->quoteInto('c.parent_city_id = ?', $parentCityId))
            // Canonical generic sort (TASK-7HVGAB): effective localized display name with
            // default_name fallback, city_id as deterministic tie-breaker. This module is
            // language-agnostic — no locale-specific normalization here; ordering quality is
            // owned by the column collation (schema baseline) and, if ever needed, by a
            // locale-specific sorter in the locale module (e.g. Secomm_VietNamAddress).
            // Mirrors CityLocaleCollection::_initSelect.
            ->order(new Zend_Db_Expr('COALESCE(n.name, c.default_name) ASC, c.city_id ASC'));

        return $connection->fetchAll($select);
    }

    /**
     * @return array<string, mixed>|null node row with locale-resolved name
     */
    private function fetchNode(int $cityId): ?array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from(
                ['c' => $this->table('directory_region_city')],
                [
                    'city_id' => 'c.city_id',
                    'default_name' => 'c.default_name',
                    'region_id' => 'c.region_id',
                    'parent_city_id' => 'c.parent_city_id',
                    'name' => new Zend_Db_Expr('COALESCE(n.name, c.default_name)'),
                ]
            )
            ->joinLeft(
                ['n' => $this->table('directory_region_city_name')],
                'n.city_id = c.city_id AND ' . $connection->quoteInto('n.locale = ?', $this->localeResolver->getLocale()),
                []
            )
            ->where('c.city_id = ?', $cityId);
        $row = $connection->fetchRow($select);

        return $row === false ? null : $row;
    }

    /**
     * Light fetch for ancestor walks (PK lookup, no name join).
     *
     * @return array<string, mixed>|null
     */
    private function fetchLight(int $cityId): ?array
    {
        $row = $this->connection()->fetchRow(
            $this->connection()->select()
                ->from($this->table('directory_region_city'), ['city_id', 'region_id', 'parent_city_id'])
                ->where('city_id = ?', $cityId)
        );

        return $row === false ? null : $row;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param int $depth depth assigned to every node in the list
     * @return LocationNodeInterface[]
     */
    private function hydrateList(array $rows, int $depth): array
    {
        return array_map(
            fn (array $row): LocationNodeInterface => $this->hydrateNode($row, $depth),
            $rows
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows root-first chain; depth = position + 1
     * @return LocationNodeInterface[]
     */
    private function hydratePath(array $rows): array
    {
        return array_map(
            fn (array $row, int $index): LocationNodeInterface => $this->hydrateNode($row, $index + 1),
            array_values($rows),
            array_keys(array_values($rows))
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateNode(array $row, int $depth): LocationNodeInterface
    {
        return new LocationNodeData([
            LocationNodeInterface::CITY_ID => (int)$row['city_id'],
            LocationNodeInterface::DEFAULT_NAME => (string)$row['default_name'],
            LocationNodeInterface::NAME => (string)($row['name'] ?? $row['default_name']),
            LocationNodeInterface::DEPTH => $depth,
            LocationNodeInterface::PARENT_CITY_ID => $row['parent_city_id'] === null
                ? null
                : (int)$row['parent_city_id'],
            LocationNodeInterface::REGION_ID => (int)$row['region_id'],
            LocationNodeInterface::HAS_CHILDREN => !empty($row['has_children']),
        ]);
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
