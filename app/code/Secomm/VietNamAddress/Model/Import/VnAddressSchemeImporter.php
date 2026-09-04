<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Import;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Serialize;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\HierarchyAddressImportInterface;
use Secomm\AddressDropdown\Model\AddressProfileResolver;
use Secomm\AddressDropdown\Model\Import\Hierarchy\HierarchyImportValidationException;
use Secomm\VietNamAddress\Api\DirectoryReferenceGuardInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-003 — orchestrates one VERSIONED scheme dataset (VN_ADMIN_*):
 *
 *   read + validate (STOP_ON_ERROR)
 *     → same scheme?  upsert refresh: re-key bridges (assign dataset codes to legacy-format
 *                     REGION rows first, then CITY rows, preserving region_id/city_id)
 *                     → generic hierarchy import (creates regions when absent — the dataset
 *                     is the self-contained seed source, no pre-existing VN data required)
 *                     → stale cleanup (codeless cities, stray regions)
 *     → other scheme? refuse without --swap; with --swap purge ALL VN data first
 *     → historical snapshot (unit table, accumulates across schemes) + registry sync
 *     → orphan membership sweep + reseed (active profile claims every VN region)
 *     → config: secomm_vietnam_address/general/active_scheme + address/profiles/mapping
 *     → clean caches (config / graphql_query / full_page / block_html)
 *
 * The whole write phase runs in ONE transaction (nested chunk commits flatten onto it):
 * a mid-import failure rolls the runtime tables, snapshot, registry, membership and config
 * back to the pre-import state — never a partial hierarchy, never a moved active_scheme.
 *
 * The runtime DB holds exactly ONE scheme at a time (swap model, DEC-002 retained);
 * active-scheme signals never move unless the import succeeded (§7).
 */
class VnAddressSchemeImporter
{
    private const COUNTRY_VN = 'VN';
    private const TABLE_REGION = 'directory_country_region';
    private const TABLE_CITY = 'directory_region_city';
    private const TABLE_MEMBERSHIP = 'secomm_address_profile_location';
    private const TABLE_CONFIG = 'core_config_data';
    private const TABLE_UNIT = 'secomm_vietnam_address_unit';
    private const CACHE_TYPES = ['config', 'graphql_query', 'full_page', 'block_html'];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly VnDatasetReader $reader,
        private readonly VnDatasetValidator $validator,
        private readonly CurrentDatasetRekeyMatcher $rekeyMatcher,
        private readonly HierarchyAddressImportInterface $hierarchyImport,
        private readonly ConfigInterface $configWriter,
        private readonly Serialize $serializer,
        private readonly CacheManager $cacheManager,
        private readonly SchemeRegistryUpdater $registryUpdater,
        private readonly UnitSnapshotWriter $unitSnapshotWriter,
        private readonly LoggerInterface $logger,
        /** DEC-FEATYA2C0W-004 (D7): DirectoryReferenceGuardInterface[] contributed by carrier modules via DI. */
        private readonly array $directoryReferenceGuards = []
    ) {
    }

    /**
     * @param bool $swap purge required when switching to a different installed scheme
     * @param bool $rebuild purge the runtime dataset + this scheme's snapshot rows and
     *                      re-import from scratch (same or different scheme; explicit,
     *                      destructive — the corrected-source re-import path)
     *
     * @throws VnImportValidationException dataset contract violated — nothing written
     * @throws LocalizedException scheme mismatch without swap/rebuild / FK guard / setup faults
     */
    public function import(string $scheme, bool $swap = false, bool $rebuild = false): VnImportReport
    {
        VnSchemes::assertKnown($scheme);
        $report = new VnImportReport();
        $report->scheme = $scheme;
        $dataset = $this->readAndValidate($scheme, $report);

        $this->assertSchemeChangeAllowed($scheme, $swap || $rebuild, $report);
        $datasetRegionCodes = array_map(
            static fn (array $row): string => (string)$row['region_code'],
            $dataset['regions']
        );

        // One transaction across the whole write phase (nested per-chunk commits flatten
        // onto it): everything below leaves together or not at all.
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $switchesScheme = $this->hasVnRegionData() && !in_array($scheme, $this->detectInstalledSchemes(), true);
            if ($rebuild || ($swap && $switchesScheme)) {
                $this->purgeVnData($report);
                if ($rebuild) {
                    $report->rebuildPerformed = true;
                    $this->purgeSchemeSnapshot($scheme, $report);
                } else {
                    $report->swapPerformed = true;
                }
            }

            $this->rekeyExistingRegionRows($dataset['regions'], $report, false);
            if ($scheme === VnSchemes::VN_ADMIN_2025 && !$report->rebuildPerformed) {
                $this->rekeyExistingCurrentRows($dataset['units'], $report, false);
            }

            $this->runHierarchyImport($scheme, $dataset, $report);
            $this->cleanupStaleRows($report, $datasetRegionCodes);

            // Historical reference + registry (Phase C): accumulate then promote — never purged.
            $report->unitsSnapshoted = $this->unitSnapshotWriter->write($scheme, $dataset['regions'], $dataset['units']);
            $this->registryUpdater->apply($scheme);

            $this->purgeOrphanedMembershipClaims($report);
            $this->reseedMembership($scheme, $report);
            $this->setActiveSchemeConfig($scheme, $report);
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->cleanCaches();

        return $report;
    }

    /**
     * Validation + simulation only — no writes. Reports the detected installed scheme(s),
     * whether a swap would be required, and the re-key match rate for VN_ADMIN_2025.
     */
    public function dryRun(string $scheme): VnImportReport
    {
        VnSchemes::assertKnown($scheme);
        $report = new VnImportReport();
        $report->scheme = $scheme;
        $report->dryRun = true;
        $dataset = $this->readAndValidate($scheme, $report);

        $report->installedSchemes = $this->detectInstalledSchemes();
        if ($this->hasVnRegionData() && !in_array($scheme, $report->installedSchemes, true)) {
            $report->warnings[] = sprintf(
                'Installed scheme(s) [%s] differ from "%s": a purge+re-import (--swap) is required.',
                implode(', ', $report->installedSchemes ?: ['none (unclaimed data)']),
                $scheme
            );
            [$regions, $cities, $membership] = $this->countVnData();
            $report->purgedRegions = $regions;
            $report->purgedCities = $cities;
            $report->purgedMembership = $membership;

            // DEC-FEATYA2C0W-004 (D7): surface registered external-reference guards in the
            // simulation so a blocking carrier table is visible BEFORE the swap is attempted.
            $regionIds = $this->fetchVnRegionIds();
            if ($regionIds !== []) {
                $cityIds = $this->connection()->fetchCol(
                    $this->connection()->select()
                        ->from($this->table(self::TABLE_CITY), ['city_id'])
                        ->where('region_id IN (?)', $regionIds)
                );
                $report->guardViolations = $this->collectExternalReferenceViolations($regionIds, $cityIds);
            }
        }
        // Region bridge first (its code map lets the city simulation join dataset codes
        // without writing them).
        $regionCodeMap = $this->rekeyExistingRegionRows($dataset['regions'], $report, true);
        if ($scheme === VnSchemes::VN_ADMIN_2025) {
            $this->rekeyExistingCurrentRows($dataset['units'], $report, true, $regionCodeMap);
        }

        return $report;
    }

    // ------------------------------------------------------------------ validate + gate

    /**
     * @return array{regions: array, units: array, header: string}
     */
    private function readAndValidate(string $scheme, VnImportReport $report): array
    {
        $dataset = $this->reader->read($scheme);
        $errors = $this->validator->validate($scheme, $dataset['regions'], $dataset['units']);
        $report->regionRowsValidated = count($dataset['regions']);
        $report->unitRowsValidated = count($dataset['units']);

        if ($errors !== []) {
            $report->errors = $errors;
            throw new VnImportValidationException(
                __('VN dataset "%1" failed validation with %2 error(s); nothing was written.', $scheme, count($errors)),
                $errors
            );
        }

        return $dataset;
    }

    /**
     * Switching schemes is destructive (purge) — require the explicit swap flag.
     */
    private function assertSchemeChangeAllowed(string $scheme, bool $swap, VnImportReport $report): void
    {
        $report->installedSchemes = $this->detectInstalledSchemes();
        if (!$this->hasVnRegionData() || in_array($scheme, $report->installedSchemes, true)) {
            return; // fresh DB or same-scheme refresh (legacy profile aliases resolve here)
        }
        if (!$swap) {
            throw new LocalizedException(
                __(
                    'VN data is installed for scheme(s) [%1]; importing "%2" would purge it. '
                    . 'Re-run with --swap to confirm the scheme switch.',
                    implode(', ', $report->installedSchemes ?: ['none (unclaimed data)']),
                    $scheme
                )
            );
        }
    }

    // ------------------------------------------------------------------ swap purge

    /**
     * Purge every VN address entity (regions cascade to cities + locale names); membership
     * rows carry no FK and are deleted explicitly. is_default=1 regions stay protected
     * (Magento-seeded data). Aborts up-front when registered external-reference guards
     * (carrier mapping tables) still reference the ids about to disappear.
     */
    private function purgeVnData(VnImportReport $report): void
    {
        $connection = $this->connection();
        $regionIds = $this->fetchVnRegionIds();

        if ($regionIds === []) {
            return;
        }

        $cityIds = $connection->fetchCol(
            $connection->select()
                ->from($this->table(self::TABLE_CITY), ['city_id'])
                ->where('region_id IN (?)', $regionIds)
        );

        $this->assertNoExternalReferences($regionIds, $cityIds);

        $membershipWhere = [
            $connection->quoteInto('location_type = ?', 'region')
            . ' AND ' . $connection->quoteInto('location_id IN (?)', $regionIds),
        ];
        if ($cityIds !== []) {
            $membershipWhere[] = $connection->quoteInto('location_type = ?', 'city')
                . ' AND ' . $connection->quoteInto('location_id IN (?)', $cityIds);
        }

        $report->purgedRegions = count($regionIds);
        $report->purgedCities = count($cityIds);

        // Transaction: membership + regions (+ cascaded children) leave together or not at all.
        $connection->beginTransaction();
        try {
            $report->purgedMembership = $connection->delete(
                $this->table(self::TABLE_MEMBERSHIP),
                new \Zend_Db_Expr('(' . implode(') OR (', $membershipWhere) . ')')
            );
            $connection->delete(
                $this->table(self::TABLE_REGION),
                $connection->quoteInto('region_id IN (?)', $regionIds)
            );
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Rebuild-only: replace this scheme's historical snapshot with the corrected source
     * (other schemes' snapshots stay — accumulation across schemes is the invariant).
     */
    private function purgeSchemeSnapshot(string $scheme, VnImportReport $report): void
    {
        $report->unitsPurged = $this->connection()->delete(
            $this->table(self::TABLE_UNIT),
            $this->connection()->quoteInto('scheme_code = ?', $scheme)
        );
    }

    /**
     * DEC-FEATYA2C0W-004 (D7): external (carrier) mapping tables key on directory PKs — a
     * swap would orphan their rows. Guards are contributed via DI by the owning modules;
     * VietNamAddress never names their tables. Every registered guard runs and the
     * violations aggregate into ONE exception (same "Cannot swap: …" wording as before,
     * now listing every blocking table).
     *
     * @param array<int, int> $regionIds
     * @param array<int, int> $cityIds
     */
    private function assertNoExternalReferences(array $regionIds, array $cityIds): void
    {
        $violations = $this->collectExternalReferenceViolations($regionIds, $cityIds);
        if ($violations !== []) {
            throw new LocalizedException(
                __('Cannot swap: %1', implode(' | ', $violations))
            );
        }
    }

    /**
     * Run all registered guards and return their violation messages ("[GuardName] message").
     * Never throws — used directly by the dry-run report and by assertNoExternalReferences().
     *
     * @param array<int, int> $regionIds
     * @param array<int, int> $cityIds
     * @return array<int, string>
     */
    private function collectExternalReferenceViolations(array $regionIds, array $cityIds): array
    {
        $violations = [];
        foreach ($this->directoryReferenceGuards as $guard) {
            if (!$guard instanceof DirectoryReferenceGuardInterface) {
                $this->logger->warning(
                    'Secomm_VietNamAddress: skipping invalid directory reference guard entry.',
                    ['guard_type' => get_debug_type($guard)]
                );
                continue;
            }
            try {
                $guard->assertSafe($regionIds, $cityIds);
            } catch (LocalizedException $e) {
                $violations[] = sprintf('[%s] %s', $guard->getName(), $e->getMessage());
            }
        }

        return $violations;
    }

    // ------------------------------------------------------------------ re-key bridge

    /**
     * Region-level bridge, ALWAYS before the city bridge (the city join reads r.code):
     * legacy-format regions carry bare official codes ("01" = Hanoi, government
     * numbering) while datasets use "VN-XX" sequence codes (VN-01 = An Giang) — matched
     * on normalised names, preserving region_id. Rows already carrying a dataset code
     * are aligned and skipped; rows left unmatched are reported and removed by the
     * stray-region cleanup after the hierarchy import.
     *
     * @param array<int, array<string, string|int>> $regionRows dataset region rows
     * @return array<string, string> legacy region code => dataset region code (dry-run map)
     */
    private function rekeyExistingRegionRows(array $regionRows, VnImportReport $report, bool $dryRun): array
    {
        $connection = $this->connection();
        $existing = $connection->fetchAll(
            $connection->select()
                ->from(['r' => $this->table(self::TABLE_REGION)], ['region_id', 'code', 'default_name'])
                ->joinLeft(
                    ['n' => $this->resource->getTableName('directory_country_region_name')],
                    'n.region_id = r.region_id AND ' . $connection->quoteInto('n.locale = ?', 'vi_VN'),
                    ['vi_name' => 'n.name']
                )
                ->where('r.country_id = ?', self::COUNTRY_VN)
                ->where('r.is_default = ?', 0)
        );

        if ($existing === []) {
            return [];
        }

        $datasetCodes = [];
        foreach ($regionRows as $row) {
            $datasetCodes[(string)$row['region_code']] = true;
        }
        $this->rekeyMatcher->buildRegionIndex($regionRows);

        $takenCodes = [];
        foreach ($existing as $row) {
            if (isset($datasetCodes[(string)$row['code']])) {
                $takenCodes[(string)$row['code']] = (int)$row['region_id'];
            }
        }

        $codeMap = [];
        foreach ($existing as $row) {
            $regionId = (int)$row['region_id'];
            if (isset($datasetCodes[(string)$row['code']])) {
                continue; // already aligned
            }

            try {
                $code = $this->rekeyMatcher->matchRegion((string)$row['default_name'], (string)($row['vi_name'] ?? ''));
            } catch (\RuntimeException $e) {
                $report->warnings[] = sprintf('Region re-key skipped region_id %d: %s', $regionId, $e->getMessage());
                $report->rekeyRegionMissed++;
                continue;
            }

            if ($code === null || isset($takenCodes[$code])) {
                if ($code !== null) {
                    $report->warnings[] = sprintf(
                        'Region re-key skipped region_id %d: dataset code "%s" is already held by region_id %d.',
                        $regionId,
                        $code,
                        $takenCodes[$code]
                    );
                }
                $report->rekeyRegionMissed++;
                continue;
            }

            $codeMap[(string)$row['code']] = $code;
            if ($dryRun) {
                $report->rekeyRegionMatched++;
                continue;
            }

            $connection->update(
                $this->table(self::TABLE_REGION),
                ['code' => $code],
                $connection->quoteInto('region_id = ?', $regionId)
            );
            $takenCodes[$code] = $regionId;
            $report->rekeyRegionMatched++;
        }

        return $codeMap;
    }

    /**
     * One-time bridge from the legacy-format current data (codes NULL, names carry type
     * words) to the dataset VNA25 codes — preserves city_id. Rows left unmatched are
     * reported and later removed by the stale cleanup.
     *
     * @param array<int, array<string, string|int>> $unitRows dataset unit rows
     * @param array<string, string> $regionCodeMap dry-run region bridge map (legacy => dataset
     *        region code); empty on the write path, where regions were re-keyed in DB first
     */
    private function rekeyExistingCurrentRows(
        array $unitRows,
        VnImportReport $report,
        bool $dryRun,
        array $regionCodeMap = []
    ): void {
        $connection = $this->connection();
        $existing = $connection->fetchAll(
            $connection->select()
                ->from(['c' => $this->table(self::TABLE_CITY)], ['city_id', 'default_name'])
                ->join(
                    ['r' => $this->table(self::TABLE_REGION)],
                    'r.region_id = c.region_id',
                    ['region_code' => 'r.code']
                )
                ->joinLeft(
                    ['n' => $this->resource->getTableName('directory_region_city_name')],
                    'n.city_id = c.city_id AND ' . $connection->quoteInto('n.locale = ?', 'vi_VN'),
                    ['vi_name' => 'n.name']
                )
                ->where('r.country_id = ?', self::COUNTRY_VN)
                ->where('c.code IS NULL')
        );

        if ($existing === []) {
            return;
        }

        $this->rekeyMatcher->buildIndex($unitRows);

        foreach ($existing as $row) {
            $viName = (string)($row['vi_name'] ?? '');
            // Dry-run only: the region bridge did not write dataset codes, translate the
            // join's legacy region code through the simulated map.
            $regionCode = $regionCodeMap[(string)$row['region_code']] ?? (string)$row['region_code'];
            try {
                $code = $this->rekeyMatcher->match($regionCode, $viName, (string)$row['default_name']);
            } catch (\RuntimeException $e) {
                $report->warnings[] = sprintf('Re-key skipped city_id %d: %s', (int)$row['city_id'], $e->getMessage());
                $report->rekeyMissed++;
                continue;
            }

            if ($code === null) {
                $report->rekeyMissed++;
                continue;
            }
            if ($dryRun) {
                $report->rekeyMatched++;
                continue;
            }

            $connection->update(
                $this->table(self::TABLE_CITY),
                ['code' => $code],
                $connection->quoteInto('city_id = ?', (int)$row['city_id']) . ' AND code IS NULL'
            );
            $report->rekeyMatched++;
        }
    }

    // ------------------------------------------------------------------ generic import + cleanup

    /**
     * Synthesise generic hierarchy rows (region rows from the derived region dataset,
     * city rows from the unit dataset) and run the code-identified import.
     *
     * @param array{regions: array, units: array, header: string} $dataset
     */
    private function runHierarchyImport(string $scheme, array $dataset, VnImportReport $report): void
    {
        $importRows = [];
        foreach ($dataset['regions'] as $row) {
            $importRows[] = [
                'entity_type' => 'region',
                'region_code' => '',
                'code' => (string)$row['region_code'],
                'parent_code' => '',
                'default_name' => (string)$row['name_en'],
                'names' => ['vi_VN' => (string)$row['name_vi']],
            ];
        }
        foreach ($dataset['units'] as $row) {
            $importRows[] = [
                'entity_type' => 'city',
                'region_code' => (string)$row['region_code'],
                'code' => (string)$row['code'],
                'parent_code' => (string)$row['parent_code'],
                'default_name' => (string)$row['name_en'],
                'names' => ['vi_VN' => (string)$row['name_vi']],
            ];
        }

        try {
            $result = $this->hierarchyImport->import(self::COUNTRY_VN, $importRows);
        } catch (HierarchyImportValidationException $e) {
            $report->errors = array_map(
                static fn (array $error): string => sprintf(
                    'Row %s [%s]: %s',
                    $error['row_index'] ?? '?',
                    $error['code'] ?? '',
                    $error['reason'] ?? ''
                ),
                $e->getResult()->getErrors()
            );
            throw new VnImportValidationException(
                __('Hierarchy import validation failed for "%1"; nothing new was written.', $scheme),
                $report->errors
            );
        }

        $report->regionsInserted = $result->getRegionsInserted();
        $report->regionsUpdated = $result->getRegionsUpdated();
        $report->citiesInserted = $result->getCitiesInserted();
        $report->citiesUpdated = $result->getCitiesUpdated();
    }

    /**
     * Post-import reconciliation: (1) city rows that never received a dataset code
     * (legacy-format strays), then (2) VN regions whose code is not one of the dataset's
     * region codes (unmatched legacy regions / mixed-scheme leftovers) — the swap model
     * keeps exactly one scheme in the runtime tables. Remaining cities of a stray region
     * cascade on delete; external references abort loudly, like a swap purge.
     *
     * @param array<int, string> $datasetRegionCodes
     */
    private function cleanupStaleRows(VnImportReport $report, array $datasetRegionCodes): void
    {
        $regionIds = $this->fetchVnRegionIds();
        if ($regionIds === []) {
            return;
        }

        $connection = $this->connection();
        $stale = (int)$connection->fetchOne(
            $connection->select()
                ->from($this->table(self::TABLE_CITY), ['COUNT(*)'])
                ->where('region_id IN (?)', $regionIds)
                ->where('code IS NULL')
        );

        if ($stale > 0) {
            $connection->delete(
                $this->table(self::TABLE_CITY),
                $connection->quoteInto('region_id IN (?)', $regionIds) . ' AND code IS NULL'
            );
            $report->staleRemoved = $stale;
        }

        $strayRegionIds = array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($this->table(self::TABLE_REGION), ['region_id'])
                ->where('country_id = ?', self::COUNTRY_VN)
                ->where('is_default = ?', 0)
                ->where('code IS NULL OR code NOT IN (?)', $datasetRegionCodes)
        ));

        if ($strayRegionIds === []) {
            return;
        }

        $strayCityIds = $connection->fetchCol(
            $connection->select()
                ->from($this->table(self::TABLE_CITY), ['city_id'])
                ->where('region_id IN (?)', $strayRegionIds)
        );
        $this->assertNoExternalReferences($strayRegionIds, $strayCityIds);

        $connection->delete(
            $this->table(self::TABLE_REGION),
            $connection->quoteInto('region_id IN (?)', $strayRegionIds)
        );
        $report->staleRegionsRemoved = count($strayRegionIds);
    }

    // ------------------------------------------------------------------ membership + config

    /**
     * Claims whose location ids no longer exist (regions/cities deleted underneath them,
     * e.g. manual DB cleanups) are never touched by the reseed — it only replaces claims
     * of ids that DO exist — and would accumulate forever. Scoped to VN scheme profiles
     * (canonical + legacy aliases); other countries' profiles never share this table's
     * VN rows.
     */
    private function purgeOrphanedMembershipClaims(VnImportReport $report): void
    {
        $profileCodes = [];
        foreach (VnSchemes::catalog() as $entry) {
            $profileCodes[] = $entry['profile_code'];
            foreach ($entry['legacy_profile_aliases'] as $alias) {
                $profileCodes[] = $alias;
            }
        }

        $connection = $this->connection();
        $report->orphanMembershipRemoved = $connection->delete(
            $this->table(self::TABLE_MEMBERSHIP),
            $connection->quoteInto('profile_code IN (?)', array_values(array_unique($profileCodes)))
            . ' AND ((' . $connection->quoteInto('location_type = ?', 'region')
            . ' AND location_id NOT IN (SELECT region_id FROM ' . $this->table(self::TABLE_REGION) . '))'
            . ' OR (' . $connection->quoteInto('location_type = ?', 'city')
            . ' AND location_id NOT IN (SELECT city_id FROM ' . $this->table(self::TABLE_CITY) . ')))'
        );
    }

    /**
     * Swap model: only the active scheme's profile claims VN; claims of every profile are replaced.
     */
    private function reseedMembership(string $scheme, VnImportReport $report): void
    {
        $connection = $this->connection();
        $regionIds = $this->fetchVnRegionIds();
        if ($regionIds === []) {
            return;
        }

        $cityIds = $connection->fetchCol(
            $connection->select()
                ->from($this->table(self::TABLE_CITY), ['city_id'])
                ->where('region_id IN (?)', $regionIds)
        );

        $conditions = [
            $connection->quoteInto('location_type = ?', 'region')
            . ' AND ' . $connection->quoteInto('location_id IN (?)', $regionIds),
        ];
        if ($cityIds !== []) {
            $conditions[] = $connection->quoteInto('location_type = ?', 'city')
                . ' AND ' . $connection->quoteInto('location_id IN (?)', $cityIds);
        }

        $rows = array_map(
            static fn (int $regionId): array => [
                'profile_code' => VnSchemes::profileCode($scheme),
                'location_type' => 'region',
                'location_id' => $regionId,
                'include_subtree' => 1,
            ],
            $regionIds
        );

        $connection->delete(
            $this->table(self::TABLE_MEMBERSHIP),
            new \Zend_Db_Expr('(' . implode(') OR (', $conditions) . ')')
        );
        $connection->insertOnDuplicate($this->table(self::TABLE_MEMBERSHIP), $rows);
        $report->membershipRows = count($rows);
    }

    /**
     * Point the default-scope configs at the imported scheme — ONLY after import success
     * (§7): secomm_vietnam_address/general/active_scheme + address/profiles/mapping
     * (other countries' mapping entries preserved).
     */
    private function setActiveSchemeConfig(string $scheme, VnImportReport $report): void
    {
        $mapping = $this->readDefaultScopeMapping();
        $profileCode = VnSchemes::profileCode($scheme);

        if (($mapping[self::COUNTRY_VN] ?? null) !== $profileCode) {
            $mapping[self::COUNTRY_VN] = $profileCode;
            $this->configWriter->saveConfig(
                AddressProfileResolver::XML_PATH_PROFILE_MAPPING,
                $this->serializer->serialize($mapping),
                'default',
                0
            );
            $report->configUpdated = true;
        }
        $this->configWriter->saveConfig(VnSchemes::XML_PATH_ACTIVE_SCHEME, $scheme, 'default', 0);
    }

    /**
     * @return array<string, string>
     */
    private function readDefaultScopeMapping(): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->table(self::TABLE_CONFIG), ['value'])
            ->where('path = ?', AddressProfileResolver::XML_PATH_PROFILE_MAPPING)
            ->where('scope = ?', 'default')
            ->where('scope_id = ?', 0)
            ->limit(1);
        $raw = $connection->fetchOne($select);
        if (!$raw) {
            return [];
        }

        try {
            $mapping = $this->serializer->unserialize((string)$raw);
        } catch (\InvalidArgumentException) {
            $this->logger->warning(
                'Secomm_VietNamAddress: address profile mapping config corrupted at default scope; rebuilding.'
            );

            return [];
        }

        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Best-effort cache clean, logged (never fatal): the Setup application
     * (setup:upgrade running this importer via the data patch) does not bootstrap the
     * full cache type configuration — CacheManager::clean would fatal there. Deployment
     * flushes caches around setup:upgrade anyway; the CLI path always cleans.
     */
    private function cleanCaches(): void
    {
        try {
            $this->cacheManager->clean(self::CACHE_TYPES);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Secomm_VietNamAddress: post-import cache clean skipped (cache infrastructure unavailable): '
                . $e->getMessage()
            );
        }
    }

    // ------------------------------------------------------------------ db helpers

    /**
     * Canonical schemes whose profiles (incl. legacy aliases vn_current/vn_legacy) claim
     * VN regions right now — alias resolution keeps dev-DB upgrades purge-free.
     *
     * @return string[]
     */
    private function detectInstalledSchemes(): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from(['m' => $this->table(self::TABLE_MEMBERSHIP)], ['profile_code'])
            ->join(
                ['r' => $this->table(self::TABLE_REGION)],
                'r.region_id = m.location_id',
                []
            )
            ->where('m.location_type = ?', 'region')
            ->where('r.country_id = ?', self::COUNTRY_VN)
            ->distinct(true);

        $schemes = [];
        foreach (array_map('strval', $connection->fetchCol($select)) as $profileCode) {
            $scheme = VnSchemes::schemeForProfile($profileCode);
            if ($scheme !== null) {
                $schemes[$scheme] = true;
            }
        }

        return array_keys($schemes);
    }

    private function hasVnRegionData(): bool
    {
        return $this->fetchVnRegionIds() !== [];
    }

    /**
     * All VN region ids — swap model: only one scheme's regions exist at a time.
     *
     * @return array<int, int>
     */
    private function fetchVnRegionIds(): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->table(self::TABLE_REGION), ['region_id'])
            ->where('country_id = ?', self::COUNTRY_VN)
            ->where('is_default = ?', 0);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * @return array{0: int, 1: int, 2: int} [regions, cities, membership]
     */
    private function countVnData(): array
    {
        $connection = $this->connection();
        $regionIds = $this->fetchVnRegionIds();
        $cities = 0;
        $membership = 0;

        if ($regionIds !== []) {
            $cities = (int)$connection->fetchOne(
                $connection->select()
                    ->from($this->table(self::TABLE_CITY), ['COUNT(*)'])
                    ->where('region_id IN (?)', $regionIds)
            );
            $membership = (int)$connection->fetchOne(
                $connection->select()
                    ->from($this->table(self::TABLE_MEMBERSHIP), ['COUNT(*)'])
                    ->where('location_type = ?', 'region')
                    ->where('location_id IN (?)', $regionIds)
            );
        }

        return [count($regionIds), $cities, $membership];
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
