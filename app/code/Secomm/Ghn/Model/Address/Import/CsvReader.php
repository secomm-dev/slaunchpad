<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Import;

use Magento\Framework\Exception\LocalizedException;

/**
 * SPEC-TASK-TBM30R §4/§5 — strict CSV reader for dataset files: UTF-8 (BOM tolerated and
 * stripped), fixed header contract, blank lines skipped, every cell kept verbatim as string
 * (GHN WardCode stays a string — never integer semantics). Fail loud on structural problems.
 */
class CsvReader
{
    /**
     * @param string $path dataset CSV path
     * @param array<int, string> $expectedHeader exact header column order
     * @return array<int, array<string, string>> rows keyed by header name (all values strings)
     * @throws LocalizedException unreadable / wrong header / row width mismatch
     */
    public function read(string $path, array $expectedHeader): array
    {
        if (!is_readable($path)) {
            throw new LocalizedException(__('Dataset file %1 is not readable.', $path));
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new LocalizedException(__('Unable to open dataset file %1.', $path));
        }

        try {
            $header = $this->readHeader($handle, $path);
            if ($header !== $expectedHeader) {
                throw new LocalizedException(
                    __('Dataset file %1 has header "%2" — expected "%3".', $path, implode(',', $header), implode(',', $expectedHeader))
                );
            }

            $rows = [];
            $line = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if ($row === [null] || $row === []) {
                    continue; // blank line
                }

                if (count($row) !== count($expectedHeader)) {
                    throw new LocalizedException(
                        __('Dataset file %1 line %2: expected %3 columns, found %4.', $path, $line, count($expectedHeader), count($row))
                    );
                }

                /** @var array<string, string> $record */
                $record = [];
                foreach ($expectedHeader as $index => $name) {
                    $record[$name] = trim((string) $row[$index]);
                }
                $rows[] = $record;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * Count data rows without full parsing (manifest validation).
     */
    public function countRows(string $path, array $expectedHeader): int
    {
        return count($this->read($path, $expectedHeader));
    }

    /**
     * @param resource $handle
     * @return array<int, string>
     */
    private function readHeader($handle, string $path): array
    {
        $first = (string) fgets($handle);
        // Strip UTF-8 BOM if present.
        if (str_starts_with($first, "\xEF\xBB\xBF")) {
            $first = substr($first, 3);
        }

        return array_map(static fn (string $name): string => trim($name), str_getcsv(trim($first, "\r\n")));
    }
}
