<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Secomm\VietNamAddress\Model\Import\VnImportValidationException;
use Secomm\VietNamAddress\Model\Import\VnMappingImporter;
use Secomm\VietNamAddress\Model\Import\VnReferenceSchemeImporter;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-GS78X2 — corrective snapshot migration: replace the VN_ADMIN_PRE_2025 reference-layer
 * dataset with the end-of-2024 administrative snapshot (63 provinces + 696 district-level +
 * 10,035 ward-level units). SNAPSHOT_2024 is a dataset VERSION, not a new scheme — the
 * scheme_code stays VN_ADMIN_PRE_2025 and the codes are imported verbatim from the CSV
 * (canonical source, never regenerated at runtime).
 *
 * Orchestration ONLY — every step reuses the existing import stack:
 *   cleanup (mapping table + scheme-scoped unit delete)
 *     → VnSnapshot2024ReferenceImporter (virtual: VnReferenceSchemeImporter + snapshot reader
 *       + snapshot expected-counts validator) — read + validate + unit upsert + registry in
 *       ONE transaction
 *     → VnMappingImporter — validate ALL edges (relation types, duplicates, orphans against
 *       the just-imported unit catalog) then rebuild the truncated mapping table
 *     → post-import validation — mandated counts, no duplicate codes, no orphan parents,
 *       DB-mode mapping re-validation
 *
 * ONE patch-level transaction wraps the DML (nested with the reference importer's own —
 * Magento inner commit/rollBack are level-decrements, the patch commit is the real one), so
 * a failed setup:upgrade cannot leave a half-imported state: units and mappings roll back
 * together. The mapping cleanup is a DELETE inside that transaction rather than a TRUNCATE —
 * TRUNCATE is DDL and would auto-commit, defeating the failure-safety goal.
 *
 * Deliberately untouched: directory_country_region / directory_region_city, VN_ADMIN_2025
 * units, active_scheme config, membership, caches (the reference-only importer structurally
 * cannot reach them). Existing patches are neither modified nor re-ordered — this patch runs
 * after the legacy seed chain via getDependencies().
 */
class RefreshVnAdminPre2025Snapshot2024 implements DataPatchInterface
{
    private const SNAPSHOT_MAPPING_FILE = 'VN_ADMIN_PRE_2025_TO_2025_SNAPSHOT_2024_mapping.csv';

    /** End-of-2024 administrative structure the snapshot must reproduce exactly. */
    private const EXPECTED_REGION_COUNT = 63;
    private const EXPECTED_DISTRICT_COUNT = 696;
    private const EXPECTED_WARD_COUNT = 10035;

    public function __construct(
        private readonly VnReferenceSchemeImporter $referenceImporter,
        private readonly VnMappingImporter $mappingImporter,
        private readonly ResourceConnection $resource,
        private readonly ComponentRegistrarInterface $componentRegistrar
    ) {
    }

    /**
     * @inheritDoc
     * @throws VnImportValidationException dataset/DB contract violated — nothing was written
     * @throws \Exception DB fault — transaction rolled back, nothing partial remains
     */
    public function apply(): void
    {
        $connection = $this->resource->getConnection();
        $unitTable = $this->resource->getTableName('secomm_vietnam_address_unit');
        $mappingTable = $this->resource->getTableName('secomm_vietnam_address_mapping');

        $connection->beginTransaction();
        try {
            // Cleanup: the mapping table is canonical inter-scheme mapping data of this module
            // only (verified 2026-09-08: single PRE_2025→2025 edge set) and is rebuilt in full
            // below; the unit table loses ONLY the VN_ADMIN_PRE_2025 scheme.
            $connection->delete($mappingTable);
            $connection->delete($unitTable, ['scheme_code = ?' => VnSchemes::VN_ADMIN_PRE_2025]);

            // Import the snapshot units (+ registry refresh) — validated before anything is
            // written; runs nested in this patch's transaction.
            $this->referenceImporter->import(VnSchemes::VN_ADMIN_PRE_2025);

            // Rebuild the inter-scheme mapping from the snapshot file — every edge is validated
            // against the unit catalog just written (source PRE_2025 / target VN_ADMIN_2025).
            $result = $this->mappingImporter->import($this->snapshotMappingPath(), false);

            // Post-import validation — fail loud, never silently continue.
            $errors = $this->collectPostImportErrors($connection, $unitTable, $mappingTable, (int)$result['rows_validated']);
            if ($errors !== []) {
                throw new VnImportValidationException(
                    __('VN_ADMIN_PRE_2025 snapshot 2024 import failed validation with %1 error(s); nothing was written.', count($errors)),
                    $errors
                );
            }

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        // The legacy seed chain (2025 scheme → PRE_2025 reference → PRE_2025→2025 mapping)
        // must have run first on fresh installs; the snapshot replaces its data wholesale.
        return [ImportVnAdminPre2025To2025MappingPatch::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    private function snapshotMappingPath(): string
    {
        return rtrim((string)$this->componentRegistrar->getPath('module', 'Secomm_VietNamAddress'), '/')
            . '/Files/' . self::SNAPSHOT_MAPPING_FILE;
    }

    /**
     * @return array<int, string>
     */
    private function collectPostImportErrors(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        string $unitTable,
        string $mappingTable,
        int $importedEdgeCount
    ): array {
        $errors = [];

        $scheme = VnSchemes::VN_ADMIN_PRE_2025;
        $levels = [1 => self::EXPECTED_REGION_COUNT, 2 => self::EXPECTED_DISTRICT_COUNT, 3 => self::EXPECTED_WARD_COUNT];
        foreach ($levels as $level => $expected) {
            $actual = (int)$connection->fetchOne(
                $connection->select()
                    ->from($unitTable, ['COUNT(*)'])
                    ->where('scheme_code = ?', $scheme)
                    ->where('level = ?', $level)
            );
            if ($actual !== $expected) {
                $errors[] = sprintf('VN_ADMIN_PRE_2025 unit count at level %d: expected %d, got %d.', $level, $expected, $actual);
            }
        }

        $duplicates = $connection->fetchCol(
            $connection->select()
                ->from($unitTable, ['code'])
                ->where('scheme_code = ?', $scheme)
                ->group('code')
                ->having('COUNT(*) > 1')
        );
        foreach ($duplicates as $code) {
            $errors[] = sprintf('Duplicate VN_ADMIN_PRE_2025 unit code "%s" in secomm_vietnam_address_unit.', $code);
        }

        $orphans = $connection->fetchCol(
            $connection->select()
                ->from(['u' => $unitTable], ['u.code'])
                ->joinLeft(
                    ['p' => $unitTable],
                    'p.scheme_code = u.scheme_code AND p.code = u.parent_code',
                    []
                )
                ->where('u.scheme_code = ?', $scheme)
                ->where('u.parent_code IS NOT NULL')
                ->where('p.code IS NULL')
        );
        foreach ($orphans as $code) {
            $errors[] = sprintf('Orphan parent_code on VN_ADMIN_PRE_2025 unit "%s".', $code);
        }

        $edgeCount = (int)$connection->fetchOne(
            $connection->select()
                ->from($mappingTable, ['COUNT(*)'])
                ->where('source_scheme = ?', VnSchemes::VN_ADMIN_PRE_2025)
                ->where('target_scheme = ?', VnSchemes::VN_ADMIN_2025)
        );
        if ($edgeCount !== $importedEdgeCount) {
            $errors[] = sprintf(
                'Mapping rebuild incomplete: imported %d edges but secomm_vietnam_address_mapping holds %d.',
                $importedEdgeCount,
                $edgeCount
            );
        }

        // DB-mode re-validation of the rebuilt edges (orphans against the new unit catalog,
        // relation types, duplicate canonical edges) — reuses the mapping validator contract.
        $mappingErrors = $this->mappingImporter->validate(null)['errors'];
        foreach ($mappingErrors as $mappingError) {
            $errors[] = (string)$mappingError;
        }

        return $errors;
    }
}
