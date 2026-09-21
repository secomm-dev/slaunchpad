<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — per-method capability + membership + city-code reader.
 * TASK-JZXM66 — City/Area self-service readers (region consistency, option lists, labels).
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model;

use Launchpad\MageplazaTableRate\Model\Data\MethodSettings;
use Magento\Framework\App\ResourceConnection;

/**
 * Single reader for the three Launchpad extension tables. Both tables are tiny (one row per
 * Mageplaza method / membership), so each map is loaded with ONE explicit-column query per
 * request and memoized on this shared instance — the checkout-critical plugin path never issues
 * N+1 queries.
 *
 * Missing settings row  = native Mageplaza behavior (show_to_customer=true, use_as_fallback=false).
 * Membership rows referencing an uninstalled/disabled carrier stay in the table but never
 * manufacture an outcome — the coordinator only looks up outcomes that Magento actually produced.
 */
class MethodSettingsProvider
{
    public const TABLE_SETTING = 'launchpad_mptablerate_method_setting';
    public const TABLE_MEMBER = 'launchpad_mptablerate_method_member';
    public const TABLE_RATE_CITY = 'launchpad_mptablerate_rate_city';

    /** TASK-JZXM66 — label of the always-present wildcard select entry (empty `city_code`). */
    public const WILDCARD_OPTION_LABEL = 'All / *';

    /** @var array<int, MethodSettings>|null keyed by method_id */
    private ?array $settings = null;

    /** @var array<int, array<int, array{carrier_code: string, method_code: string}>>|null */
    private ?array $members = null;

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return array<int, MethodSettings> all configured methods, keyed by method_id
     */
    public function getSettingsMap(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resourceConnection->getTableName(self::TABLE_SETTING), [
                    'method_id',
                    'show_to_customer',
                    'use_as_fallback',
                ])
        );

        $this->settings = [];
        foreach ($rows as $row) {
            $methodId = (int) $row['method_id'];
            $this->settings[$methodId] = new MethodSettings(
                $methodId,
                (bool) $row['show_to_customer'],
                (bool) $row['use_as_fallback']
            );
        }

        return $this->settings;
    }

    /**
     * @return array<int, int> method_id => method_id (fallback-capable groups)
     */
    public function getFallbackMethodIds(): array
    {
        $ids = [];
        foreach ($this->getSettingsMap() as $methodId => $settings) {
            if ($settings->isUseAsFallback()) {
                $ids[$methodId] = $methodId;
            }
        }

        return $ids;
    }

    /**
     * @return array<int, int> method_id => method_id (methods hidden from customers)
     */
    public function getHiddenMethodIds(): array
    {
        $ids = [];
        foreach ($this->getSettingsMap() as $methodId => $settings) {
            if (!$settings->isShowToCustomer()) {
                $ids[$methodId] = $methodId;
            }
        }

        return $ids;
    }

    /**
     * @return array<int, array<int, array{carrier_code: string, method_code: string}>>
     *         enabled members per method_id
     */
    public function getEnabledMembersMap(): array
    {
        if ($this->members !== null) {
            return $this->members;
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resourceConnection->getTableName(self::TABLE_MEMBER), [
                    'method_id',
                    'carrier_code',
                    'method_code',
                ])
                ->where('enabled = ?', 1)
        );

        $this->members = [];
        foreach ($rows as $row) {
            $this->members[(int) $row['method_id']][] = [
                'carrier_code' => (string) $row['carrier_code'],
                'method_code' => (string) $row['method_code'],
            ];
        }

        return $this->members;
    }

    /**
     * City codes for the given Mageplaza rate rows — only rows with a constraint are returned
     * (absence = wildcard/legacy row). No memoization on purpose: the map is bounded by the
     * matched rows of the current collection and must reflect fresh data.
     *
     * @param array<int, int> $rateIds
     * @return array<int, string> rate_id => city_code
     */
    public function fetchCityCodes(array $rateIds): array
    {
        if ($rateIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($this->resourceConnection->getTableName(self::TABLE_RATE_CITY), ['rate_id', 'city_code'])
                ->where('rate_id IN (?)', array_map('intval', array_values($rateIds)))
        );

        $map = [];
        foreach ($rows as $rateId => $cityCode) {
            $map[(int) $rateId] = (string) $cityCode;
        }

        return $map;
    }

    /**
     * Validates a stable address-node code against the live address hierarchy
     * (`directory_region_city.code` — the generic city-node identity). Unknown codes never
     * silently create address units (directive §16).
     */
    public function cityCodeExists(string $cityCode): bool
    {
        if ($cityCode === '') {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();

        return (bool) $connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('directory_region_city'), ['COUNT(*)'])
                ->where('code = ?', $cityCode)
        );
    }

    /**
     * TASK-JZXM66 — region a stable city/area code belongs to (deterministic on the
     * lowest city_id when a code is present under more than one region). Null = unknown code.
     */
    public function cityRegionId(string $cityCode): ?int
    {
        $value = $this->fetchCityColumn($cityCode, 'region_id');

        return $value === null ? null : (int) $value;
    }

    /**
     * TASK-JZXM66 — display label (default_name) for a stable city/area code. Null = unknown.
     */
    public function cityLabel(string $cityCode): ?string
    {
        return $this->fetchCityColumn($cityCode, 'default_name');
    }

    /**
     * TASK-JZXM66 — region-consistency guard: the city/area code must live under the region
     * the rate row is being saved with.
     */
    public function cityBelongsToRegion(string $cityCode, int $regionId): bool
    {
        $cityRegionId = $this->cityRegionId($cityCode);

        return $cityRegionId !== null && $cityRegionId === $regionId;
    }

    /**
     * TASK-JZXM66 — City/Area select options for one region. ALWAYS starts with the wildcard
     * entry (empty code = all areas), then the region's coded nodes ordered by display name.
     *
     * @return array<int, array{code: string, label: string}>
     */
    public function fetchCityOptionsByRegion(int $regionId): array
    {
        $options = [$this->wildcardOption()];
        if ($regionId <= 0) {
            return $options;
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resourceConnection->getTableName('directory_region_city'), ['code', 'default_name'])
                ->where('region_id = ?', $regionId)
                ->where('code IS NOT NULL')
                ->where('code <> ?', '')
                ->order('default_name ASC')
        );
        foreach ($rows as $row) {
            $options[] = ['code' => (string) $row['code'], 'label' => (string) $row['default_name']];
        }

        return $options;
    }

    /**
     * TASK-JZXM66 — the wildcard select entry (empty `city_code` = all areas).
     *
     * @return array{code: string, label: string}
     */
    public function wildcardOption(): array
    {
        return ['code' => '', 'label' => self::WILDCARD_OPTION_LABEL];
    }

    private function fetchCityColumn(string $cityCode, string $column): ?string
    {
        if ($cityCode === '') {
            return null;
        }

        $connection = $this->resourceConnection->getConnection();
        $value = $connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('directory_region_city'), [$column])
                ->where('code = ?', $cityCode)
                ->order('city_id ASC')
                ->limit(1)
        );

        return $value === false || $value === null ? null : (string) $value;
    }
}
