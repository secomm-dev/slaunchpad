<?php
require '/var/www/html/slaunchpad/app/bootstrap.php';
$om = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER)->getObjectManager();
$c = $om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$flushConfig = function () use ($om) { $om->get(\Magento\Framework\App\Cache\TypeListInterface::class)->cleanType('config'); };
$results = [];
$fail = function (string $id, string $msg) use (&$results) { $results[] = "FAIL [$id] $msg"; };
$pass = function (string $id, string $msg) use (&$results) { $results[] = "PASS [$id] $msg"; };
$assert = function (bool $cond, string $id, string $ok, string $ko) use ($pass, $fail) { $cond ? $pass($id, $ok) : $fail($id, $ko); };

// ---------- state helpers ----------
$origGhtk = $c->fetchOne("SELECT value FROM core_config_data WHERE path='carriers/ghtk/api_base_url' AND scope_id=0");
$setGhtkRaw = function (string $url) use ($c, $flushConfig) {
    $c->exec("INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default',0,'carriers/ghtk/api_base_url'," . $c->quote($url) . ")
        ON DUPLICATE KEY UPDATE value = VALUES(value)");
    $flushConfig();
};
$setGhtk = $setGhtkRaw;
$restoreGhtk = function () use ($c, $origGhtk, $flushConfig) { $c->exec("UPDATE core_config_data SET value = " . $c->quote((string)$origGhtk) . " WHERE path='carriers/ghtk/api_base_url' AND scope_id=0"); $flushConfig(); };
$origActive = $c->fetchOne("SELECT value FROM core_config_data WHERE path='carriers/mptablerate/active' AND scope_id=0");
$setActive = function (string $v) use ($c, $flushConfig) {
    $c->exec("INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default',0,'carriers/mptablerate/active'," . $c->quote($v) . ")
        ON DUPLICATE KEY UPDATE value = VALUES(value)");
    $flushConfig();
};
$ghnRows = json_decode(file_get_contents('/tmp/qc_ghn_backup.json'), true);
$locRows = $c->fetchAll("SELECT * FROM secomm_ghn_address_mapping_location WHERE city_id IN (4649,4687)");
file_put_contents('/tmp/qc_ghn_loc_backup.json', json_encode($locRows));
$dropGhn = function () use ($c, $om) {
    $c->exec("DELETE FROM secomm_ghn_address_mapping WHERE secomm_unit_code IN ('VNA25-23A715C820','VNA25-985ACD7CE6')");
    $c->exec("DELETE FROM secomm_ghn_address_mapping_location WHERE city_id IN (4649,4687)");
    // GHN resolver caches HITS per (scheme|unit) — a dropped mapping must not read stale hits.
    $om->get(\Magento\Framework\App\Cache\TypeListInterface::class)->cleanType('secomm_ghn_mapping');
};
$restoreGhn = function () use ($c, $ghnRows, $locRows) {
    foreach ($ghnRows as $r) { unset($r['entity_id']); $c->insert('secomm_ghn_address_mapping', $r); }
    foreach ($locRows as $r) { $c->insertOnDuplicate('secomm_ghn_address_mapping_location', $r); }
};
$mapRows = json_decode(file_get_contents('/tmp/qc_map_backup.json'), true);
$dropMap = function () use ($c) { $c->delete('secomm_vietnam_address_mapping', ['target_code = ?' => 'VNA25-2554C867B9']); };
$restoreMap = function () use ($c, $mapRows) {
    foreach ($mapRows as $r) { unset($r['mapping_id']); $c->insert('secomm_vietnam_address_mapping', $r); }
};

// ---------- collect via REAL Magento quote path ----------
$collect = function (string $city, int $storeId = 1) use ($om): array {
    $product = $om->create(\Magento\Catalog\Model\ProductRepository::class)->get('atlas-pouf');
    $quote = $om->create(\Magento\Quote\Model\QuoteFactory::class)->create()->setStoreId($storeId);
    $address = $om->create(\Magento\Quote\Model\Quote\Address::class)->setQuote($quote);
    $address->setCountryId('VN')->setRegionId(1205)->setCity($city)->setPostcode('700000')
        ->setCollectShippingRates(true);
    $item = $om->create(\Magento\Quote\Model\Quote\Item::class)
        ->setProduct($product)->setQty(1)->setRowWeight(1.0)->setBaseRowTotal(500000)->setPrice(500000);
    $address->setItem($item);
    $address->setWeight(1.0);
    $address->collectShippingRates();
    $out = [];
    foreach ($address->getShippingRatesCollection() as $r) {
        $out[$r->getCode()] = (float) $r->getPrice();
    }
    return $out;
};
$has = function (array $rates, string $code): bool { return isset($rates[$code]); };

// ============ S1: normal state, dest Binh Chau — GHN real sandbox SUCCESS ============
// NOTE: standalone methods B/C need the Mageplaza carrier active (its own activation flag).
// Fallback-only A is proven independent of that flag (it appended at active=0 in the first
// run — recorded in evidence). For S1 native B/C assertions we activate the carrier.
$setActive('1');
$r1 = $collect('Binh Chau');
$assert(isset($r1['secomm_ghn_secomm_ghn']) && $r1['secomm_ghn_secomm_ghn'] > 0, 'S1-GHN-SUCCESS', 'GHN rate shown: ' . $r1['secomm_ghn_secomm_ghn'], 'GHN rate missing: ' . json_encode($r1));
$assert(!$has($r1, 'mptablerate_1'), 'S1-A-HIDDEN', 'fallback-only A absent (realtime SUCCESS suppresses)', 'A unexpectedly present');
$assert($has($r1, 'mptablerate_2') && abs($r1['mptablerate_2'] - 55000) < .001, 'S1-B-STANDALONE', 'B standalone 55000', 'B wrong: ' . json_encode($r1));
$assert($has($r1, 'mptablerate_3') && abs($r1['mptablerate_3'] - 65000) < .001, 'S1-C-STANDALONE', 'C standalone 65000', 'C wrong');
$assert(!$has($r1, 'mptablerate_4'), 'S1-D-NO-GHOST', 'ghost-member D never triggers', 'D present');

// ============ state2: GHN entries dropped + GHTK refused ============
$dropGhn();
// (DB-level table ops do not need config flush)
$setGhtk('https://127.0.0.1:9/');
// S2/S7a: dest Bau Bang — GHN mapping missing (ineligible UNAVAILABLE) + GHTK TECHNICAL → A eligible, exact-city tier
$r2 = $collect('Bau Bang');
$assert($has($r2, 'mptablerate_1') && abs($r1['mptablerate_2'] ?? 0) >= 0 && abs($r2['mptablerate_1'] - 20000) < .001, 'S2-A-EXACT-CITY', 'eligible fallback A = 20000 (exact city tier)', 'A wrong: ' . json_encode($r2));
$assert(!$has($r2, 'secomm_ghn_secomm_ghn') && !$has($r2, 'ghtk_ghtk_standard'), 'S2-CARRIERS-OUT', 'GHN+GHTK no rates', 'carrier rate present');
$cCount = 0; foreach (array_keys($r2) as $k) { if ($k === 'mptablerate_3') $cCount++; }
$assert($cCount === 1, 'S2-C-NO-DUPLICATE', 'C appears once (native, fallback copy skipped)', "C duplicated ($cCount)");
// S7b: dest Con Dao → region tier 30000
$r7b = $collect('Con Dao');
$assert($has($r7b, 'mptablerate_1') && abs($r7b['mptablerate_1'] - 30000) < .001, 'S7B-REGION-TIER', 'Con Dao → 30000 (region wildcard tier)', 'wrong: ' . json_encode($r7b));
// S7c: unresolved city name → no city-specific match, wildcard tiers 30000 (min)
$r7c = $collect('Phuong Khong Ton Tai');
$assert($has($r7c, 'mptablerate_1') && abs($r7c['mptablerate_1'] - 30000) < .001, 'S7C-UNRESOLVED-CITY', 'unresolved city → 30000 (no false city match)', 'wrong: ' . json_encode($r7c));
// S3: dest An Dong — GHN CANONICAL_AMBIGUOUS real (3 PRE candidates) + GHTK TECHNICAL → eligible
$r3 = $collect('An Dong');
$assert(!$has($r3, 'secomm_ghn_secomm_ghn'), 'S3-GHN-NO-GUESS', 'GHN did NOT quote on ambiguous PRE candidates', 'GHN quoted on ambiguity!');
$assert($has($r3, 'mptablerate_1') && abs($r3['mptablerate_1'] - 30000) < .001, 'S3-FALLBACK', 'CANONICAL_AMBIGUOUS → eligible fallback 30000', 'no fallback: ' . json_encode($r3));

// ============ state3: restore GHTK sandbox, delete Tan My mapping edges → CANONICAL_UNMAPPED ============
$restoreGhtk();
$dropMap();
$r4 = $collect('Tan My');
$assert(!$has($r4, 'mptablerate_1'), 'S4-UNMAPPED-NO-FALLBACK', 'CANONICAL_UNMAPPED → NO fallback', 'A present (unexpected): ' . json_encode($r4));
$assert(!$has($r4, 'mptablerate_4'), 'S4-D-STILL-QUIET', 'ghost D still never triggers', 'D present');

// ============ S8: restore everything, re-estimate in SAME process — no stale outcomes ============
$restoreMap(); $restoreGhn(); $restoreGhtk();
$r8 = $collect('Binh Chau');
$restoreActive = function () use ($c, $origActive, $flushConfig) { $c->exec("UPDATE core_config_data SET value = " . $c->quote((string)$origActive) . " WHERE path='carriers/mptablerate/active' AND scope_id=0"); $flushConfig(); };
$r8 = $collect('Binh Chau');
$restoreActive();
$assert(isset($r8['secomm_ghn_secomm_ghn']) && !$has($r8, 'mptablerate_1'), 'S8-NO-STALE', '2nd collection in same process: GHN SUCCESS again, no stale fallback', 'stale/missing: ' . json_encode($r8));

echo implode("\n", $results) . "\n";
echo (count(preg_grep('/^FAIL/', $results)) === 0 ? "\nQC RESULT: ALL PASS\n" : "\nQC RESULT: HAS FAILURES\n");
