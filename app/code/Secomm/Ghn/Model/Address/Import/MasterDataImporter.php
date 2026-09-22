<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Import;

use Magento\Framework\Exception\LocalizedException;
use Secomm\Ghn\Model\Address\Dataset\DatasetPaths;
use Secomm\Ghn\Model\Address\Dataset\Manifest;
use Secomm\Ghn\Model\Address\Dataset\MasterCsv;
use Secomm\Ghn\Model\Address\GhnSchemes;
use Secomm\Ghn\Model\Address\Sync\UnitPersister;

/**
 * SPEC-TASK-TBM30R §2/§8 — import GHN master data from deterministic CSV files (bundled bootstrap
 * dataset or an exported refresh). UPSERT-based; units missing from the imported snapshot become
 * DISABLED (never deleted) to preserve historical shipment references. Importing master data never
 * touches secomm_ghn_address_mapping (reviewed mapping decisions are separate, DEC-FEATFQWEQ3-002).
 */
class MasterDataImporter
{
    public function __construct(
        private readonly CsvReader $reader,
        private readonly Manifest $manifest,
        private readonly DatasetPaths $datasetPaths,
        private readonly UnitPersister $unitPersister
    ) {
    }

    /**
     * @param string|null $ghnScheme import one scheme or all approved GHN schemes
     * @return array<string, mixed>
     * @throws LocalizedException malformed dataset / checksum mismatch / missing parent
     */
    public function import(string $dir, ?string $ghnScheme, ?string $sourceVersion, bool $dryRun): array
    {
        $schemes = $this->resolveSchemes($ghnScheme);
        $manifestFile = $this->datasetPaths->manifestFile($dir);
        $manifest = $this->manifest->read($manifestFile);
        if ($manifest === null) {
            $manifest = [];
        }

        $reports = [];
        foreach ($schemes as $scheme) {
            $file = $this->datasetPaths->masterFile($dir, $scheme);
            $relative = 'master/' . $scheme . '.csv';
            $records = $this->reader->read($file, MasterCsv::HEADER);
            $this->manifest->validateFile($manifest, $file, $relative, count($records));

            $rows = [];
            foreach ($records as $record) {
                if ($record['scheme_code'] !== $scheme) {
                    throw new LocalizedException(
                        __('Dataset file %1 contains scheme_code "%2" — expected "%3".', $relative, $record['scheme_code'], $scheme)
                    );
                }
                GhnSchemes::assertKnown($scheme);
                $rows[] = MasterCsv::toNormalizedRow($record);
            }

            $version = $sourceVersion ?? (string) ($manifest['dataset_version'] ?? 'import:' . gmdate('Y-m-d'));
            $result = $this->unitPersister->persist($scheme, $rows, $version, $dryRun);

            $reports[$scheme] = [
                'scheme' => $scheme,
                'records' => count($records),
                'dry_run' => $dryRun,
                'affected' => $result['affected'],
                'disabled' => $result['disabled'],
                'source_version' => $version,
            ];
        }

        return ['dir' => $dir, 'manifest_version' => $manifest['dataset_version'] ?? null, 'schemes' => $reports];
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
                __('Unknown GHN scheme %1 — importable schemes: %2.', $ghnScheme, implode(', ', array_values($this->datasetPaths->schemePairs())))
            );
        }

        return [$ghnScheme];
    }
}
