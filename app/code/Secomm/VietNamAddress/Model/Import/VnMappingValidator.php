<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Import;

use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-J9AVGK — mapping contract validation. Pure checks + unit-table lookups; no writes.
 *
 * Errors (STOP_ON_ERROR): unknown scheme, same-scheme edge, bad relation type, duplicate
 * edge, empty fields, orphan codes (not present in secomm_vietnam_address_unit for their
 * scheme). Warnings (informational): reverse ambiguity — a target code receiving more than
 * one distinct source per scheme pair (expected for MERGED_INTO; consumers must treat the
 * reverse resolution as AMBIGUOUS).
 */
class VnMappingValidator
{
    public const RELATION_TYPES = ['SAME_AS', 'RENAMED_TO', 'MERGED_INTO', 'SPLIT_INTO'];

    public function __construct(
        private readonly VnAddressUnitProviderInterface $unitProvider
    ) {
    }

    /**
     * @param array<int, array<string, string|int>> $rows rows from VnMappingReader::read()
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    public function validate(array $rows): array
    {
        $errors = [];
        $warnings = [];
        $seenEdges = [];
        $reverseIncoming = [];

        foreach ($rows as $row) {
            $line = (int)$row['line'];

            $hasEmpty = false;
            foreach (['source_scheme', 'source_code', 'target_scheme', 'target_code', 'relation_type'] as $field) {
                if ((string)$row[$field] === '') {
                    $errors[] = sprintf('Line %d: empty %s.', $line, $field);
                    $hasEmpty = true;
                }
            }
            if ($hasEmpty) {
                continue; // no point checking semantics of an incomplete row
            }

            // TASK-MD2BD3 v1.1 — curated directional primary flag (optional, default 0).
            $isPrimary = isset($row['is_primary']) ? (string)$row['is_primary'] : '';
            if ($isPrimary !== '' && !in_array($isPrimary, ['0', '1'], true)) {
                $errors[] = sprintf('Line %d: invalid is_primary "%s" (expected 0 or 1).', $line, $isPrimary);
            }

            if (!VnSchemes::exists((string)$row['source_scheme'])) {
                $errors[] = sprintf('Line %d: unknown source_scheme "%s".', $line, $row['source_scheme']);
            }
            if (!VnSchemes::exists((string)$row['target_scheme'])) {
                $errors[] = sprintf('Line %d: unknown target_scheme "%s".', $line, $row['target_scheme']);
            }
            if ($row['source_scheme'] === $row['target_scheme']) {
                $errors[] = sprintf('Line %d: source_scheme and target_scheme are identical ("%s").', $line, $row['source_scheme']);
            }
            if (!in_array((string)$row['relation_type'], self::RELATION_TYPES, true)) {
                $errors[] = sprintf(
                    'Line %d: relation_type "%s" invalid (expected one of %s).',
                    $line,
                    $row['relation_type'],
                    implode('|', self::RELATION_TYPES)
                );
            }

            $edge = $row['source_scheme'] . '|' . $row['source_code'] . '|' . $row['target_scheme'] . '|' . $row['target_code'];
            if (isset($seenEdges[$edge])) {
                $errors[] = sprintf('Line %d: duplicate edge "%s" (first at line %d).', $line, $edge, $seenEdges[$edge]);
            }
            $seenEdges[$edge] = $line;

            if (VnSchemes::exists((string)$row['source_scheme'])
                && $this->unitProvider->getUnit((string)$row['source_scheme'], (string)$row['source_code']) === null
            ) {
                $errors[] = sprintf('Line %d: orphan source code "%s" not in unit table for %s.', $line, $row['source_code'], $row['source_scheme']);
            }
            if (VnSchemes::exists((string)$row['target_scheme'])
                && $this->unitProvider->getUnit((string)$row['target_scheme'], (string)$row['target_code']) === null
            ) {
                $errors[] = sprintf('Line %d: orphan target code "%s" not in unit table for %s.', $line, $row['target_code'], $row['target_scheme']);
            }

            $reverseKey = $row['target_scheme'] . '|' . $row['target_code'] . '|' . $row['source_scheme'];
            $reverseIncoming[$reverseKey][$row['source_code']] = true;
        }

        foreach ($reverseIncoming as $key => $sources) {
            if (count($sources) > 1) {
                [$targetScheme, $targetCode, $sourceScheme] = explode('|', $key);
                $warnings[] = sprintf(
                    'Reverse ambiguity: %s:%s resolves back to %d %s units (%s) — reverse resolution is AMBIGUOUS.',
                    $targetScheme,
                    $targetCode,
                    count($sources),
                    $sourceScheme,
                    implode(', ', array_keys($sources))
                );
            }
        }

        // TASK-MD2BD3 v1.1 — curated directional primary must be UNIQUE per resolution key
        // (source_scheme + source_code + target_scheme). >1 = DATA_INTEGRITY_DEFECT (fail-loud).
        $primaryCount = [];
        foreach ($rows as $row) {
            if (((string)($row['is_primary'] ?? '0')) !== '1') {
                continue;
            }
            $key = $row['source_scheme'] . '|' . $row['source_code'] . '|' . $row['target_scheme'];
            $primaryCount[$key] = ($primaryCount[$key] ?? 0) + 1;
        }
        foreach ($primaryCount as $key => $count) {
            if ($count > 1) {
                $errors[] = sprintf(
                    'Duplicate curated primary for resolution key "%s" (%d rows) — only one is allowed.',
                    $key,
                    $count
                );
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
