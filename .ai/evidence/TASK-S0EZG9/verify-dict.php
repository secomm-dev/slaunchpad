<?php
/**
 * TASK-S0EZG9 (SLP-293) — framework-level i18n verify for the "Back to top"
 * aria-label key, per store (LL-0011: HTTP store-switch broken locally, so
 * verify the dictionary through CLI store emulation instead).
 * Pattern: magento-cli-phrase-verify (setAreaCode BEFORE emulation, 1 store per process).
 * Run as secomm: php verify-dict.php <store_code>
 */
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Store\Model\App\Emulation;

require '/var/www/projects/slaunchpad/app/bootstrap.php';

$storeCode = $argv[1] ?? 'default';
$phrase = 'Back to top';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
// Direct OM usage is acceptable for a throwaway verification script (not shipped code).
$objectManager = $bootstrap->getObjectManager();

/** @var State $state */
$state = $objectManager->get(State::class);
$state->setAreaCode(Area::AREA_FRONTEND);

/** @var Emulation $emulation */
$emulation = $objectManager->get(Emulation::class);
$emulation->startEnvironmentEmulation($storeCode, Area::AREA_FRONTEND);

$translated = (string) __($phrase);
echo sprintf(
    "store=%s phrase=%s => %s (%s)%s",
    $storeCode,
    $phrase,
    $translated,
    ($translated === $phrase ? 'IDENTITY' : 'TRANSLATED'),
    PHP_EOL
);

$emulation->stopEnvironmentEmulation();
