<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Import;

use Magento\Framework\Exception\LocalizedException;

/**
 * TASK-J9AVGK — mapping CSV reader.
 * Header: source_scheme,source_code,target_scheme,target_code,relation_type
 */
class VnMappingReader
{
    public const HEADER = ['source_scheme', 'source_code', 'target_scheme', 'target_code', 'relation_type'];

    /** TASK-MD2BD3 v1.1 — optional curated directional primary flag appended to the base header. */
    public const HEADER_V11 = ['source_scheme', 'source_code', 'target_scheme', 'target_code', 'relation_type', 'is_primary'];

    private const BOM = "\xEF\xBB\xBF";

    /**
     * @return array<int, array{line: int, source_scheme: string, source_code: string, target_scheme: string, target_code: string, relation_type: string}>
     * @throws LocalizedException unreadable file / wrong header / wrong column count
     */
    public function read(string $path): array
    {
        if (!is_readable($path)) {
            throw new LocalizedException(__('Mapping file not readable: %1', $path));
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new LocalizedException(__('Unable to open mapping file: %1', $path));
        }

        try {
            return $this->parse($handle, $path);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function parse($handle, string $path): array
    {
        $rows = [];
        $line = 0;

        while (($raw = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $line++;
            if ($line === 1) {
                if (isset($raw[0])) {
                    $raw[0] = preg_replace('/^' . preg_quote(self::BOM, '/') . '/u', '', $raw[0]) ?? $raw[0];
                }
                // TASK-MD2BD3 v10: both the legacy 5-column header and the v1.1 header (with the
                // curated `is_primary` flag) are accepted — legacy files stay backward-safe.
                if ($raw !== self::HEADER && $raw !== self::HEADER_V11) {
                    throw new LocalizedException(
                        __('%1: wrong header. Expected "%2" or "%3", got "%4".', $path, implode(',', self::HEADER), implode(',', self::HEADER_V11), implode(',', $raw))
                    );
                }
                continue;
            }
            if (count($raw) === 1 && trim((string)$raw[0]) === '') {
                continue;
            }
            if (count($raw) !== count(self::HEADER) && count($raw) !== count(self::HEADER_V11)) {
                throw new LocalizedException(
                    __('%1 line %2: expected %3 or %4 columns, got %5.', $path, $line, count(self::HEADER), count(self::HEADER_V11), count($raw))
                );
            }

            $values = array_map(static fn (string $value): string => trim($value), $raw);
            $rows[] = [
                'line' => $line,
                'source_scheme' => $values[0],
                'source_code' => $values[1],
                'target_scheme' => $values[2],
                'target_code' => $values[3],
                'relation_type' => $values[4],
                'is_primary' => isset($values[5]) ? $values[5] : '0',
            ];
        }

        if ($rows === []) {
            throw new LocalizedException(__('Mapping file %1 contains no data rows.', $path));
        }

        return $rows;
    }
}
