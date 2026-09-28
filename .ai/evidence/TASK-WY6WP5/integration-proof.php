<?php
declare(strict_types=1);
// TASK-WY6WP5 — directive §31 integration proof (CLI, real DB). NOT part of the codebase.
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Secomm\ShippingCore\Api\Config\CarrierDestinationScopeConfigInterface;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityEvaluatorInterface;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\Zone\ZoneRepository;
use Secomm\ShippingCore\Model\Address\CanonicalZone;

require '/var/www/html/slaunchpad/app/bootstrap.php';
$env = include '/var/www/html/slaunchpad/app/etc/env.php';
$c = $env['db']['connection']['default'];
$pdo = new PDO("mysql:host={$c['host']};dbname={$c['dbname']}", $c['username'], $c['password']);
$bootstrap = Bootstrap::create(BP, $_SERVER);
$bootstrap->createApplication(\Magento\Framework\App\Http::class);
$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
$objectManager->get(State::class)->setAreaCode('adminhtml');

$adapter = $objectManager->get(CarrierCoverageConfigAdapter::class);
$reader = $objectManager->get(CarrierDestinationScopeConfigInterface::class);
$evaluator = $objectManager->get(CarrierEligibilityEvaluatorInterface::class);
$zones = $objectManager->get(ZoneRepository::class);
$identity = CoverageTargetIdentity::carrier('secomm_ghn');

function check(string $label, bool $cond): void {
    echo ($cond ? 'PASS' : 'FAIL') . " — $label\n";
    if (!$cond) { exit(1); }
}

echo "== baseline (no explicit GHN coverage config) ==\n";
// Idempotent entry: a previous crashed run may have persisted config — restore baseline.
if ($adapter->hasExplicitConfig($identity)) {
    $adapter->reset($identity);
    echo "(cleaned config left by a previous run)\n";
}
check('hasExplicitConfig = false', $adapter->hasExplicitConfig($identity) === false);
check('runtime reader default scope = ALL', $reader->getDestinationScope('secomm_ghn') === 'ALL');
$r = $evaluator->evaluate('ALL', [], 'VN-15', null);
check('ALL → eligible for HCM province (VN-15)', $r->isEligible());

echo "== create zone HCM_INNER (VN-15) if missing ==\n";
if ($zones->getByCode('HCM_INNER') === null) {
    $zones->save(new CanonicalZone('HCM_INNER', 'Ho Chi Minh Inner', true, ['VN-15'], [], []));
    echo "created HCM_INNER\n";
} else {
    echo "HCM_INNER already present\n";
}

echo "== save explicit coverage: SELECTED_ZONES = [HCM_INNER] ==\n";
$adapter->save($identity, 'SELECTED_ZONES', ['HCM_INNER'], 'CARRIER_WITH_FALLBACK', 'FALLBACK');

$rows = $pdo->query("SELECT path, scope, value FROM core_config_data WHERE path LIKE 'carriers/secomm_ghn/%' ORDER BY path")->fetchAll(PDO::FETCH_ASSOC);
check('exactly 4 persisted DEFAULT rows', count($rows) === 4 && count(array_unique(array_column($rows, 'scope'))) === 1 && $rows[0]['scope'] === 'default');
foreach ($rows as $row) { echo "   {$row['path']} = {$row['value']}\n"; }

echo "== fresh runtime readers consume the exact persisted values ==\n";
check('reader scope = SELECTED_ZONES', $reader->getDestinationScope('secomm_ghn') === 'SELECTED_ZONES');
check('reader zones = [HCM_INNER]', $reader->getAllowedZoneCodes('secomm_ghn') === ['HCM_INNER']);

echo "== eligibility evaluation (canonical identity, pre provider conversion) ==\n";
$inZone = $evaluator->evaluate('SELECTED_ZONES', ['HCM_INNER'], 'VN-15', null);
check('VN-15 (HCM) eligible, matched=HCM_INNER', $inZone->isEligible() && $inZone->getMatchedZoneCode() === 'HCM_INNER');
$outZone = $evaluator->evaluate('SELECTED_ZONES', ['HCM_INNER'], 'VN-01', null);
check('VN-01 (An Giang) ineligible + DESTINATION_NOT_IN_SCOPE', !$outZone->isEligible() && $outZone->getReasonCode() === 'DESTINATION_NOT_IN_SCOPE');

echo "== reset to defaults ==\n";
$removed = $adapter->reset($identity);
check("reset removed 4 DEFAULT values (got $removed)", $removed === 4);
check('runtime reader back to default ALL', $reader->getDestinationScope('secomm_ghn') === 'ALL');
check('reader zones empty', $reader->getAllowedZoneCodes('secomm_ghn') === []);
$r2 = $evaluator->evaluate('ALL', [], 'VN-01', null);
check('ALL → eligible again for VN-01', $r2->isEligible());
check('hasExplicitConfig back to false', $adapter->hasExplicitConfig($identity) === false);

echo "ALL INTEGRATION PROOF CHECKS PASSED\n";
