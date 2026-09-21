<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Dataset;

/**
 * SPEC-TASK-TBM30R §4 — stable master-data CSV schema shared by exporter and importer.
 * Portable identity only; hierarchy reconstructable from parent_provider_key; GHN names verbatim.
 */
final class MasterCsv
{
    public const HEADER = [
        'scheme_code',
        'level',
        'provider_key',
        'provider_id',
        'provider_code',
        'parent_provider_key',
        'name',
        'extension_names',
        'status',
    ];

    private function __construct()
    {
    }

    /**
     * CSV record → normalized unit row (the MasterDataFetcher shape, reused by persistence).
     *
     * @param array<string, string> $record
     * @return array<string, mixed>
     */
    public static function toNormalizedRow(array $record): array
    {
        return [
            'provider_key' => $record['provider_key'],
            'provider_id' => $record['provider_id'] !== '' ? $record['provider_id'] : null,
            'provider_code' => $record['provider_code'] !== '' ? $record['provider_code'] : null,
            'parent_key' => $record['parent_provider_key'] !== '' ? $record['parent_provider_key'] : null,
            'depth' => (int) $record['level'],
            'name' => $record['name'],
            'extension_names' => $record['extension_names'] !== '' ? $record['extension_names'] : null,
            'status' => $record['status'],
        ];
    }
}
