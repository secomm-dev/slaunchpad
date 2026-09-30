<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrarInterface;

/**
 * TASK-SEC-1.2 — EXACT synchronization of the bundled PRE-2025 → 2025 mapping with the
 * authoritative SNAPSHOT_2024 dataset. Replaces the earlier count-based no-op: a row count
 * is NOT an identity — a table holding 10,418 wrong rows would previously have been skipped.
 *
 * Two-phase, idempotent, convergent:
 *   1. UPSERT every snapshot edge (VnMappingImporter: validate-ALL then upsert on the
 *      UNIQUE(source_scheme, source_code, target_scheme, target_code) key).
 *   2. DELETE exactly the bundled-ownership keyset recorded in the manifest — the 41 edges
 *      the snapshot dataset REMOVED relative to the superseded baseline. Nothing outside
 *      that keyset is ever deleted, so merchant/custom edges (keys outside both bundled
 *      datasets) survive untouched, and repeat runs converge with zero further changes.
 *
 * Dataset identity = content (SHA-256 of the bundled files, recorded in the manifest);
 * counts are reported, never used as a match condition. No scheme swap, no unit purge.
 *
 * @return array{upserted_snapshot_edges: int, removed_stale_keys: int, manifest: array}
 */
class VnSnapshotMappingResync
{
    private const SNAPSHOT_FILE = 'VN_ADMIN_PRE_2025_TO_2025_SNAPSHOT_2024_mapping.csv';
    private const MANIFEST_FILE = 'VN_ADMIN_PRE_2025_SNAPSHOT_2024_mapping_manifest.json';
    private const MAPPING_TABLE = 'secomm_vietnam_address_mapping';

    public function __construct(
        private readonly VnMappingImporter $importer,
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public const STATUS_EXACT = 'exact bundled sync complete';
    public const STATUS_WITH_CONFLICTS = 'complete with preserved merchant conflict';

    /**
     * @return array{upserted_snapshot_edges: int, removed_stale_keys: int, conflicts: array,
     *               status: string, manifest: array}
     * @throws VnImportValidationException when the snapshot dataset fails validation
     * @throws \RuntimeException when the manifest checksums no longer match the bundled files
     */
    public function sync(): array
    {
        $manifest = $this->manifest();

        // Content identity first: the bundled files must still be the recorded versions.
        $this->assertChecksums($manifest);

        // Manifest contract: removed keys and snapshot keys must be disjoint — a key both
        // removed and present is a broken bundled contract and fails loudly.
        $snapshotSources = array_flip($manifest['snapshot_sources'] ?? []);
        foreach ($manifest['removed_keys'] ?? [] as $key) {
            if (isset($snapshotSources[$key['source_code'] . '|' . $key['target_code']])) {
                throw new \RuntimeException(
                    sprintf('Manifest contract conflict: removed key "%s|%s" also exists in the authoritative snapshot.', $key['source_code'], $key['target_code'])
                );
            }
        }

        // Phase 1 — assert every authoritative edge (validate-ALL + idempotent upsert).
        $result = $this->importer->import($this->moduleFile(self::SNAPSHOT_FILE), false);
        $upserted = isset($result['edges']) ? (int) $result['edges'] : (int) ($manifest['snapshot_edge_count'] ?? 0);

        // Phase 2 — old-value guarded removal: a bundled key is deleted ONLY when the
        // current row still carries the exact old bundled value. A changed row is a
        // merchant modification: preserved, reported, never silently overwritten.
        $conflicts = [];
        $removed = $this->removeStaleBundledKeys($manifest['removed_keys'] ?? [], $conflicts);

        return [
            'upserted_snapshot_edges' => $upserted,
            'removed_stale_keys' => $removed,
            'conflicts' => $conflicts,
            'status' => $conflicts === [] ? self::STATUS_EXACT : self::STATUS_WITH_CONFLICTS,
            'manifest' => $manifest,
        ];
    }

    private function manifest(): array
    {
        $raw = (string) file_get_contents($this->moduleFile(self::MANIFEST_FILE));
        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || !isset($manifest['removed_keys'], $manifest['snapshot_sha256'])) {
            throw new \RuntimeException('Mapping resync manifest is missing or malformed.');
        }

        return $manifest;
    }

    private function assertChecksums(array $manifest): void
    {
        $pairs = [
            [self::SNAPSHOT_FILE, $manifest['snapshot_sha256'] ?? null],
            [$manifest['superseded_baseline'] ?? '', $manifest['superseded_baseline_sha256'] ?? null],
        ];
        foreach ($pairs as [$file, $expected]) {
            if (!is_string($expected) || $expected === '') {
                continue;
            }
            $actual = hash_file('sha256', $this->moduleFile($file));
            if ($actual !== $expected) {
                throw new \RuntimeException(
                    sprintf(
                        'VN mapping dataset checksum mismatch for "%s": expected sha256 %s, got %s. '
                        . 'The bundled dataset does not match its recorded version contract.',
                        $file,
                        $expected,
                        $actual
                    )
                );
            }
        }
    }

    /**
     * @param array<int, array{source_code: string, target_code: string, relation_type: string, old_is_primary: int, row_fingerprint_sha256: string}> $removedKeys
     * @param array<int, array<string, mixed>> $conflicts preserved merchant-modified rows
     */
    private function removeStaleBundledKeys(array $removedKeys, array &$conflicts): int
    {
        if ($removedKeys === []) {
            return 0;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::MAPPING_TABLE);
        $removed = 0;
        foreach ($removedKeys as $key) {
            // Read the CURRENT row content — the guard compares value, not just existence.
            $current = $connection->fetchRow(
                $connection->select()
                    ->from($table, ['relation_type', 'is_primary'])
                    ->where('source_scheme = ?', 'VN_ADMIN_PRE_2025')
                    ->where('source_code = ?', (string) $key['source_code'])
                    ->where('target_scheme = ?', 'VN_ADMIN_2025')
                    ->where('target_code = ?', (string) $key['target_code'])
            );

            if ($current === false || $current === null || (array) $current === []) {
                continue; // already gone — converged, nothing to remove
            }

            $currentRel = (string) ($current['relation_type'] ?? '');
            $currentPrimary = (int) ($current['is_primary'] ?? 0);
            $oldRel = (string) $key['relation_type'];
            $oldPrimary = (int) ($key['old_is_primary'] ?? 0);

            if ($currentRel !== $oldRel || $currentPrimary !== $oldPrimary) {
                // Merchant changed the row after the baseline seed — PRESERVE + report.
                $conflicts[] = [
                    'source_code' => $key['source_code'],
                    'target_code' => $key['target_code'],
                    'bundled_value' => ['relation_type' => $oldRel, 'is_primary' => $oldPrimary],
                    'current_value' => ['relation_type' => $currentRel, 'is_primary' => $currentPrimary],
                ];
                continue;
            }

            $removed += $connection->delete($table, [
                'source_scheme = ?' => 'VN_ADMIN_PRE_2025',
                'source_code = ?' => (string) $key['source_code'],
                'target_scheme = ?' => 'VN_ADMIN_2025',
                'target_code = ?' => (string) $key['target_code'],
            ]);
        }

        return $removed;
    }

    private function moduleFile(string $file): string
    {
        return rtrim((string) $this->componentRegistrar->getPath('module', 'Secomm_VietNamAddress'), '/')
            . '/Files/' . $file;
    }
}
