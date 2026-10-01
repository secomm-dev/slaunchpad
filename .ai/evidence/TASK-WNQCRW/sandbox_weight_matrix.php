<?php
/**
 * TASK-WNQCRW §6 — GHN sandbox retest matrix for the FROZEN RATE weight semantics
 * (DEC-TASKWNQCRW-001): service_type_id = TOTAL quote weight only (<20000g → 2, >=20000g → 5).
 * Sanitized: provider codes/messages/fees only — the token is read from store config and
 * never printed. Payload shape mirrors GhnRateCalculator::fetchFeeTotal (items[] ONLY for
 * type 5; type 2 multi-unit carries the root aggregate weight alone — the payload-safety
 * case the former matrix never exercised).
 *
 * Local pre-gate proof is NOT part of this script (unit tests pin: 50001g → 0 API calls).
 * This script proves the PROVIDER side directly: what GHN answers per case, including
 * single >50kg (accepted — the merchant-tunable display gate defaults to 50000 for
 * checkout coherence, not because the provider rejects).
 *
 * Run: php .ai/evidence/TASK-WNQCRW/sandbox_weight_matrix.php
 */
require __DIR__ . '/../../../app/bootstrap.php';
$params = $_SERVER;
$params[\Magento\Store\Model\StoreManager::PARAM_RUN_CODE] = 'admin';
$params[\Magento\Store\Model\Store::ADMIN_CODE] = 'admin';
$om = \Magento\Framework\App\Bootstrap::create(BP, $params)->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');
// The api_token field is stored ENCRYPTED — read it exactly like production does via
// Secomm\Ghn\Model\Config::getApiToken() (raw ScopeConfig sends ciphertext → GHN 401).
$ghnConfig = $om->get(\Secomm\Ghn\Model\Config::class);

$token = $ghnConfig->getApiToken();
$shopId = $ghnConfig->getShopId();
if ($token === '' || $shopId === '') {
    fwrite(STDERR, "sandbox token/shop_id not configured\n");
    exit(1);
}
$baseUrlMap = (new \ReflectionClass(\Secomm\Ghn\Model\Config::class))->getConstant('BASE_URLS');
$environment = (string) $om->get(\Magento\Framework\App\Config\ScopeConfigInterface::class)
    ->getValue(\Secomm\Ghn\Model\Config::XML_PATH_ENVIRONMENT);
$base = $baseUrlMap[$environment] ?? reset($baseUrlMap);
$url = $base . '/v2/shipping-order/fee';

// RATE destination from the verified RATE slice (Đông Thành → Nhân Thành, Nghệ An).
const TO_DISTRICT_ID = 1846;
const TO_WARD_CODE = '291124';

/**
 * @param int $serviceTypeId 2|5
 * @param int $totalWeightG root weight
 * @param array<int, int> $unitWeightsG per-unit weights (items[] rows, quantity=1 each)
 */
function payload(int $serviceTypeId, int $totalWeightG, array $unitWeightsG): array
{
    $payload = [
        'service_type_id' => $serviceTypeId,
        'weight' => $totalWeightG,
        'to_district_id' => TO_DISTRICT_ID,
        'to_ward_code' => TO_WARD_CODE,
    ];
    if ($serviceTypeId === 5) {
        $payload['items'] = array_map(
            static fn (int $w, int $i): array => ['name' => 'Package ' . ($i + 1), 'quantity' => 1, 'weight' => $w],
            $unitWeightsG,
            array_keys($unitWeightsG)
        );
    }

    return $payload;
}

/** @return array{http:int, code:string|int|null, message:string|null, total:string|int|null} */
function callFee(string $url, string $token, string $shopId, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Token: ' . $token,
            'ShopId: ' . $shopId,
        ],
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = is_string($body) ? (json_decode($body, true) ?: []) : [];
    return [
        'http' => $http,
        'code' => $decoded['code'] ?? null,
        'message' => isset($decoded['message']) ? (string) $decoded['message'] : null,
        'total' => $decoded['data']['total'] ?? null,
    ];
}

$cases = [
    // id, label, unit weights (g) — service type derived by the FROZEN total-weight-only rule
    'A' => ['single 10kg', [10000]],
    'B' => ['single 19.999kg', [19999]],
    'C' => ['single 20kg (boundary)', [20000]],
    'D' => ['single 35kg', [35000]],
    'E' => ['single 50kg (boundary)', [50000]],
    'F' => ['single 50.001kg', [50001]],
    'G' => ['2x5kg = 10kg (multi-unit, type 2)', [5000, 5000]],
    'H' => ['2x10kg = 20kg (boundary)', [10000, 10000]],
    'I' => ['2x30kg = 60kg', [30000, 30000]],
    'J' => ['2x50kg = 100kg', [50000, 50000]],
    'K' => ['3x40kg = 120kg', [40000, 40000, 40000]],
    'L' => ['2x35kg = 70kg (FXFMJ0 continuity)', [35000, 35000]],
    'M' => ['4x30kg = 120kg (FXFMJ0 continuity)', [30000, 30000, 30000, 30000]],
];

printf("| %-3s | %-36s | %-2s | %-7s | %-5s | %-4s | %-6s | %-28s | %s |\n", 'ID', 'Case', 'ST', 'Root(g)', 'Items', 'HTTP', 'Code', 'Message', 'Total');
printf("|-%s-|-%s-|-%s-|-%s-|-%s-|-%s-|-%s-|-%s-|-%s-|\n", '---', str_repeat('-', 36), '--', '-------', '-----', '----', '------', str_repeat('-', 28), '---------');
foreach ($cases as $id => [$label, $units]) {
    $total = array_sum($units);
    // FROZEN rule (TASK-WNQCRW §3): type follows the TOTAL only — never the unit count.
    $serviceTypeId = $total < 20000 ? 2 : 5;
    $result = callFee($url, $token, $shopId, payload($serviceTypeId, $total, $units));
    printf(
        "| %-3s | %-36s | %-2d | %-7d | %-5d | %-4d | %-6s | %-28s | %s |\n",
        $id,
        $label,
        $serviceTypeId,
        $total,
        $serviceTypeId === 5 ? count($units) : 0,
        $result['http'],
        (string) ($result['code'] ?? '-'),
        mb_substr((string) ($result['message'] ?? '-'), 0, 28),
        (string) ($result['total'] ?? '-')
    );
    usleep(300000); // gentle pacing
}
