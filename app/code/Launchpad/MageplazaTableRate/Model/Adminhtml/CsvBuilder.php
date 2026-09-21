<?php
/*
 * TASK-JZXM66 — CSV download content builder (shared by the City Reference and the Import
 * Template downloads): UTF-8 + BOM for Excel, fputcsv enclosure/escaping, LF rows.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model\Adminhtml;

/**
 * Builds in-memory CSV strings for admin download responses (`Result\Raw`). Rows are plain
 * string arrays; the first row is the header. Values containing commas / quotes / UTF-8 are
 * escaped by fputcsv exactly as any spreadsheet expects — the builders never hand-roll
 * escaping.
 */
class CsvBuilder
{
    public const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * @param array<int, array<int, string>> $rows header row first
     * @return string UTF-8 CSV content with leading BOM ('' when there are no rows)
     */
    public function toCsvString(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }

        foreach ($rows as $row) {
            fputcsv($stream, array_map('strval', $row), ',', '"', '\\');
        }
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return self::UTF8_BOM . $csv;
    }
}
