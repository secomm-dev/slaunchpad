<?php
declare(strict_types=1);
$env = include '/var/www/html/slaunchpad/app/etc/env.php';
$db = $env['db']['connection']['default'];
$pdo = new PDO("mysql:host={$db['host']};dbname={$db['dbname']}", $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Load snapshot CSV edges
$csvPath = '/var/www/html/slaunchpad/app/code/Secomm/VietNamAddress/Files/VN_ADMIN_PRE_2025_TO_2025_SNAPSHOT_2024_mapping.csv';
$rows = [];
$handle = fopen($csvPath, 'rb');
fgetcsv($handle); // header
while (($r = fgetcsv($handle)) !== false) {
    if (count($r) < 2 || trim((string)$r[0]) === '') { continue; }
    $rows[] = $r;
}
// Detect column order from header? assume: source_code,target_code,relation_type,(is_primary?)
// Print header for the report
$handle2 = fopen($csvPath, 'rb');
$header = fgetcsv($handle2);
echo "CSV_HEADER: " . implode(',', $header) . "\n";
echo "CSV_EDGE_COUNT: " . count($rows) . "\n";

// Load baseline (superseded) CSV edges
$baselinePath = '/var/www/html/slaunchpad/app/code/Secomm/VietNamAddress/Files/VN_ADMIN_PRE_2025_TO_2025_mapping.csv';
$baselineRows = [];
$h2 = fopen($baselinePath, 'rb');
fgetcsv($h2);
while (($r = fgetcsv($h2)) !== false) {
    if (count($r) < 2 || trim((string)$r[0]) === '') { continue; }
    $baselineRows[] = $r;
}
echo "BASELINE_EDGE_COUNT: " . count($baselineRows) . "\n";

// key both CSVs
$keyOf = function (array $r): string { return $r[1] . '=>' . $r[3]; }; // source_code=>target_code (col 1,3 — col 0/2 are schemes)
$csvByKey = []; $csvRel = [];
foreach ($rows as $r) { $k = $keyOf($r); $csvByKey[$k] = true; $csvRel[$k] = $r[4] ?? ''; }
$baselineByKey = [];
foreach ($baselineRows as $r) { $baselineByKey[$keyOf($r)] = true; }

// Load DB edges
$st = $pdo->query("SELECT source_code, target_code, relation_type FROM secomm_vietnam_address_mapping WHERE source_scheme='VN_ADMIN_PRE_2025' AND target_scheme='VN_ADMIN_2025'");
$dbByKey = []; $dbRel = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $k = $row['source_code'] . '=>' . $row['target_code'];
    $dbByKey[$k] = true;
    $dbRel[$k] = $row['relation_type'];
}
echo "DB_EDGE_COUNT: " . count($dbByKey) . "\n";

// CSV internal duplicates
$seen = []; $dup = 0;
foreach ($rows as $r) { $k = $keyOf($r); $dup += isset($seen[$k]) ? 1 : 0; $seen[$k] = true; }
echo "CSV_DUPLICATE_TUPLES: $dup\n";

// DB vs CSV
$addedInDb = array_diff_key($dbByKey, $csvByKey);
$missingInDb = array_diff_key($csvByKey, $dbByKey);
$changedRel = [];
foreach (array_intersect_key($dbByKey, $csvByKey) as $k => $_) {
    if (trim((string)$dbRel[$k]) !== trim((string)$csvRel[$k])) { $changedRel[] = $k; }
}
echo "DB_NOT_IN_CSV(extra): " . count($addedInDb) . "\n";
echo "CSV_NOT_IN_DB(missing): " . count($missingInDb) . "\n";
echo "CHANGED_RELATION_TYPE: " . count($changedRel) . "\n";

// vs superseded baseline
echo "BASELINE_NOT_IN_CSV(baseline-only): " . count(array_diff_key($baselineByKey, $csvByKey)) . "\n";
echo "CSV_NOT_IN_BASELINE(snapshot-added): " . count(array_diff_key($csvByKey, $baselineByKey)) . "\n";

// orphan checks: source/target codes exist in units table
function codesExist(PDO $pdo, array $keys, string $schemeColumn, string $table): array {
    // returns [missing_source, missing_target] for the provided mapping tuples
    return [0, 0];
}
$srcCodes = []; $tgtCodes = [];
foreach ($dbByKey as $k => $_) { [$s, $t] = explode('=>', $k); $srcCodes[$s] = 1; $tgtCodes[$t] = 1; }
function countMissing(PDO $pdo, array $codes, string $scheme): int {
    $missing = 0;
    foreach (array_chunk(array_keys($codes), 500) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $st = $pdo->prepare("SELECT COUNT(*) FROM secomm_vietnam_address_unit WHERE scheme_code = ? AND code IN ($ph)");
        $st->execute(array_merge([$scheme], $chunk));
        $found = (int)$st->fetchColumn();
        $missing += count($chunk) - $found;
    }
    return $missing;
}
echo "DB_ORPHAN_SOURCE_CODES: " . countMissing($pdo, $srcCodes, 'VN_ADMIN_PRE_2025') . "\n";
echo "DB_ORPHAN_TARGET_CODES: " . countMissing($pdo, $tgtCodes, 'VN_ADMIN_2025') . "\n";

// ambiguous sources (multi-target) + multi-primary
$st2 = $pdo->query("SELECT source_code, COUNT(*) c FROM secomm_vietnam_address_mapping WHERE source_scheme='VN_ADMIN_PRE_2025' GROUP BY source_code HAVING c > 1");
echo "DB_AMBIGUOUS_SOURCES: " . $st2->rowCount() . "\n";

// is_primary column?
$cols = $pdo->query("SHOW COLUMNS FROM secomm_vietnam_address_mapping")->fetchAll(PDO::FETCH_COLUMN);
echo "MAPPING_COLUMNS: " . implode(',', $cols) . "\n";
if (in_array('is_primary', $cols, true)) {
    $st3 = $pdo->query("SELECT source_code, COUNT(*) c FROM secomm_vietnam_address_mapping WHERE is_primary=1 GROUP BY source_code HAVING c > 1");
    echo "DB_MULTI_PRIMARY_SOURCES: " . $st3->rowCount() . "\n";
}
// coverage
$st4 = $pdo->query("SELECT COUNT(*) FROM secomm_vietnam_address_unit WHERE scheme_code='VN_ADMIN_PRE_2025' AND level=3");
$preWards = (int)$st4->fetchColumn();
$st5 = $pdo->query("SELECT COUNT(DISTINCT source_code) FROM secomm_vietnam_address_mapping WHERE source_scheme='VN_ADMIN_PRE_2025'");
$mappedSources = (int)$st5->fetchColumn();
echo "PRE2025_WARD_UNITS: $preWards\n";
echo "MAPPED_DISTINCT_SOURCES: $mappedSources\n";
echo "UNMAPPED_KNOWN(legit, no edge): " . max(0, $preWards - $mappedSources) . "\n";
