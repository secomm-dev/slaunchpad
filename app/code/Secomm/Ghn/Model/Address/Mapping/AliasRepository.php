<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Mapping;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Exception\LocalizedException;

/**
 * SPEC-FEAT-FQWEQ3 §7 — curated aliases: the versioned, human-reviewed override layer of the
 * mapping pipeline ("manual/curated resolution → approved mapping"). Files are module-shipped
 * (`Files/ghn_mapping_aliases_{scheme}.csv`, header: secomm_unit_code,ghn_provider_key,note) so a
 * mapping rebuild is deterministic and reviewable in the repo. CSV must be valid UTF-8; a
 * malformed line fails LOUD (never silently skipped) — bad curation must surface.
 */
class AliasRepository
{
    private const FILE_FORMAT = 'ghn_mapping_aliases_%s.csv';

    /** @var array<string, array<string, string>> cache per scheme: unit_code => provider_key */
    private array $cache = [];

    public function __construct(private readonly ComponentRegistrar $componentRegistrar)
    {
    }

    /**
     * @return array<string, string> canonical unit_code => GHN provider_key
     *
     * @throws LocalizedException malformed alias file
     */
    public function getAliases(string $secommScheme): array
    {
        if (isset($this->cache[$secommScheme])) {
            return $this->cache[$secommScheme];
        }

        $path = $this->resolveFilePath($secommScheme);
        if ($path === null || !is_readable($path)) {
            return $this->cache[$secommScheme] = [];
        }

        return $this->cache[$secommScheme] = $this->parseFile($path);
    }

    /**
     * Parse one alias CSV. Public so the curation tooling/tests can validate a file directly.
     *
     * @return array<string, string> canonical unit_code => GHN provider_key
     *
     * @throws LocalizedException malformed alias file
     */
    public function parseFile(string $path): array
    {
        $aliases = [];
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new LocalizedException(__('Unable to open GHN alias file: %1', $path));
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || trim((string) $header[0]) !== 'secomm_unit_code') {
                throw new LocalizedException(
                    __('GHN alias file %1 must start with header "secomm_unit_code,ghn_provider_key,note".', $path)
                );
            }

            $line = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if ($row === [null] || $row === []) {
                    continue; // blank line
                }

                $unitCode = trim((string) ($row[0] ?? ''));
                $providerKey = trim((string) ($row[1] ?? ''));
                if ($unitCode === '' || $providerKey === '') {
                    throw new LocalizedException(
                        __('GHN alias file %1 line %2: secomm_unit_code and ghn_provider_key are required.', $path, $line)
                    );
                }

                $aliases[$unitCode] = $providerKey;
            }
        } finally {
            fclose($handle);
        }

        return $aliases;
    }

    private function resolveFilePath(string $secommScheme): ?string
    {
        $moduleDir = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Secomm_Ghn');

        $path = $moduleDir . '/Files/' . sprintf(self::FILE_FORMAT, $secommScheme);

        return is_file($path) ? $path : null;
    }
}
