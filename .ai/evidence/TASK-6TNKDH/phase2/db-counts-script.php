<?php
require '/var/www/html/slaunchpad/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');
$res = $om->get(\Magento\Framework\App\ResourceConnection::class);
$db = $res->getConnection();
$t = fn (string $n): string => $res->getTableName($n);

echo "== secomm_ghn_address_unit per scheme/depth/status:\n";
foreach ($db->fetchAll("SELECT scheme_code, depth, status, COUNT(*) c FROM " . $t('secomm_ghn_address_unit') . " GROUP BY scheme_code, depth, status ORDER BY scheme_code, depth") as $r) {
    echo "  {$r['scheme_code']} depth={$r['depth']} {$r['status']}: {$r['c']}\n";
}
echo "== secomm_ghn_address_mapping per scheme/status:\n";
foreach ($db->fetchAll("SELECT secomm_scheme_code, mapping_status, COUNT(*) c FROM " . $t('secomm_ghn_address_mapping') . " GROUP BY secomm_scheme_code, mapping_status") as $r) {
    echo "  {$r['secomm_scheme_code']} {$r['mapping_status']}: {$r['c']}\n";
}
echo "== mapping method distribution (DB):\n";
foreach ($db->fetchAll("SELECT mapping_method, COUNT(*) c FROM " . $t('secomm_ghn_address_mapping') . " GROUP BY mapping_method ORDER BY c DESC") as $r) {
    echo "  {$r['mapping_method']}: {$r['c']}\n";
}
echo "== DB-side integrity:\n";
echo "  duplicate ghn target: " . $db->fetchOne("SELECT COUNT(*) FROM (SELECT ghn_address_unit_id FROM " . $t('secomm_ghn_address_mapping') . " GROUP BY ghn_address_unit_id HAVING COUNT(*) > 1) x") . "\n";
echo "  duplicate canonical source: " . $db->fetchOne("SELECT COUNT(*) FROM (SELECT secomm_scheme_code, secomm_unit_code FROM " . $t('secomm_ghn_address_mapping') . " GROUP BY secomm_scheme_code, secomm_unit_code HAVING COUNT(*) > 1) x") . "\n";
echo "  dangling ghn unit refs: " . $db->fetchOne("SELECT COUNT(*) FROM " . $t('secomm_ghn_address_mapping') . " m LEFT JOIN " . $t('secomm_ghn_address_unit') . " u ON u.entity_id = m.ghn_address_unit_id WHERE u.entity_id IS NULL") . "\n";
echo "  dangling canonical refs: " . $db->fetchOne("SELECT COUNT(*) FROM " . $t('secomm_ghn_address_mapping') . " m LEFT JOIN " . $t('secomm_vietnam_address_unit') . " v ON v.scheme_code = m.secomm_scheme_code AND v.code = m.secomm_unit_code WHERE v.entity_id IS NULL") . "\n";
echo "  null identity: " . $db->fetchOne("SELECT COUNT(*) FROM " . $t('secomm_ghn_address_mapping') . " WHERE secomm_unit_code IS NULL OR secomm_unit_code = '' OR ghn_address_unit_id IS NULL") . "\n";
