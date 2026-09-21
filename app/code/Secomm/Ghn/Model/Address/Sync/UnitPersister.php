<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Sync;

use Magento\Framework\Exception\LocalizedException;
use Secomm\Ghn\Model\Address\GhnSchemes;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;

/**
 * SPEC-TASK-TBM30R §8 — shared parent-first persistence for normalized unit rows, used by BOTH
 * the API synchronizer (GHN-B) and the file importer (GHN-B2) so API-synced and file-bootstrapped
 * data follow exactly the same rules: upsert keyed by (scheme, provider_key), parents resolved
 * before children, rows missing from the snapshot soft-DISABLED (never deleted).
 */
class UnitPersister
{
    public function __construct(private readonly AddressUnit $unitResource)
    {
    }

    /**
     * Known provider keys for a scheme (provider_key => entity_id, all statuses) — used by
     * callers for "present before" reporting without duplicating resource access.
     *
     * @return array<string, int>
     */
    public function knownKeys(string $scheme): array
    {
        return $this->unitResource->fetchKeys($scheme);
    }

    /**
     * @param array<int, array<string, mixed>> $rows normalized rows (MasterDataFetcher shape)
     * @return array{affected: int, disabled: int}
     * @throws LocalizedException duplicate provider_key / missing parent
     */
    public function persist(string $scheme, array $rows, string $sourceVersion, bool $dryRun): array
    {
        GhnSchemes::assertKnown($scheme);
        $this->assertUniqueKeys($rows);

        $existing = $this->unitResource->fetchKeys($scheme);

        $keepKeys = [];
        foreach ($rows as $row) {
            $keepKeys[(string) $row['provider_key']] = true;
        }
        $missingCount = 0;
        foreach (array_keys($existing) as $existingKey) {
            if (!isset($keepKeys[$existingKey])) {
                $missingCount++;
            }
        }

        if ($dryRun) {
            return ['affected' => 0, 'disabled' => $missingCount];
        }

        // Parent-first write: refresh the key→entity map after each depth so children resolve
        // parent_id against both pre-existing rows and rows just written.
        $byDepth = [];
        foreach ($rows as $row) {
            $byDepth[(int) $row['depth']][] = $row;
        }
        ksort($byDepth);

        $keyMap = $existing;
        $affected = 0;
        foreach ($byDepth as $depth => $depthRows) {
            $dbRows = [];
            foreach ($depthRows as $row) {
                $parentKey = $row['parent_key'];
                $parentId = null;
                if ($parentKey !== null) {
                    $parentId = $keyMap[$parentKey]
                        ?? throw new LocalizedException(
                            __('Address unit "%1" references missing parent "%2".', $row['provider_key'], $parentKey)
                        );
                }

                $dbRows[] = [
                    'scheme_code' => $scheme,
                    'provider_id' => $row['provider_id'],
                    'provider_code' => $row['provider_code'],
                    'provider_key' => $row['provider_key'],
                    'parent_id' => $parentId,
                    'depth' => $depth,
                    'name' => $row['name'],
                    'extension_names' => $row['extension_names'],
                    'status' => $row['status'],
                    'source_version' => $sourceVersion,
                    'synced_at' => gmdate('Y-m-d H:i:s'),
                ];
            }

            $affected += $this->unitResource->upsert($dbRows);
            $keyMap += $this->unitResource->fetchKeys($scheme);
        }

        $disabled = $this->unitResource->disableMissing($scheme, $keepKeys, $sourceVersion);

        return ['affected' => $affected, 'disabled' => $disabled];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @throws LocalizedException duplicate provider_key inside one snapshot
     */
    private function assertUniqueKeys(array $rows): void
    {
        $seen = [];
        foreach ($rows as $row) {
            $key = (string) $row['provider_key'];
            if (isset($seen[$key])) {
                throw new LocalizedException(__('Address data contains duplicate provider_key "%1".', $key));
            }
            $seen[$key] = true;
        }
    }
}
