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
use Secomm\Ghn\Model\Address\Dataset\MappingCsv;
use Secomm\Ghn\Model\Cache\MappingCache;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\Ghn\Model\ResourceModel\AddressMapping;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;

/**
 * SPEC-TASK-TBM30R §5/§8 — THE single production path that writes APPROVED canonical↔GHN
 * mappings (DEC-FEATFQWEQ3-002 §1). Offline-reviewed CSV → strict validation → UPSERT of the
 * APPROVED rows only. REVIEW_REQUIRED / UNRESOLVED / AMBIGUOUS rows are skipped and counted —
 * they live in the offline file, never in the runtime table. Any structural or referential
 * problem rejects the WHOLE import (fail loud, no partial write). Refresh is upsert-based —
 * existing reviewed rows not present in the file are left untouched (no truncation).
 */
class MappingImporter
{
    public function __construct(
        private readonly CsvReader $reader,
        private readonly Manifest $manifest,
        private readonly DatasetPaths $datasetPaths,
        private readonly VnAddressUnitProviderInterface $unitProvider,
        private readonly AddressUnit $unitResource,
        private readonly AddressMapping $mappingResource,
        private readonly MappingCache $mappingCache
    ) {
    }

    /**
     * @param string|null $secommScheme import one scheme pair or both approved pairs
     * @return array<string, mixed>
     * @throws LocalizedException malformed dataset / referential failure / checksum mismatch
     */
    public function import(string $dir, ?string $secommScheme, ?string $verifiedAt, bool $dryRun): array
    {
        $pairs = $this->datasetPaths->schemePairs();
        if ($secommScheme !== null && $secommScheme !== '') {
            if (!isset($pairs[$secommScheme])) {
                throw new LocalizedException(__('Unknown canonical scheme %1 — expected one of: %2.', $secommScheme, implode(', ', array_keys($pairs))));
            }
            $pairs = [$secommScheme => $pairs[$secommScheme]];
        }

        $manifest = $this->manifest->read($this->datasetPaths->manifestFile($dir)) ?? [];
        $verifiedAt ??= gmdate('Y-m-d H:i:s');

        $reports = [];
        $allApprovedRows = [];
        foreach ($pairs as $secommScheme => $ghnScheme) {
            $file = $this->datasetPaths->mappingFile($dir, $secommScheme, $ghnScheme);
            $relative = sprintf('mapping/%s_TO_%s.csv', $secommScheme, $ghnScheme);
            if (!is_file($file)) {
                throw new LocalizedException(__('Mapping dataset file %1 is missing.', $relative));
            }

            $records = $this->reader->read($file, MappingCsv::HEADER);
            $this->manifest->validateFile($manifest, $file, $relative, count($records));

            $report = $this->importPair($secommScheme, $ghnScheme, $records, $verifiedAt, $dryRun, $allApprovedRows);
            $reports[$secommScheme] = $report;
        }

        if (!$dryRun && $allApprovedRows !== []) {
            // Refresh = UPSERT of the reviewed rows; existing reviewed rows not in the file stay
            // (no truncate — DEC-FEATFQWEQ3-002 §6).
            $this->mappingResource->upsert($allApprovedRows);
            $this->mappingCache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, [MappingCache::CACHE_TAG]);
        }

        return ['dir' => $dir, 'dry_run' => $dryRun, 'schemes' => $reports];
    }

    /**
     * @param array<int, array<string, string>> $records
     * @param array<int, array<string, mixed>> $allApprovedRows
     * @return array<string, int>
     * @throws LocalizedException on ANY invalid row — whole pair rejected
     */
    private function importPair(
        string $secommScheme,
        string $ghnScheme,
        array $records,
        string $verifiedAt,
        bool $dryRun,
        array &$allApprovedRows
    ): array {
        $skipped = [
            MappingCsv::STATUS_REVIEW_REQUIRED => 0,
            MappingCsv::STATUS_UNRESOLVED => 0,
            MappingCsv::STATUS_AMBIGUOUS => 0,
        ];
        $seenCanonical = [];
        /** @var array<string, string> $targets ghn_provider_key => secomm_unit_code (collision detection) */
        $targets = [];
        $approvedRows = [];

        foreach ($records as $index => $record) {
            $line = $index + 2; // + header
            $unitCode = $record['secomm_unit_code'];
            $providerKey = $record['ghn_provider_key'];
            $status = strtoupper($record['mapping_status']);

            // Scheme columns must match the file's declared pair (no cross-pair mixing).
            if ($record['secomm_scheme_code'] !== $secommScheme || $record['ghn_scheme_code'] !== $ghnScheme) {
                throw new LocalizedException(
                    __('Mapping file line %1: scheme columns "%2"/"%3" do not match file pair "%4"/"%5".', $line, $record['secomm_scheme_code'], $record['ghn_scheme_code'], $secommScheme, $ghnScheme)
                );
            }

            if (!in_array($status, MappingCsv::FILE_STATUSES, true)) {
                throw new LocalizedException(__('Mapping file line %1: unknown mapping_status "%2".', $line, $record['mapping_status']));
            }

            if (isset($seenCanonical[$unitCode])) {
                throw new LocalizedException(__('Mapping file line %1: duplicate canonical unit "%2".', $line, $unitCode));
            }
            $seenCanonical[$unitCode] = true;

            if ($status !== MappingCsv::STATUS_APPROVED) {
                $skipped[$status]++;
                continue;
            }

            // APPROVED rows must carry a valid method and resolvable identities on BOTH sides.
            if (!in_array($record['mapping_method'], MappingCsv::METHODS, true)) {
                throw new LocalizedException(
                    __('Mapping file line %1: APPROVED row requires mapping_method one of %2.', $line, implode('/', MappingCsv::METHODS))
                );
            }
            if ($providerKey === '') {
                throw new LocalizedException(__('Mapping file line %1: APPROVED row requires ghn_provider_key.', $line));
            }
            if ($this->unitProvider->getUnit($secommScheme, $unitCode) === null) {
                throw new LocalizedException(__('Mapping file line %1: canonical unit "%2" does not exist (dangling mapping).', $line, $unitCode));
            }
            $unit = $this->unitResource->fetchUnitByKey($ghnScheme, $providerKey);
            if ($unit === null) {
                throw new LocalizedException(__('Mapping file line %1: GHN provider identity "%2/%3" does not exist (dangling mapping).', $line, $ghnScheme, $providerKey));
            }
            if (($targets[$providerKey] ?? null) !== null) {
                throw new LocalizedException(
                    __('Mapping file line %1: GHN unit "%2" is already targeted by canonical unit "%3" (conflicting mapping).', $line, $providerKey, $targets[$providerKey])
                );
            }
            $targets[$providerKey] = $unitCode;

            $approvedRows[] = [
                'secomm_scheme_code' => $secommScheme,
                'secomm_unit_code' => $unitCode,
                'ghn_address_unit_id' => (int) $unit['entity_id'],
                'mapping_method' => $record['mapping_method'],
                'mapping_status' => MappingCsv::STATUS_APPROVED,
                'verified_at' => $verifiedAt,
            ];
        }

        if (!$dryRun) {
            // Local entity IDs resolved here — the only place where file identity meets DB identity.
            foreach ($approvedRows as $row) {
                $allApprovedRows[] = $row;
            }
        }

        return [
            'total_rows' => count($records),
            'approved' => count($approvedRows),
            'skipped_review_required' => $skipped[MappingCsv::STATUS_REVIEW_REQUIRED],
            'skipped_unresolved' => $skipped[MappingCsv::STATUS_UNRESOLVED],
            'skipped_ambiguous' => $skipped[MappingCsv::STATUS_AMBIGUOUS],
        ];
    }
}
