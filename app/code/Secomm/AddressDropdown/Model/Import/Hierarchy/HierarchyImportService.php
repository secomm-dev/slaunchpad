<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Import\Hierarchy;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Secomm\AddressDropdown\Api\HierarchyAddressImportInterface;
use Zend_Db_Expr;

/**
 * FEAT-2PZQKJ (core pulled forward into TASK-ADT94K) — code-identified upserts for the
 * recursive address hierarchy. See HierarchyAddressImportInterface for the row contract.
 *
 * Order independence: regions are upserted first, then city nodes are resolved level by
 * level through a worklist (batch parents via the code map, pre-existing DB parents via a
 * bounded lookup) — physical row order never changes the resulting hierarchy.
 *
 * Depth-1 lookups are explicit application-side selects: the composite unique index
 * (region_id, parent_city_id, code) does not enforce uniqueness while parent_city_id is NULL.
 */
class HierarchyImportService implements HierarchyAddressImportInterface
{
    private const TABLE_REGION = 'directory_country_region';
    private const TABLE_REGION_NAME = 'directory_country_region_name';
    private const TABLE_CITY = 'directory_region_city';
    private const TABLE_CITY_NAME = 'directory_region_city_name';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validate(string $countryId, array $rows): HierarchyImportResult
    {
        $result = new HierarchyImportResult();
        $normalized = $this->normalizeRows($rows, $result);

        if (!$result->hasErrors()) {
            $this->checkReferences($countryId, $normalized, $result);
        }

        $result->setRowsValidated(count($normalized));

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function import(string $countryId, array $rows): HierarchyImportResult
    {
        $result = $this->validate($countryId, $rows);
        if ($result->hasErrors()) {
            throw new HierarchyImportValidationException(
                __('Hierarchy address import validation failed with %1 error(s).', count($result->getErrors())),
                $result
            );
        }

        // Re-run the (now error-free) normalisation to obtain the working row set.
        $normalized = $this->normalizeRows($rows, new HierarchyImportResult());
        $this->runImport($countryId, $normalized, $result);

        return $result;
    }

    // ------------------------------------------------------------------ normalisation

    /**
     * Field-level checks; invalid rows are reported and dropped from the working set.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(array $rows, HierarchyImportResult $result): array
    {
        $normalized = [];
        $seen = [self::ENTITY_TYPE_REGION => [], self::ENTITY_TYPE_CITY => []];

        foreach (array_values($rows) as $index => $row) {
            $entityType = (string)($row['entity_type'] ?? '');
            $code = trim((string)($row['code'] ?? ''));
            $defaultName = trim((string)($row['default_name'] ?? ''));
            $parentCode = trim((string)($row['parent_code'] ?? ''));
            $regionCode = trim((string)($row['region_code'] ?? ''));
            $names = $this->normalizeNames($row['names'] ?? null);

            if ($entityType !== self::ENTITY_TYPE_REGION && $entityType !== self::ENTITY_TYPE_CITY) {
                $result->addError($index, $code, sprintf('Unknown entity_type "%s".', $entityType));
                continue;
            }
            if ($code === '') {
                $result->addError($index, $code, 'Missing code (the stable identifier).');
                continue;
            }
            if (strlen($code) > HierarchyAddressImportInterface::CODE_COLUMN_LIMIT) {
                $result->addError($index, $code, sprintf('Code longer than %d characters.', HierarchyAddressImportInterface::CODE_COLUMN_LIMIT));
                continue;
            }
            if ($defaultName === '') {
                $result->addError($index, $code, 'Missing default_name.');
                continue;
            }
            if ($names === []) {
                $result->addError($index, $code, 'Missing names (at least one locale required).');
                continue;
            }
            if ($entityType === self::ENTITY_TYPE_REGION && $parentCode !== '') {
                $result->addError($index, $code, 'Region rows must not carry parent_code.');
                continue;
            }
            if ($entityType === self::ENTITY_TYPE_CITY && $regionCode === '') {
                $result->addError($index, $code, 'City rows require region_code.');
                continue;
            }
            if (isset($seen[$entityType][$code])) {
                $result->addError(
                    $index,
                    $code,
                    sprintf('Duplicate code (first seen at row %d).', $seen[$entityType][$code])
                );
                continue;
            }

            $seen[$entityType][$code] = $index;
            $normalized[] = [
                'index' => $index,
                'entity_type' => $entityType,
                'code' => $code,
                'region_code' => $regionCode,
                'parent_code' => $parentCode,
                'default_name' => $defaultName,
                'names' => $names,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $names
     * @return array<string, string>
     */
    private function normalizeNames(mixed $names): array
    {
        if (!is_array($names)) {
            return [];
        }

        $clean = [];
        foreach ($names as $locale => $name) {
            $locale = trim((string)$locale);
            $name = trim((string)$name);
            if ($locale !== '' && $name !== '') {
                $clean[$locale] = $name;
            }
        }

        return $clean;
    }

    // ------------------------------------------------------------------ reference checks

    /**
     * Cross-row + DB-state checks: region existence, parent resolution (same region),
     * depth bound. Only runs on fully normalised rows.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function checkReferences(string $countryId, array $rows, HierarchyImportResult $result): void
    {
        $cityIndex = [];
        $regionCodes = [];
        foreach ($rows as $row) {
            if ($row['entity_type'] === self::ENTITY_TYPE_CITY) {
                $cityIndex[$row['code']] = $row;
            } else {
                $regionCodes[$row['code']] = true;
            }
        }

        $dbRegionCache = [];
        $depthMemo = [];
        foreach ($rows as $row) {
            if ($row['entity_type'] !== self::ENTITY_TYPE_CITY) {
                continue;
            }

            if (!$this->regionExists($countryId, $row['region_code'], $regionCodes, $dbRegionCache)) {
                $result->addError(
                    $row['index'],
                    $row['code'],
                    sprintf('Unknown region_code "%s" (not in batch, not in DB).', $row['region_code'])
                );
                continue;
            }

            if ($row['parent_code'] === '') {
                continue; // depth-1: nothing further to resolve
            }

            $depth = $this->resolveDepth($countryId, $row, $cityIndex, $depthMemo, $result);
            if ($depth !== null && $depth > self::MAX_DEPTH) {
                $result->addError(
                    $row['index'],
                    $row['code'],
                    sprintf('Depth %d exceeds the maximum of %d.', $depth, self::MAX_DEPTH)
                );
            }
        }
    }

    /**
     * Region exists among the batch region codes or in the DB for the country.
     *
     * @param array<string, bool> $regionCodes batch index (code => true)
     * @param array<string, bool> $dbRegionCache resolved DB lookups (code => exists)
     */
    private function regionExists(string $countryId, string $regionCode, array $regionCodes, array &$dbRegionCache): bool
    {
        if (isset($regionCodes[$regionCode])) {
            return true;
        }
        if (isset($dbRegionCache[$regionCode])) {
            return $dbRegionCache[$regionCode];
        }

        $select = $this->connection()->select()
            ->from($this->table(self::TABLE_REGION), [new Zend_Db_Expr('1')])
            ->where('country_id = ?', $countryId)
            ->where('code = ?', $regionCode)
            ->limit(1);
        $dbRegionCache[$regionCode] = (bool)$this->connection()->fetchOne($select);

        return $dbRegionCache[$regionCode];
    }

    /**
     * Depth of a city row via its parent chain: batch parents recursively (cycle-guarded,
     * memoised), DB parents through a bounded ancestor walk. Reports cross-region parents
     * and unresolved/cyclic chains on the result and returns null for those rows.
     *
     * @param array<string, array<string, mixed>> $cityIndex
     * @param array<string, int> $depthMemo
     */
    private function resolveDepth(
        string $countryId,
        array $row,
        array $cityIndex,
        array &$depthMemo,
        HierarchyImportResult $result
    ): ?int {
        $chain = []; // codes in walk order: row, parent, grandparent, ...
        $current = $row;
        $terminalDepth = null;

        while (true) {
            if (isset($depthMemo[$current['code']])) {
                $terminalDepth = $depthMemo[$current['code']];
                break;
            }
            if (isset($chain[$current['code']])) {
                $result->addError($row['index'], $row['code'], 'Cyclic parent_code chain.');
                return null;
            }
            $chain[$current['code']] = true;

            $parentCode = $current['parent_code'];
            if ($parentCode === '') {
                $terminalDepth = 1; // $current is a root city
                break;
            }

            $parent = $cityIndex[$parentCode] ?? null;
            if ($parent === null) {
                // Parent not in batch: fall back to a pre-existing DB city within the same region.
                $parentDepth = $this->dbParentDepth($countryId, $current['region_code'], $parentCode);
                if ($parentDepth === null) {
                    $result->addError(
                        $row['index'],
                        $row['code'],
                        sprintf('Unresolved parent_code "%s" (not in batch, not in DB region).', $parentCode)
                    );
                    return null;
                }
                $terminalDepth = $parentDepth + 1;
                break;
            }
            if ($parent['region_code'] !== $current['region_code']) {
                $result->addError(
                    $row['index'],
                    $row['code'],
                    sprintf('parent_code "%s" belongs to another region ("%s").', $parentCode, $parent['region_code'])
                );
                return null;
            }
            $current = $parent;
        }

        // Backfill depths from the terminal node toward the row (child = parent depth + 1).
        $chainCodes = array_keys($chain);
        $depth = $terminalDepth;
        for ($i = count($chainCodes) - 1; $i >= 0; $i--) {
            $depthMemo[$chainCodes[$i]] = $depth;
            $depth++;
        }

        return $depthMemo[$row['code']];
    }

    // ------------------------------------------------------------------ import

    /**
     * @param array<int, array<string, mixed>> $rows normalised rows
     */
    private function runImport(string $countryId, array $rows, HierarchyImportResult $result): void
    {
        $regionRows = [];
        $citiesByRegion = [];
        foreach ($rows as $row) {
            if ($row['entity_type'] === self::ENTITY_TYPE_REGION) {
                $regionRows[$row['code']] = $row;
            } else {
                $citiesByRegion[$row['region_code']][] = $row;
            }
        }

        // Regions declared in the batch first (deterministic code order), then DB-only regions.
        // strval: PHP casts numeric-string array keys ('32') to int — keep codes canonical strings.
        $regionCodes = array_map('strval', array_unique(array_merge(array_keys($regionRows), array_keys($citiesByRegion))));
        sort($regionCodes);

        foreach ($regionCodes as $regionCode) {
            $this->importRegionChunk(
                $countryId,
                $regionCode,
                $regionRows[$regionCode] ?? null,
                $citiesByRegion[$regionCode] ?? [],
                $result
            );
        }
    }

    /**
     * One transactional chunk: the region upsert + all of its city nodes.
     *
     * @param array<int, array<string, mixed>> $cityRows
     */
    private function importRegionChunk(
        string $countryId,
        string $regionCode,
        ?array $regionRow,
        array $cityRows,
        HierarchyImportResult $result
    ): void {
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $regionId = $regionRow !== null
                ? $this->upsertRegion($countryId, $regionRow, $result)
                : $this->fetchRegionId($countryId, $regionCode);

            if ($regionId !== null) {
                $this->importCityRows((int)$regionId, $cityRows, $result);
            }
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Worklist passes: a row is written once its parent is resolved (batch map or DB),
     * making the outcome independent of input order.
     *
     * @param array<int, array<string, mixed>> $cityRows
     */
    private function importCityRows(int $regionId, array $cityRows, HierarchyImportResult $result): void
    {
        $resolved = []; // code => city_id
        $pending = array_values($cityRows);

        while ($pending !== []) {
            $next = [];
            $progress = false;

            foreach ($pending as $row) {
                $parentCode = $row['parent_code'];
                if ($parentCode === '') {
                    $resolved[$row['code']] = $this->upsertCity($regionId, null, $row, $result);
                    $progress = true;
                    continue;
                }
                if (isset($resolved[$parentCode])) {
                    $resolved[$row['code']] = $this->upsertCity($regionId, (int)$resolved[$parentCode], $row, $result);
                    $progress = true;
                    continue;
                }
                $dbParentId = $this->fetchCityIdByCode($regionId, $parentCode);
                if ($dbParentId !== null) {
                    $resolved[$parentCode] = $dbParentId;
                    $resolved[$row['code']] = $this->upsertCity($regionId, $dbParentId, $row, $result);
                    $progress = true;
                    continue;
                }
                $next[] = $row;
            }

            if (!$progress && $next !== []) {
                // Unreachable after successful validation; guards against infinite loops.
                throw new \LogicException('Hierarchy city import stalled on unresolved parents.');
            }
            $pending = $next;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function upsertRegion(string $countryId, array $row, HierarchyImportResult $result): int
    {
        $connection = $this->connection();
        $regionId = $this->fetchRegionId($countryId, $row['code']);

        if ($regionId !== null) {
            $connection->update(
                $this->table(self::TABLE_REGION),
                ['default_name' => $row['default_name']],
                ['region_id = ?' => $regionId]
            );
            $result->countRegionUpdated();
        } else {
            $connection->insert(
                $this->table(self::TABLE_REGION),
                [
                    'country_id' => $countryId,
                    'code' => $row['code'],
                    'default_name' => $row['default_name'],
                    'is_default' => 0,
                ]
            );
            $regionId = (int)$connection->lastInsertId($this->table(self::TABLE_REGION));
            $result->countRegionInserted();
        }

        // Locale rows: every supplied locale plus en_US mirroring default_name
        // (matches the existing region-name pattern and refreshes stale English names).
        $nameRows = [];
        foreach ($row['names'] as $locale => $name) {
            $nameRows[] = ['locale' => $locale, 'region_id' => $regionId, 'name' => $name];
        }
        if (!isset($row['names']['en_US'])) {
            $nameRows[] = ['locale' => 'en_US', 'region_id' => $regionId, 'name' => $row['default_name']];
        }
        $connection->insertOnDuplicate($this->table(self::TABLE_REGION_NAME), $nameRows, ['name']);

        return $regionId;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function upsertCity(int $regionId, ?int $parentCityId, array $row, HierarchyImportResult $result): int
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->table(self::TABLE_CITY), ['city_id'])
            ->where('region_id = ?', $regionId)
            ->where('code = ?', $row['code'])
            ->where($parentCityId === null
                ? new Zend_Db_Expr('parent_city_id IS NULL')
                : $connection->quoteInto('parent_city_id = ?', $parentCityId))
            ->limit(1);
        $cityId = $connection->fetchOne($select);

        if ($cityId) {
            $connection->update(
                $this->table(self::TABLE_CITY),
                ['default_name' => $row['default_name']],
                ['city_id = ?' => (int)$cityId]
            );
            $result->countCityUpdated();
            $cityId = (int)$cityId;
        } else {
            $connection->insert(
                $this->table(self::TABLE_CITY),
                [
                    'region_id' => $regionId,
                    'parent_city_id' => $parentCityId,
                    'code' => $row['code'],
                    'default_name' => $row['default_name'],
                ]
            );
            $cityId = (int)$connection->lastInsertId($this->table(self::TABLE_CITY));
            $result->countCityInserted();
        }

        $nameRows = [];
        foreach ($row['names'] as $locale => $name) {
            $nameRows[] = ['city_id' => $cityId, 'locale' => $locale, 'name' => $name];
        }
        if ($nameRows !== []) {
            $connection->insertOnDuplicate($this->table(self::TABLE_CITY_NAME), $nameRows, ['name']);
        }

        return $cityId;
    }

    // ------------------------------------------------------------------ db helpers

    private function fetchRegionId(string $countryId, string $regionCode): ?int
    {
        $select = $this->connection()->select()
            ->from($this->table(self::TABLE_REGION), ['region_id'])
            ->where('country_id = ?', $countryId)
            ->where('code = ?', $regionCode)
            ->limit(1);
        $regionId = $this->connection()->fetchOne($select);

        return $regionId ? (int)$regionId : null;
    }

    private function fetchCityIdByCode(int $regionId, string $code): ?int
    {
        $select = $this->connection()->select()
            ->from($this->table(self::TABLE_CITY), ['city_id'])
            ->where('region_id = ?', $regionId)
            ->where('code = ?', $code)
            ->limit(1);
        $cityId = $this->connection()->fetchOne($select);

        return $cityId ? (int)$cityId : null;
    }

    /**
     * Depth of a pre-existing DB city used as a parent (1 = directly below the region);
     * null when it does not exist under the given region. Bounded by MAX_DEPTH.
     */
    private function dbParentDepth(string $countryId, string $regionCode, string $code): ?int
    {
        $regionId = $this->fetchRegionId($countryId, $regionCode);
        if ($regionId === null) {
            return null;
        }

        $cityId = $this->fetchCityIdByCode($regionId, $code);
        if ($cityId === null) {
            return null;
        }

        $connection = $this->connection();
        $depth = 1;
        $visited = [];
        while (true) {
            if (isset($visited[$cityId]) || $depth > self::MAX_DEPTH) {
                return null; // cycle / runaway guard
            }
            $visited[$cityId] = true;

            $row = $connection->fetchRow(
                $connection->select()
                    ->from($this->table(self::TABLE_CITY), ['parent_city_id'])
                    ->where('city_id = ?', $cityId)
            );
            if ($row === false) {
                return null;
            }
            if ($row['parent_city_id'] === null) {
                return $depth;
            }
            $cityId = (int)$row['parent_city_id'];
            $depth++;
        }
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
