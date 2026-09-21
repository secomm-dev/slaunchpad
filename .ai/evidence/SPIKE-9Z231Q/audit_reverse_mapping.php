<?php
/**
 * SPIKE-9Z231Q — Reverse mapping audit: VN_ADMIN_2025 → VN_ADMIN_PRE_2025.
 *
 * Read-only analysis of the canonical dataset (Secomm_VietNamAddress/Files):
 *   - VN_ADMIN_2025_import.csv            (3,321 current wards, 2-level)
 *   - VN_ADMIN_PRE_2025_import.csv        (old districts + wards, 3-level)
 *   - VN_ADMIN_PRE_2025_TO_2025_mapping.csv (authored PRE→2025 edges)
 *
 * Classifies every current (2025) ward for the REVERSE direction:
 *   ONE_TO_ONE (deterministic) / ONE_TO_MANY (ambiguous) / NO_MATCH (fail closed)
 * plus INVALID_MAPPING rows, district-information loss, province-level stats.
 *
 * Output: stdout summary + JSON at <script dir>/reverse-mapping-stats.json
 *
 * SPIKE evidence scope — NOT production code; never shipped in app/code.
 */

declare(strict_types=1);

$filesDir = '/var/www/html/slaunchpad/app/code/Secomm/VietNamAddress/Files';

/** @return list<list<string>> rows without header, BOM stripped */
function readCsv(string $path): array
{
    $raw = (string)file_get_contents($path);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $lines = preg_split("/\r\n|\n|\r/", trim($raw));
    array_shift($lines); // header
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $rows[] = str_getcsv($line);
    }
    return $rows;
}

// ---- Load datasets -----------------------------------------------------------
$current = readCsv("$filesDir/VN_ADMIN_2025_import.csv");       // region_code,region_vi,region_en,code,parent_code,name_vi,name_en
$legacy  = readCsv("$filesDir/VN_ADMIN_PRE_2025_import.csv");
$edges   = readCsv("$filesDir/VN_ADMIN_PRE_2025_TO_2025_mapping.csv"); // source_scheme,source_code,target_scheme,target_code,relation_type

$currentWards = []; // code => [region_code, name_vi]
foreach ($current as $r) {
    $currentWards[$r[3]] = ['region' => $r[0], 'name' => $r[5]];
}

// Legacy: districts have empty parent_code; wards point at a district code.
$legacyRows = [];   // code => [region, parent, name]
foreach ($legacy as $r) {
    $legacyRows[$r[3]] = ['region' => $r[0], 'parent' => $r[4] !== '' ? $r[4] : null, 'name' => $r[5]];
}
$legacyWardToDistrict = [];
foreach ($legacyRows as $code => $row) {
    $legacyWardToDistrict[$code] = $row['parent']; // null for district-level rows
}

// ---- Analyse edges -----------------------------------------------------------
$invalid = [];
$edgesByTarget = [];   // 2025 ward code => list of PRE ward source codes (ward-level only)
$edgesByTargetAll = [];
$relationStats = [];
$seenPairs = [];
$duplicateEdges = 0;
$regionEdges = [];

foreach ($edges as $i => $r) {
    $line = $i + 2; // +header, 1-indexed
    [$srcScheme, $srcCode, $tgtScheme, $tgtCode, $rel] = array_pad($r, 5, '');
    $relationStats[$rel] = ($relationStats[$rel] ?? 0) + 1;

    if (isset($seenPairs["$srcCode>$tgtCode"])) {
        $duplicateEdges++;
        $invalid[] = ['line' => $line, 'reason' => 'duplicate edge', 'row' => $r];
        continue;
    }
    $seenPairs["$srcCode>$tgtCode"] = true;

    if ($srcScheme !== 'VN_ADMIN_PRE_2025' || $tgtScheme !== 'VN_ADMIN_2025') {
        $invalid[] = ['line' => $line, 'reason' => 'unexpected scheme value', 'row' => $r];
        continue;
    }
    if ($srcCode === '' || $tgtCode === '' || $rel === '') {
        $invalid[] = ['line' => $line, 'reason' => 'empty required field', 'row' => $r];
        continue;
    }
    if ($srcCode === $tgtCode && str_starts_with($srcCode, 'VNAP25')) {
        $invalid[] = ['line' => $line, 'reason' => 'self reference (ward/district level)', 'row' => $r];
        continue;
    }

    $srcIsLegacyUnit = isset($legacyRows[$srcCode]);
    $tgtIsCurrentWard = isset($currentWards[$tgtCode]);

    if (str_starts_with($srcCode, 'VN-')) {
        $regionEdges[] = ['src' => $srcCode, 'tgt' => $tgtCode, 'rel' => $rel];
        continue; // region-level edge — handled separately
    }
    if (!$srcIsLegacyUnit) {
        $invalid[] = ['line' => $line, 'reason' => "source code not in PRE_2025 dataset: $srcCode", 'row' => $r];
        continue;
    }
    if (!$tgtIsCurrentWard) {
        $invalid[] = ['line' => $line, 'reason' => "target code not in 2025 dataset: $tgtCode", 'row' => $r];
        continue;
    }

    $edgesByTargetAll[$tgtCode][] = $srcCode;
    if (isset($legacyWardToDistrict[$srcCode]) && $legacyWardToDistrict[$srcCode] !== null) {
        $edgesByTarget[$tgtCode][] = $srcCode; // ward-level source (has a district parent)
    }
}

// ---- Classify each 2025 ward (reverse direction) -----------------------------
$oneToOne = $oneToMany = $noMatch = 0;
$oneToManySameDistrict = $oneToManyCrossDistrict = 0;
$districtLossWards = [];      // 2025 ward => distinct legacy districts spanned
$multiProvinceWards = [];     // 2025 ward => distinct legacy provinces spanned
$provinceAmbiguity = [];      // 2025 province => [distinct legacy provinces, wards]
$examplesMerges = [];
$examplesNoMatch = [];

foreach ($currentWards as $code => $meta) {
    $sources = array_values(array_unique($edgesByTarget[$code] ?? []));
    $n = count($sources);

    $provCount = [];
    if ($n > 0) {
        $districts = [];
        foreach ($sources as $s) {
            $d = $legacyWardToDistrict[$s] ?? null;
            if ($d !== null) {
                $districts[$d] = true;
            }
            $prov = $legacyRows[$s]['region'] ?? null;
            if ($prov !== null) {
                $provCount[$prov] = true;
            }
        }
        $districts = array_keys($districts);
        $provCount = array_keys($provCount);
        if (count($districts) > 1) {
            $districtLossWards[$code] = $districts;
        }
        if (count($provCount) > 1) {
            $multiProvinceWards[$code] = $provCount;
        }
        foreach ($provCount as $p) {
            $provinceAmbiguity[$p]['wards'] = ($provinceAmbiguity[$p]['wards'] ?? 0) + 1;
        }
    }

    if ($n === 0) {
        $noMatch++;
        if (count($examplesNoMatch) < 10) {
            $examplesNoMatch[] = [
                'code' => $code, 'name' => $meta['name'], 'province' => $meta['region'],
            ];
        }
        continue;
    }
    if ($n === 1) {
        $oneToOne++;
        continue;
    }

    $oneToMany++;
    $districts = [];
    foreach ($sources as $s) {
        $d = $legacyWardToDistrict[$s] ?? null;
        if ($d !== null) {
            $districts[$d] = true;
        }
    }
    $sameDistrict = count($districts) === 1;
    if ($sameDistrict) {
        $oneToManySameDistrict++;
    } else {
        $oneToManyCrossDistrict++;
    }
    $examplesMerges[] = [
        'target' => ['code' => $code, 'name' => $meta['name'], 'province' => $meta['region']],
        'source_count' => $n,
        'districts_spanned' => count($districts),
        'provinces_spanned' => count($provCount),
    ];
}

usort($examplesMerges, static fn(array $a, array $b): int => $b['source_count'] <=> $a['source_count']);

// Province-level reverse ambiguity (region-level edges)
$provEdgesIn = [];
foreach ($regionEdges as $e) {
    $provEdgesIn[$e['tgt']][] = $e['src'];
}
$provAmbiguousCount = count(array_filter($provEdgesIn, static fn(array $s): bool => count($s) > 1));

$total = count($currentWards);
$stats = [
    'dataset' => [
        'current_wards_2025' => $total,
        'legacy_units_pre_2025' => count($legacyRows),
        'mapping_edges' => count($edges),
    ],
    'reverse_classification' => [
        'ONE_TO_ONE' => $oneToOne,
        'ONE_TO_MANY' => $oneToMany,
        'NO_MATCH' => $noMatch,
        'INVALID_MAPPING' => count($invalid),
        'coverage_pct_deterministic' => round($oneToOne / max($total, 1) * 100, 2),
        'coverage_pct_resolvable_incl_ambiguous' => round(($oneToOne + $oneToMany) / max($total, 1) * 100, 2),
    ],
    'one_to_many_breakdown' => [
        'same_district_only' => $oneToManySameDistrict,
        'cross_district' => $oneToManyCrossDistrict,
        'cross_province' => count($multiProvinceWards),
    ],
    'district_information_loss' => [
        'current_wards_whose_sources_span_gt1_legacy_district' => count($districtLossWards),
        'pct_of_current_wards' => round(count($districtLossWards) / max($total, 1) * 100, 2),
    ],
    'province_level' => [
        'region_level_edges' => count($provEdgesIn),
        'regions_with_gt1_incoming_edge_reverse_ambiguous' => $provAmbiguousCount,
        'current_wards_with_sources_spanning_gt1_legacy_province' => count($multiProvinceWards),
    ],
    'relation_type_distribution' => $relationStats,
    'top_10_largest_merges' => array_slice($examplesMerges, 0, 10),
    'no_match_examples' => $examplesNoMatch,
    'invalid_edges' => array_slice($invalid, 0, 200),
    'invalid_edges_total' => count($invalid),
];

file_put_contents(__DIR__ . '/reverse-mapping-stats.json', json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

printf("=== VN_ADMIN_2025 → VN_ADMIN_PRE_2025 reverse mapping audit ===\n");
printf("current wards: %d | legacy units: %d | edges: %d\n\n", $total, count($legacyRows), count($edges));
printf("ONE_TO_ONE  (deterministic)      : %6d (%.2f%%)\n", $oneToOne, $oneToOne / $total * 100);
printf("ONE_TO_MANY (AMBIGUOUS reverse)  : %6d (%.2f%%)\n", $oneToMany, $oneToMany / $total * 100);
printf("  - same legacy district only    : %6d\n", $oneToManySameDistrict);
printf("  - cross legacy district        : %6d\n", $oneToManyCrossDistrict);
printf("NO_MATCH    (fail closed)        : %6d (%.2f%%)\n", $noMatch, $noMatch / $total * 100);
printf("INVALID_MAPPING edges             : %6d (duplicates: %d)\n\n", count($invalid), $duplicateEdges);
printf("wards spanning >1 legacy district : %6d (%.2f%%) — district info lost\n", count($districtLossWards), count($districtLossWards) / $total * 100);
printf("wards spanning >1 legacy province : %6d\n", count($multiProvinceWards));
printf("region-level edges: %d | regions reverse-ambiguous (>1 incoming): %d\n", count($provEdgesIn), $provAmbiguousCount);
printf("relation types: %s\n", json_encode($relationStats));
