<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Mapping;

use Magento\Framework\Exception\LocalizedException;
use Secomm\Ghn\Model\Address\Dataset\MappingCsv;
use Secomm\Ghn\Model\Address\Dataset\MasterCsv;
use Secomm\Ghn\Model\Address\Import\CsvReader;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * DEC-FEATFQWEQ3-002 / SPEC-TASK-TBM30R §11 — candidate-generation tooling (legacy matcher
 * classification A). Runs the deterministic matcher and writes an IMPORT-COMPATIBLE workfile:
 * exact/alias suggestions become REVIEW_REQUIRED (never APPROVED), ambiguous stays AMBIGUOUS with
 * candidates in the note, unmapped stays UNRESOLVED. The human/AI reviewer flips statuses offline;
 * only then does the mapping importer activate APPROVED rows. This class NEVER writes the
 * runtime mapping table.
 *
 * SPEC-TASK-6TNKDH §12 — additionally emits a review artifact (GHN_ADDRESS_MAPPING_REVIEW.csv)
 * carrying hierarchy context on BOTH sides for every non-trivial row, so offline review can be
 * evidence-driven. GHN rows may come from the live DB (synced) or from an exported master dataset
 * directory (offline authoring path, no API call).
 */
class MappingSuggester
{
    /** Review artifact schema (SPEC-TASK-6TNKDH §12). */
    public const REVIEW_HEADER = [
        'secomm_scheme',
        'secomm_unit_code',
        'secomm_parent_path',
        'secomm_name',
        'ghn_scheme',
        'candidate_provider_keys',
        'candidate_paths',
        'candidate_names',
        'reason',
        'status',
        'note',
    ];

    public function __construct(
        private readonly MappingMatcher $matcher,
        private readonly AddressUnit $unitResource,
        private readonly CsvReader $csvReader,
        private readonly VnAddressUnitProviderInterface $unitProvider
    ) {
    }

    /**
     * Suggest from the LIVE DB master data (post sync).
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function suggest(string $secommScheme, string $outputFile, ?string $reviewFile = null): array
    {
        $ghnScheme = $this->assertScheme($secommScheme);
        $ghnUnits = $this->unitResource->fetchByScheme($ghnScheme);
        if ($ghnUnits === []) {
            throw new LocalizedException(
                __('GHN master data for %1 is empty — sync or import a master dataset first.', $ghnScheme)
            );
        }

        return $this->build($secommScheme, $ghnScheme, $ghnUnits, $outputFile, $reviewFile);
    }

    /**
     * Suggest from an EXPORTED master dataset directory (offline authoring — no GHN API call).
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function suggestFromExport(string $secommScheme, string $exportDir, string $outputFile, ?string $reviewFile = null): array
    {
        $ghnScheme = $this->assertScheme($secommScheme);
        $file = $exportDir . '/master/' . $ghnScheme . '.csv';
        $records = $this->csvReader->read($file, MasterCsv::HEADER);
        $ghnUnits = [];
        foreach ($records as $record) {
            $row = MasterCsv::toNormalizedRow($record);
            // Matcher groups by opaque entity/parent ids (DB shape). For file-based suggestions the
            // portable keys stand in for those ids — the matcher never persists them anywhere.
            $row['entity_id'] = $row['provider_key'];
            $row['parent_id'] = $row['parent_key'];
            $ghnUnits[] = $row;
        }
        if ($ghnUnits === []) {
            throw new LocalizedException(__('Exported master dataset %1 is empty.', $file));
        }

        return $this->build($secommScheme, $ghnScheme, $ghnUnits, $outputFile, $reviewFile);
    }

    /**
     * @param array<int, array<string, mixed>> $ghnUnits
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function build(string $secommScheme, string $ghnScheme, array $ghnUnits, string $outputFile, ?string $reviewFile): array
    {
        $decisions = $this->matcher->match($secommScheme, $ghnUnits);

        ksort($decisions);
        $counts = [
            MappingCsv::STATUS_REVIEW_REQUIRED => 0,
            MappingCsv::STATUS_AMBIGUOUS => 0,
            MappingCsv::STATUS_UNRESOLVED => 0,
        ];

        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new LocalizedException(__('Unable to create directory %1 for the suggester workfile.', $outputDir));
        }

        $handle = fopen($outputFile, 'wb');
        if ($handle === false) {
            throw new LocalizedException(__('Unable to write suggester workfile %1.', $outputFile));
        }

        try {
            fputcsv($handle, MappingCsv::HEADER);
            foreach ($decisions as $unitCode => $decision) {
                [$status, $providerKey, $method, $note] = $this->toWorkfileRow($decision);
                $counts[$status]++;
                fputcsv($handle, [
                    $secommScheme,
                    $unitCode,
                    $ghnScheme,
                    $providerKey,
                    $method,
                    $status,
                    $note,
                ]);
            }
        } finally {
            fclose($handle);
        }

        $reviewCount = 0;
        if ($reviewFile !== null) {
            $reviewCount = $this->writeReviewArtifact($secommScheme, $ghnScheme, $decisions, $ghnUnits, $reviewFile);
        }

        return [
            'output' => $outputFile,
            'review_output' => $reviewFile,
            'review_rows' => $reviewCount,
            'total' => count($decisions),
            'review_required' => $counts[MappingCsv::STATUS_REVIEW_REQUIRED],
            'ambiguous' => $counts[MappingCsv::STATUS_AMBIGUOUS],
            'unresolved' => $counts[MappingCsv::STATUS_UNRESOLVED],
        ];
    }

    /**
     * Review artifact with both-side hierarchy context for EVERY non-trivial row (§12).
     *
     * @param array<string, array<string, mixed>> $decisions
     * @param array<int, array<string, mixed>> $ghnUnits
     * @throws LocalizedException
     */
    private function writeReviewArtifact(
        string $secommScheme,
        string $ghnScheme,
        array $decisions,
        array $ghnUnits,
        string $reviewFile
    ): int {
        $keyMap = [];
        foreach ($ghnUnits as $unit) {
            $keyMap[(string) $unit['provider_key']] = $unit;
        }

        $handle = fopen($reviewFile, 'wb');
        if ($handle === false) {
            throw new LocalizedException(__('Unable to write review artifact %1.', $reviewFile));
        }

        $count = 0;
        try {
            fputcsv($handle, self::REVIEW_HEADER);
            foreach ($decisions as $unitCode => $decision) {
                $status = (string) $decision['status'];
                if ($status === MappingMatcher::STATUS_APPROVED) {
                    $reason = 'exact-name';
                } elseif ($status === MappingMatcher::STATUS_AMBIGUOUS) {
                    $reason = 'ambiguous';
                } elseif ($status === MappingMatcher::STATUS_ALIAS_INVALID) {
                    $reason = 'alias-invalid';
                } else {
                    $reason = 'unmapped';
                }

                $candidateKeys = array_map('strval', $decision['candidates'] ?? []);
                [$candidatePaths, $candidateNames] = $this->describeCandidates($keyMap, $candidateKeys);

                fputcsv($handle, [
                    $secommScheme,
                    $unitCode,
                    $this->canonicalParentPath($secommScheme, (string) ($decision['secomm_unit_code'] ?? $unitCode)),
                    (string) $decision['name'],
                    $ghnScheme,
                    implode(' | ', $candidateKeys),
                    implode(' | ', $candidatePaths),
                    implode(' | ', $candidateNames),
                    $reason,
                    $this->reviewStatus($status),
                    (string) ($decision['method'] ?? ''),
                ]);
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    /**
     * GHN candidate context: full provider path (province / district / ward names) per key.
     *
     * @param array<string, array<string, mixed>> $keyMap
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function describeCandidates(array $keyMap, array $candidateKeys): array
    {
        $paths = [];
        $names = [];
        foreach ($candidateKeys as $key) {
            $unit = $keyMap[$key] ?? null;
            if ($unit === null) {
                $paths[] = $key;
                $names[] = '';
                continue;
            }

            $names[] = (string) $unit['name'];
            $segments = [(string) $unit['name']];
            $parentKey = $unit['parent_key'] ?? null;
            $guard = 0;
            while ($parentKey !== null && isset($keyMap[$parentKey]) && $guard++ < 3) {
                array_unshift($segments, (string) $keyMap[$parentKey]['name']);
                $parentKey = $keyMap[$parentKey]['parent_key'] ?? null;
            }
            $paths[] = implode(' / ', $segments);
        }

        return [$paths, $names];
    }

    /**
     * Canonical hierarchy path (region / district / ward names) for the review artifact.
     */
    private function canonicalParentPath(string $secommScheme, string $unitCode): string
    {
        $segments = [];
        $code = $unitCode;
        $guard = 0;
        while ($code !== null && $code !== '' && $guard++ < 3) {
            $unit = $this->unitProvider->getUnit($secommScheme, $code);
            if ($unit === null) {
                break;
            }
            array_unshift($segments, $unit->getNameVi());
            $code = (string) $unit->getParentCode();
        }

        return implode(' / ', $segments);
    }

    private function reviewStatus(string $matcherStatus): string
    {
        return match ($matcherStatus) {
            MappingMatcher::STATUS_APPROVED => MappingCsv::STATUS_REVIEW_REQUIRED,
            MappingMatcher::STATUS_AMBIGUOUS => MappingCsv::STATUS_AMBIGUOUS,
            MappingMatcher::STATUS_ALIAS_INVALID => MappingCsv::STATUS_UNRESOLVED,
            default => MappingCsv::STATUS_UNRESOLVED,
        };
    }

    private function assertScheme(string $secommScheme): string
    {
        return MappingMatcher::SCHEME_PAIRS[$secommScheme]
            ?? throw new LocalizedException(__('Unknown canonical scheme %1 for GHN mapping.', $secommScheme));
    }

    /**
     * Matcher decision → [status, provider_key, method, note]. Suggestions are NEVER approved.
     *
     * @param array<string, mixed> $decision
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function toWorkfileRow(array $decision): array
    {
        $status = (string) $decision['status'];

        return match ($status) {
            MappingMatcher::STATUS_APPROVED => [
                MappingCsv::STATUS_REVIEW_REQUIRED,
                (string) ($decision['chosen_provider_key'] ?? ''),
                (string) ($decision['method'] ?? ''),
                sprintf(
                    'suggested by %s name match — review before approving',
                    (string) ($decision['match_tier'] ?? 'name') === 'name_prefixless' ? 'prefixless' : 'exact'
                ),
            ],
            MappingMatcher::STATUS_AMBIGUOUS => [
                MappingCsv::STATUS_AMBIGUOUS,
                '',
                '',
                'candidates: ' . implode(' | ', array_map('strval', $decision['candidates'] ?? [])),
            ],
            MappingMatcher::STATUS_ALIAS_INVALID => [
                MappingCsv::STATUS_UNRESOLVED,
                (string) ($decision['chosen_provider_key'] ?? ''),
                '',
                'alias outside mapped parent scope',
            ],
            default => [MappingCsv::STATUS_UNRESOLVED, '', '', ''],
        };
    }
}
