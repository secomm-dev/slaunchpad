<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Dataset;

use Magento\Framework\Component\ComponentRegistrar;

/**
 * SPEC-TASK-TBM30R §2 — bundled dataset layout shipped with the module:
 *
 * ```
 * Secomm/Ghn/data/
 *   master/GHN_ADMIN_2025.csv
 *   master/GHN_ADMIN_PRE_2025.csv
 *   mapping/VN_ADMIN_2025_TO_GHN_ADMIN_2025.csv
 *   mapping/VN_ADMIN_PRE_2025_TO_GHN_ADMIN_PRE_2025.csv
 *   manifest.json
 * ```
 *
 * Files carry PORTABLE identity only (scheme_code + unit_code ↔ scheme_code + provider_key) —
 * never entity_id / region_id / city_id (DEC-FEATFQWEQ3-002).
 */
final class DatasetPaths
{
    public const MASTER_FILE_FORMAT = 'master/%s.csv';
    public const MAPPING_FILE_FORMAT = 'mapping/%s_TO_%s.csv';
    public const MANIFEST_FILE = 'manifest.json';

    /** Canonical secomm scheme → GHN scheme (the two approved pairs). */
    public const SCHEME_PAIRS = [
        'VN_ADMIN_2025' => 'GHN_ADMIN_2025',
        'VN_ADMIN_PRE_2025' => 'GHN_ADMIN_PRE_2025',
    ];

    public function __construct(private readonly ComponentRegistrar $componentRegistrar)
    {
    }

    /**
     * Module-shipped dataset root (bundled artifacts, refresh-committed after offline review).
     */
    public function bundledDir(): string
    {
        return $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Secomm_Ghn') . '/data';
    }

    public function masterFile(string $dir, string $ghnScheme): string
    {
        return $dir . '/' . sprintf(self::MASTER_FILE_FORMAT, $ghnScheme);
    }

    public function mappingFile(string $dir, string $secommScheme, string $ghnScheme): string
    {
        return $dir . '/' . sprintf(self::MAPPING_FILE_FORMAT, $secommScheme, $ghnScheme);
    }

    public function manifestFile(string $dir): string
    {
        return $dir . '/' . self::MANIFEST_FILE;
    }

    /**
     * @return array<string, string> secomm_scheme => ghn_scheme (the approved pairs, stable order)
     */
    public function schemePairs(): array
    {
        return self::SCHEME_PAIRS;
    }
}
