<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\GhtkAddressMapImport;

use Magento\Framework\Exception\LocalizedException;

/**
 * Parses the GHTK mapping CSV into associative rows.
 *
 * Handles UTF-8 BOM and validates the canonical header. Returns raw string rows;
 * structural/type validation is performed by the Validator.
 */
class CsvReader
{
    /**
     * Canonical CSV header (replace-all).
     */
    public const HEADER = [
        'country_id',
        'region_id',
        'ward_id',
        'ghtk_province',
        'ghtk_district',
        'ghtk_ward',
        'is_active',
    ];

    /**
     * @return array<int, array<string, string>>
     * @throws LocalizedException On unreadable file, empty content, or wrong header.
     */
    public function read(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new LocalizedException(__('The uploaded GHTK mapping file is missing or unreadable.'));
        }

        $contents = (string) file_get_contents($filePath);
        if ($contents === '') {
            throw new LocalizedException(__('The uploaded GHTK mapping file is empty.'));
        }

        // Strip UTF-8 BOM if present.
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        $headerLine = array_shift($lines);
        if ($headerLine === null) {
            throw new LocalizedException(__('The uploaded GHTK mapping file has no header row.'));
        }

        $header = $this->parseLine($headerLine);
        $header = array_map(fn ($h) => trim((string) $h), $header);
        if ($header !== self::HEADER) {
            throw new LocalizedException(
                __('Invalid CSV header. Expected: %1', implode(',', self::HEADER))
            );
        }

        $rows = [];
        $columnCount = count(self::HEADER);
        foreach ($lines as $line) {
            if (trim((string) $line) === '') {
                continue;
            }
            $values = $this->parseLine($line);
            // Normalise to exactly the header column count: truncate overflow, pad shortfalls.
            $values = array_pad(array_slice($values, 0, $columnCount), $columnCount, '');
            $rows[] = array_combine(self::HEADER, $values);
        }

        return $rows;
    }

    /**
     * @return string[]
     */
    private function parseLine(string $line): array
    {
        return str_getcsv($line) ?: [];
    }
}
