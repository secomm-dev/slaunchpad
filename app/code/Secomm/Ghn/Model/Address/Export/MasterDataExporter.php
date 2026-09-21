<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Export;

use Magento\Framework\Exception\LocalizedException;
use Secomm\Ghn\Model\Address\Dataset\DatasetPaths;
use Secomm\Ghn\Model\Address\Dataset\Manifest;
use Secomm\Ghn\Model\Address\Dataset\MasterCsv;
use Secomm\Ghn\Model\Address\Sync\MasterDataFetcher;
use Secomm\Ghn\Model\Config;

/**
 * SPEC-TASK-TBM30R §4 — deterministic GHN master-data export: GHN APIs → fetchers → validate →
 * structural normalization → fixed-schema CSV (+ manifest). Names are exported VERBATIM (never
 * rewritten into Secomm canonical names); no local entity_id; ordering scheme → level →
 * provider_key (byte-wise strcmp) so two exports of the same dataset are byte-identical.
 *
 * CLI-only path — never call this from an HTTP request (directive §3).
 */
class MasterDataExporter
{
    public function __construct(
        /** @var array<string, MasterDataFetcher> ghn scheme => fetcher (di.xml) */
        private readonly array $fetchers,
        private readonly DatasetPaths $datasetPaths,
        private readonly Manifest $manifest,
        private readonly Config $config
    ) {
    }

    /**
     * @param string|null $ghnScheme export one scheme or all approved GHN schemes
     * @return array{dir: string, dataset_version: string, files: array<string, array{scheme_code: string, record_count: int}>}
     * @throws LocalizedException unknown scheme / no fetcher / unwritable target
     */
    public function export(?string $ghnScheme, string $dir, ?string $datasetVersion = null): array
    {
        $schemes = $this->resolveSchemes($ghnScheme);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new LocalizedException(__('Unable to create export directory %1.', $dir));
        }

        $fileStats = [];
        $reportFiles = [];
        foreach ($schemes as $scheme) {
            $fetcher = $this->fetchers[$scheme]
                ?? throw new LocalizedException(__('No master-data fetcher registered for scheme %1.', $scheme));

            $rows = $fetcher->fetch($scheme);
            usort($rows, static function (array $a, array $b): int {
                return [(int) $a['depth'], (string) $a['provider_key']]
                    <=> [(int) $b['depth'], (string) $b['provider_key']];
            });

            $file = $this->datasetPaths->masterFile($dir, $scheme);
            $this->writeCsv($file, MasterCsv::HEADER, $rows, $scheme);

            $relative = $this->relativeMasterPath($scheme);
            $fileStats[$relative] = [
                'scheme_code' => $scheme,
                'record_count' => count($rows),
                'sha256' => (string) hash_file('sha256', $file),
            ];
            $reportFiles[$scheme] = ['scheme_code' => $scheme, 'record_count' => count($rows)];
        }

        $version = $datasetVersion ?? gmdate('Y.m.d');
        $this->manifest->write(
            $this->datasetPaths->manifestFile($dir),
            $this->manifest->build($version, gmdate('c'), $this->config->getEnvironment(), $fileStats)
        );

        return ['dir' => $dir, 'dataset_version' => $version, 'files' => $reportFiles];
    }

    /**
     * @return array<int, string>
     * @throws LocalizedException
     */
    private function resolveSchemes(?string $ghnScheme): array
    {
        if ($ghnScheme === null || $ghnScheme === '') {
            return array_values(array_unique(array_values($this->datasetPaths->schemePairs())));
        }

        if (!in_array($ghnScheme, $this->datasetPaths->schemePairs(), true)) {
            throw new LocalizedException(
                __('Unknown GHN scheme %1 — exportable schemes: %2.', $ghnScheme, implode(', ', array_values($this->datasetPaths->schemePairs())))
            );
        }

        return [$ghnScheme];
    }

    /**
     * @param array<int, array<string, mixed>> $rows normalized fetcher rows (already sorted)
     * @throws LocalizedException unwritable target
     */
    private function writeCsv(string $file, array $header, array $rows, string $scheme): void
    {
        $subDir = dirname($file);
        if (!is_dir($subDir) && !mkdir($subDir, 0775, true) && !is_dir($subDir)) {
            throw new LocalizedException(__('Unable to create export directory %1.', $subDir));
        }

        $handle = fopen($file, 'wb');
        if ($handle === false) {
            throw new LocalizedException(__('Unable to write export file %1.', $file));
        }

        try {
            fputcsv($handle, $header);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $scheme,
                    (string) $row['depth'],
                    (string) $row['provider_key'],
                    (string) ($row['provider_id'] ?? ''),
                    (string) ($row['provider_code'] ?? ''),
                    (string) ($row['parent_key'] ?? ''),
                    (string) $row['name'],
                    (string) ($row['extension_names'] ?? ''),
                    (string) $row['status'],
                ]);
            }
        } finally {
            fclose($handle);
        }
    }

    private function relativeMasterPath(string $ghnScheme): string
    {
        return 'master/' . $ghnScheme . '.csv';
    }
}
