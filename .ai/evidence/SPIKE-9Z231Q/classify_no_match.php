<?php
/**
 * SPIKE-9Z231Q v3 — NO_MATCH (38 current wards, reverse mapping) classification.
 *
 * Clarification §8 taxonomy:
 *   SECOMM_MAPPING_DATA_GAP        — old ward exists w/ same name, edge missing (canonical data defect → fix data)
 *   STRUCTURAL_ADMIN_CHANGE        — new ward = former DISTRICT (elevation), no single old ward equivalent
 *   TRUE_NO_HISTORICAL_EQUIVALENT  — nothing comparable in PRE_2025 dataset
 *   PROVIDER_SPECIAL_CASE          — reserved (not auto-detected here)
 *   UNKNOWN                        — needs manual review
 *
 * Read-only; evidence scope only. Output: stdout + no-match-classification.json
 */

declare(strict_types=1);

$filesDir = '/var/www/html/slaunchpad/app/code/Secomm/VietNamAddress/Files';

function readCsv(string $path): array
{
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string)file_get_contents($path));
    $lines = preg_split("/\r\n|\n|\r/", trim($raw));
    array_shift($lines);
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) !== '') {
            $rows[] = str_getcsv($line);
        }
    }
    return $rows;
}

function normalize(string $name): string
{
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
    $t = strtolower($t);
    $t = preg_replace('/[^a-z0-9 ]/', ' ', $t);
    $t = preg_replace('/\b(phuong|pho|xa|thi tran|thi xa|thanh pho|tp|huyen|quan|kun)\b/', ' ', $t);
    $t = preg_replace('/\s+/', ' ', trim($t));
    return $t;
}

$current = readCsv("$filesDir/VN_ADMIN_2025_import.csv");
$legacy = readCsv("$filesDir/VN_ADMIN_PRE_2025_TO_2025_mapping.csv");

$currentWards = [];
foreach ($current as $r) {
    $currentWards[$r[3]] = ['region' => $r[0], 'name' => $r[5]];
}

// old ward / old district name indexes (normalized → list of [code, region])
$oldWards = $oldDistricts = [];
foreach (readCsv("$filesDir/VN_ADMIN_PRE_2025_import.csv") as $r) {
    $entry = ['code' => $r[3], 'region' => $r[0], 'name' => $r[5]];
    if ($r[4] === '') {
        $oldDistricts[normalize($r[5])][] = $entry;
    } else {
        $oldWards[normalize($r[5])][] = $entry;
    }
}

// region-level edges (old region → new region) — a cross-region name match is only a
// genuine data gap when the matched old region actually merged into the ward's new region.
$regionEdge = [];
foreach (readCsv("$filesDir/VN_ADMIN_PRE_2025_TO_2025_mapping.csv") as $r) {
    if (str_starts_with($r[1], 'VN-')) {
        $regionEdge[$r[1]] = $r[3];
    }
}
$regionConsistent = static fn(string $oldRegion, string $newRegion): bool =>
    isset($regionEdge[$oldRegion]) && $regionEdge[$oldRegion] === $newRegion;

$targets = [];
foreach ($legacy as $r) {
    if (!str_starts_with($r[1], 'VN-')) {
        $targets[$r[3]] = true;
    }
}

$noMatch = array_diff_key($currentWards, $targets);

$result = [];
foreach ($noMatch as $code => $meta) {
    $n = normalize($meta['name']);

    // a) same region old ward with same normalized name → missing edge (data gap)
    $wardHit = $oldWards[$n] ?? [];
    $wardSameRegion = array_values(array_filter($wardHit, static fn($e) => $e['region'] === $meta['region']));

    // b) same region old district with same normalized name → structural elevation
    $distHit = $oldDistricts[$n] ?? [];
    $distSameRegion = array_values(array_filter($distHit, static fn($e) => $e['region'] === $meta['region']));
    $distOtherRegion = array_values(array_filter($distHit, static fn($e) => $e['region'] !== $meta['region']));

    if ($wardSameRegion !== []) {
        $class = 'SECOMM_MAPPING_DATA_GAP';
        $evidence = 'old ward same name in same region: ' . $wardSameRegion[0]['code'];
    } elseif ($wardHit !== [] && $regionConsistent($wardHit[0]['region'], $meta['region'])) {
        $class = 'SECOMM_MAPPING_DATA_GAP';
        $evidence = 'old ward same name in merged-in region: ' . $wardHit[0]['code'] . '/' . $wardHit[0]['region']
            . ' (note: same-name district also exists — author full ward-set edges, not just this one)';
    } elseif ($distHit !== [] && (bool)array_filter($distHit, static fn($e) => $regionConsistent($e['region'], $meta['region']))) {
        $class = 'STRUCTURAL_ADMIN_CHANGE';
        $consistent = array_values(array_filter($distHit, static fn($e) => $regionConsistent($e['region'], $meta['region'])));
        $evidence = 'former district elevated to ward (region-consistent): ' . $consistent[0]['code'] . '/' . $consistent[0]['region'];
    } elseif ($wardHit !== [] || $distHit !== []) {
        $class = 'UNKNOWN';
        $matched = $wardHit !== [] ? $wardHit[0] : $distHit[0];
        $evidence = 'same-name candidate exists but region edge does NOT connect (' . $matched['region'] . ' -> ' . ($regionEdge[$matched['region']] ?? 'NONE') . '), region mismatch — name coincidence; manual review';
    } else {
        $class = 'UNKNOWN';
        $evidence = 'no normalized-name match in PRE_2025 dataset';
    }
    if ($distOtherRegion !== [] && $class === 'UNKNOWN') {
        $evidence .= ' | district name exists in other region: ' . $distOtherRegion[0]['code'];
    }

    $result[] = ['code' => $code, 'name' => $meta['name'], 'region' => $meta['region'], 'class' => $class, 'evidence' => $evidence];
}

usort($result, static fn($a, $b) => [$a['class'], $a['region']] <=> [$b['class'], $b['region']]);
$byClass = [];
foreach ($result as $r) {
    $byClass[$r['class']][] = $r['code'];
}

file_put_contents(__DIR__ . '/no-match-classification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

printf("=== NO_MATCH classification (%d wards) ===\n", count($result));
foreach ($byClass as $class => $codes) {
    printf("%-32s: %d\n", $class, count($codes));
}
echo "\n";
foreach ($result as $r) {
    printf("%s | %-18s | %s\n    %s\n", $r['region'], $r['name'], $r['class'], $r['evidence']);
}
