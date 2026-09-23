<?php
/**
 * GHTK staging contract probe kit — TASK-44F7V7 (SPIKE-A1DGPY NEEDS_RUNTIME_VERIFICATION items).
 *
 * OFFICIAL staging ONLY: https://services-staging.ghtklab.com (never production,
 * never dev.giaohangtietkiem.vn). Read-mostly; CREATE probes use deterministic
 * partner ids `secomm-probe-*` with same-id-same-payload repeats only.
 *
 * Usage (token from your env — NEVER committed, NEVER logged):
 *   export GHTK_STAGING_TOKEN='<staging token from khachhang-staging.ghtklab.com>'
 *   export GHTK_PARTNER_CODE='<shop/partner code>'
 *   [optional] export GHTK_PICK_ADDRESS_ID='<staging pick_address_id>'
 *   php .ai/evidence/TASK-44F7V7/probe.php > .ai/evidence/TASK-44F7V7/results.json
 *
 * Token is read from the environment only and is never printed. Output records:
 * operation, request field set, HTTP status, success, error_code, message, and the
 * relevant response fields (already free of token/PII — payloads contain no
 * customer identity, only administrative names + probe markers).
 */

declare(strict_types=1);

const BASE = 'https://services-staging.ghtklab.com';

$token = getenv('GHTK_STAGING_TOKEN') ?: '';
$partner = getenv('GHTK_PARTNER_CODE') ?: '';
if ($token === '' || $partner === '') {
    fwrite(STDERR, "BLOCKED_BY_CREDENTIAL: export GHTK_STAGING_TOKEN and GHTK_PARTNER_CODE first.\n");
    exit(2);
}

$results = [];

function record(array &$results, string $op, string $case, string $method, string $path, array $req, ?array $res, ?int $http, string $note = ''): void
{
    $results[] = [
        'operation' => $op,
        'case' => $case,
        'request' => ['method' => $method, 'path' => $path, 'fields' => $req],
        'http_status' => $http,
        'success' => $res['success'] ?? null,
        'error_code' => $res['error_code'] ?? null,
        'message' => $res['message'] ?? null,
        'response_fields' => $res === null ? null : summarize($res),
        'note' => $note,
    ];
}

function summarize(array $res): array
{
    $out = [];
    foreach (['fee', 'order', 'data'] as $key) {
        if (isset($res[$key])) {
            $block = $res[$key];
            if (is_array($block)) {
                foreach (['fee', 'insurance_fee', 'delivery', 'name', 'label', 'tracking_id', 'partner_id', 'created', 'status', 'status_id', 'status_text', 'area', 'message'] as $f) {
                    if (array_key_exists($f, $block)) {
                        $out[$key][$f] = $block[$f];
                    }
                }
            }
        }
    }

    return $out;
}

function call(string $method, string $path, string $token, string $partner, ?array $json = null, ?array $query = null): array
{
    $url = BASE . $path . ($query ? '?' . http_build_query($query) : '');
    $ch = curl_init($url);
    $headers = ['Token: ' . $token, 'X-Client-Source: ' . $partner];
    if ($json !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;

    return [$status, is_array($decoded) ? $decoded : ['_raw' => is_string($body) ? substr($body, 0, 300) : null]];
}

// ---------------------------------------------------------------- canonical test data (from secomm_vietnam_address_unit, local reference layer)
// Provenance: scheme VN_ADMIN_2025 (CURRENT). Legacy district for R2 = "Ứng Hòa",
// derived via incoming PRE edges (MERGED_INTO/SAME_AS/RENAMED_TO) collapsing to
// EXACTLY ONE PRE district — deterministic, not invented.
$cases = [
    'hn_phuong_ung_thien' => [ // also the "cấp xã" representative (2025 names carry no Phường/Xã prefix)
        'province' => 'Hà Nội', 'ward' => 'Ứng Thiên',
        'scheme' => 'VN_ADMIN_2025', 'province_code' => 'VN-12', 'ward_code' => 'VNA25-001EB63245',
        'legacy_district' => 'Ứng Hòa', 'legacy_district_code' => 'VNAP25-6E0A6B3F14',
    ],
    'hcm_binh_chan' => [
        'province' => 'Hồ Chí Minh', 'ward' => 'Bình Chánh',
        'scheme' => 'VN_ADMIN_2025', 'province_code' => 'VN-15', 'ward_code' => 'VNA25-00F4A0BB48',
    ],
    'dn_phu_ninh' => [
        'province' => 'Đà Nẵng', 'ward' => 'Phú Ninh',
        'scheme' => 'VN_ADMIN_2025', 'province_code' => 'VN-06', 'ward_code' => 'VNA25-089B81B0E8',
    ],
    'an_binh_cantho_dup5' => [ // ward name existing in 5 regions — identity is canonical, not the name
        'province' => 'Cần Thơ', 'ward' => 'An Bình',
        'scheme' => 'VN_ADMIN_2025', 'province_code' => 'VN-04', 'ward_code' => 'VNA25-22DABDD368',
    ],
];

$feeBase = ['weight' => 1000, 'transport' => 'road', 'pick_province' => 'Hà Nội', 'pick_district' => 'Ứng Hòa', 'pick_ward' => 'Ứng Thiên'];

// ---------------------------------------------------------------- P0: connectivity + token
[$http, $res] = call('GET', '/services/authenticated', $token, $partner);
record($results, 'AUTH', 'authenticated', 'GET', '/services/authenticated', [], $res, $http,
    ($res['success'] ?? false) ? 'token OK' : 'BLOCKED or invalid — do not proceed to CREATE probes');

// ---------------------------------------------------------------- RATE probes
foreach ($cases as $name => $c) {
    $req = $feeBase + ['province' => $c['province'], 'ward' => $c['ward']]; // NO district (R1)
    [$http, $res] = call('GET', '/services/shipment/fee', $token, $partner, null, $req);
    record($results, 'RATE', "R1 {$name} (no district)", 'GET', '/services/shipment/fee', $req, $res, $http);
}

$c = $cases['hn_phuong_ung_thien'];
$req = $feeBase + ['province' => $c['province'], 'district' => $c['legacy_district'], 'ward' => $c['ward']]; // R2 deterministic legacy district
[$http, $res] = call('GET', '/services/shipment/fee', $token, $partner, null, $req);
record($results, 'RATE', 'R2 hn (with legacy district Ứng Hòa)', 'GET', '/services/shipment/fee', $req, $res, $http,
    'district provenance: VN_ADMIN_PRE_2025 VNAP25-6E0A6B3F14 via incoming MERGED_INTO edges — unique');

// ---------------------------------------------------------------- CREATE probes (deterministic ids, staging only)
function orderPayload(array $c, string $id, bool $withDistrict): array
{
    $order = [
        'id' => $id,
        'pick_name' => 'Secomm Probe', 'pick_tel' => '0900000000',
        'pick_address' => 'Probe address 1',
        'pick_province' => 'Hà Nội', 'pick_district' => 'Ứng Hòa', 'pick_ward' => 'Ứng Thiên',
        'pick_money' => 0,
        'is_freeship' => 1,
        'value' => 100000,
        'transport' => 'road',
        'name' => 'Probe Receiver', 'tel' => '0980000000', 'address' => 'Probe delivery address 1',
        'province' => $c['province'], 'ward' => $c['ward'], 'hamlet' => 'Khác',
        'total_weight' => 0.2, // Double, kg — TASK-KCXKVR boundary conversion (200 g)
    ];
    if ($withDistrict) {
        $order['district'] = $c['legacy_district'] ?? 'Ứng Hòa';
    }

    return ['order' => $order, 'products' => [
        ['name' => 'Probe item', 'weight' => 0.2, 'quantity' => 1, 'price' => 100000.0], // kg per official docs
    ]];
}

$idC1 = 'secomm-probe-c1-nodistrict-001';
[$http, $res] = call('POST', '/services/shipment/order', $token, $partner, orderPayload($cases['hn_phuong_ung_thien'], $idC1, false));
record($results, 'CREATE', 'C1 hn (no district, ver absent)', 'POST', '/services/shipment/order', ['order.id' => $idC1, 'district' => 'omitted', 'products.weight' => '0.2 kg'], $res, $http);

// ORDER_ID_EXIST probe: EXACT same id + same payload (never conflict-variant).
[$http, $res2] = call('POST', '/services/shipment/order', $token, $partner, orderPayload($cases['hn_phuong_ung_thien'], $idC1, false));
record($results, 'CREATE', 'C1 repeat (ORDER_ID_EXIST)', 'POST', '/services/shipment/order', ['order.id' => $idC1, 'district' => 'omitted'], $res2, $http,
    'verify error_code + partner_id/ghtk_label/created/status recovery fields');

$idC2 = 'secomm-probe-c2-withdistrict-001';
[$http, $res] = call('POST', '/services/shipment/order', $token, $partner, orderPayload($cases['hn_phuong_ung_thien'], $idC2, true));
record($results, 'CREATE', 'C2 hn (with legacy district)', 'POST', '/services/shipment/order', ['order.id' => $idC2, 'district' => 'Ứng Hòa'], $res, $http);

// ---------------------------------------------------------------- ver=1.5 probe (shape/behavior comparison only)
$idVer = 'secomm-probe-ver15-001';
[$http, $res] = call('POST', '/services/shipment/order', $token, $partner, orderPayload($cases['hn_phuong_ung_thien'], $idVer, false), ['ver' => '1.5']);
record($results, 'CREATE', 'ver=1.5 (no district)', 'POST', '/services/shipment/order?ver=1.5', ['order.id' => $idVer], $res, $http,
    'compare accepted shape/response vs ver-absent C1; VERIFIED_ENDPOINT_VERSION_ONLY if no behavioral diff');

// ---------------------------------------------------------------- pickup id probe (only if provided)
$pickId = getenv('GHTK_PICK_ADDRESS_ID') ?: '';
if ($pickId !== '') {
    $req = ['weight' => 1000, 'transport' => 'road', 'pick_address_id' => $pickId,
        'province' => $cases['hn_phuong_ung_thien']['province'], 'ward' => $cases['hn_phuong_ung_thien']['ward']]; // pick_* omitted — ID takes priority
    [$http, $res] = call('GET', '/services/shipment/fee', $token, $partner, null, $req);
    record($results, 'RATE', 'pick_address_id priority (pick_* omitted)', 'GET', '/services/shipment/fee', $req, $res, $http);
    [$http, $res] = call('GET', '/services/shipment/list_pick_add', $token, $partner);
    record($results, 'PICKUP', 'list_pick_add (merchant address book)', 'GET', '/services/shipment/list_pick_add', [], $res, $http);
} else {
    record($results, 'PICKUP', 'pick_address_id', 'GET', 'n/a', [], null, null, 'NOT_VERIFIED — no staging pickup id provided');
}

// ---------------------------------------------------------------- output (token never printed)
echo json_encode([
    'base' => BASE,
    'generated_at' => date('c'),
    'results' => $results,
    'test_data_provenance' => [
        'scheme' => 'VN_ADMIN_2025 (CURRENT per secomm_vietnam_address_scheme)',
        'units' => 'secomm_vietnam_address_unit codes recorded per case above',
        'legacy_district' => 'VN_ADMIN_PRE_2025 VNAP25-6E0A6B3F14 (Ứng Hòa) — unique PRE district of ward VNA25-001EB63245 via incoming mapping edges',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
