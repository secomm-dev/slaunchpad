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
                if ($raw !== self::HEADER) {
                    throw new LocalizedException(
                        __('%1: wrong header. Expected "%2", got "%3".', $path, implode(',', self::HEADER), implode(',', $raw))
                    );
                }
                continue;
            }
            if (count($raw) === 1 && trim((string)$raw[0]) === '') {
                continue;
            }
            if (count($raw) !== count(self::HEADER)) {
                throw new LocalizedException(
                    __('%1 line %2: expected %3 columns, got %4.', $path, $line, count(self::HEADER), count($raw))
                );
            }

            [$sourceScheme, $sourceCode, $targetScheme, $targetCode, $relationType] = array_map(
                static fn (string $value): string => trim($value),
                $raw
            );
            $rows[] = [
                'line' => $line,
                'source_scheme' => $sourceScheme,
                'source_code' => $sourceCode,
                'target_scheme' => $targetScheme,
                'target_code' => $targetCode,
                'relation_type' => $relationType,
            ];
        }

        if ($rows === []) {
            throw new LocalizedException(__('Mapping file %1 contains no data rows.', $path));
        }

        return $rows;
    }
}
