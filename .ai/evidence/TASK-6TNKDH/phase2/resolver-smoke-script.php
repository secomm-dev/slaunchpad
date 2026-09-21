<?php
require '/var/www/html/slaunchpad/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');

$resolver = $om->get(\Secomm\Ghn\Model\Address\Mapping\GhnMappingResolver::class);
$res = $om->get(\Magento\Framework\App\ResourceConnection::class);

/**
 * Pick a NORMALIZED_EXACT ward row for a scheme: canonical code + resolved expectations
 * straight from stored data (authoritative import), so the resolver output is asserted
 * against the stored chain, not against hand-typed values.
 */
function pickExactRow(\Magento\Framework\App\ResourceConnection $res, string $scheme): array
{
    $db = $res->getConnection();
    $wardDepth = $scheme === 'VN_ADMIN_2025' ? 2 : 3; // ward depth per scheme
    $row = $db->fetchRow(
        "SELECT m.secomm_unit_code, u.name ward_name, u.provider_id ward_provider_id, u.provider_code ward_code,
                p.name parent_name, p.provider_id parent_provider_id, p.depth parent_depth,
                g.name grand_name, g.provider_id grand_provider_id
         FROM " . $res->getTableName('secomm_ghn_address_mapping') . " m
         JOIN " . $res->getTableName('secomm_ghn_address_unit') . " u ON u.entity_id = m.ghn_address_unit_id
         LEFT JOIN " . $res->getTableName('secomm_ghn_address_unit') . " p ON p.entity_id = u.parent_id
         LEFT JOIN " . $res->getTableName('secomm_ghn_address_unit') . " g ON g.entity_id = p.parent_id
         WHERE m.secomm_scheme_code = ? AND m.mapping_method = 'NORMALIZED_EXACT' AND u.depth = ?
         LIMIT 1",
        [$scheme, $wardDepth]
    );
    if ($row === false || $row === null) {
        throw new RuntimeException('no NORMALIZED_EXACT ward row found for ' . $scheme);
    }

    return $row;
}

$failures = [];
$check = function (string $label, $actual, $expected) use (&$failures): void {
    $ok = $actual === $expected;
    if (!$ok) {
        $failures[] = $label;
    }
    printf(
        "  [%s] %s: %s%s\n",
        $ok ? 'PASS' : 'FAIL',
        $label,
        var_export($actual, true),
        $ok ? '' : '  expected ' . var_export($expected, true)
    );
};

echo "== CURRENT MODE (VN_ADMIN_2025 → GHN_ADMIN_2025) ==\n";

$exact = pickExactRow($res, 'VN_ADMIN_2025');
$loc = $resolver->resolve('VN_ADMIN_2025', $exact['secomm_unit_code']);
echo " normal-exact case: {$exact['secomm_unit_code']}\n";
$check('ward name verbatim (GHN)', $loc->getWardName(), $exact['ward_name']);
$check('province name verbatim (GHN)', $loc->getProvinceName(), $exact['parent_name']);
$check('2025 mode carries no legacy ids', $loc->getDistrictId() === null && $loc->getWardCode() === null, true);

$loc = $resolver->resolve('VN_ADMIN_2025', 'VNA25-4EA3ADA7E1');
echo " reviewed case (Kỳ Lừa → residual pair):\n";
$check('Kỳ Lừa ward → Tân Thanh', $loc->getWardName(), 'Xã Tân Thanh');
$check('province → Lạng Sơn', $loc->getProvinceName(), 'Lạng Sơn');

echo "== LEGACY MODE (VN_ADMIN_PRE_2025 → GHN_ADMIN_PRE_2025) ==\n";

$exact = pickExactRow($res, 'VN_ADMIN_PRE_2025');
$loc = $resolver->resolve('VN_ADMIN_PRE_2025', $exact['secomm_unit_code']);
echo " normal-exact case: {$exact['secomm_unit_code']} (district stored: {$exact['parent_name']})\n";
$check('ward name verbatim', $loc->getWardName(), $exact['ward_name']);
$check('district_id', $loc->getDistrictId(), (string) $exact['parent_provider_id']);
$check('ward_code', $loc->getWardCode(), (string) $exact['ward_code']);
$check('province name verbatim', $loc->getProvinceName(), $exact['grand_name']);
$check('province_id', $loc->getProvinceId(), (string) $exact['grand_provider_id']);

$loc = $resolver->resolve('VN_ADMIN_PRE_2025', 'VNAP25-6A84B015D0');
echo " reviewed case (Đông Thành → historical merge Nhân Thành):\n";
$check('ward → Xã Nhân Thành', $loc->getWardName(), 'Xã Nhân Thành');
$check('ward_code → 291124', $loc->getWardCode(), '291124');
$check('district_id → 1846 (Huyện Yên Thành, d:235:1846)', $loc->getDistrictId(), '1846');
$check('province_id → 235 (p:235 Nghệ An)', $loc->getProvinceId(), '235');
$check('province → Nghệ An', $loc->getProvinceName(), 'Nghệ An');
$check('complete legacy triple', $loc->hasCompleteLegacyTriple(), true);

$loc = $resolver->resolve('VN_ADMIN_PRE_2025', 'VNAP25-32D9CD7A44');
echo " curated duplicate case (Hòa Bình, Kim Thành, Hải Dương):\n";
$check('ward_code → curated 910116 (NOT 91352)', $loc->getWardCode(), '910116');
$check('district_id → 1953 (Kim Thành)', $loc->getDistrictId(), '1953');
$check('province → Hải Dương', $loc->getProvinceName(), 'Hải Dương');

$loc = $resolver->resolve('VN_ADMIN_PRE_2025', 'VNAP25-B5E3BB1785');
echo " curated duplicate case (Yên Sơn, Yên Sơn, Tuyên Quang):\n";
$check('ward_code → curated 910044 (NOT 90815)', $loc->getWardCode(), '910044');
$check('district_id → 1745 (Yên Sơn)', $loc->getDistrictId(), '1745');
$check('province → Tuyên Quang', $loc->getProvinceName(), 'Tuyên Quang');

echo $failures === []
    ? "\nALL RESOLVER SMOKE CHECKS PASSED\n"
    : "\nFAILED: " . count($failures) . " check(s): " . implode(', ', $failures) . "\n";
exit($failures === [] ? 0 : 1);
