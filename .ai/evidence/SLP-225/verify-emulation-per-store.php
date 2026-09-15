<?php
/**
 * SLP-225 verify: server-side translation of password-reset phrases per store view.
 * Usage: php slp225-verify-translate.php <storeId>
 * One process per store — store emulation is not round-trippable within a process.
 */
use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Phrase;

require '/var/www/projects/slaunchpad/app/bootstrap.php';

$storeId   = (int)($argv[1] ?? 1);
$bootstrap = Bootstrap::create(BP, $_SERVER);
$om        = $bootstrap->getObjectManager();

// setAreaCode MUST precede emulation
$om->get(\Magento\Framework\App\State::class)->setAreaCode(Area::AREA_FRONTEND);

$om->get(\Magento\Store\Model\App\Emulation::class)->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND);

$design = $om->get(\Magento\Framework\View\DesignInterface::class)->getDesignTheme();
$locale = $om->get(\Magento\Framework\Locale\ResolverInterface::class)->getLocale();

/** @var \Magento\Framework\Translate $translate */
$translate = $om->get(\Magento\Framework\Translate::class);
$translate->loadData(Area::AREA_FRONTEND, true);
$data = $translate->getData();

// Route Phrase through the runtime renderer (Composite > Translate > MessageFormatter)
Phrase::setRenderer($om->get(\Magento\Framework\Phrase\RendererInterface::class));

printf(
    "store=%d locale=%s theme=%s dict=%d entries\n",
    $storeId,
    $locale,
    $design->getCode(),
    count($data)
);

$cases = [
    ['We received too many requests for password resets. Please wait and try again later or contact %1.', ['support@secomm.vn']],
    ['Please correct the email address.', []],
];
foreach ($cases as [$text, $args]) {
    printf(
        "dict-hit=%s\n  %s\n",
        array_key_exists($text, $data) ? 'YES' : 'NO',
        (string)(new Phrase($text, $args))
    );
}
