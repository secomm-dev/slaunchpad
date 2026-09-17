<?php
/**
 * BUG-KFJ49A đợt 3 — verify dict theo pattern SLP-225 (explicit theme + locale).
 * Usage: php verify-phrase-dot3.php <vi_VN|en_US>
 */
use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Phrase;

$locale = isset($argv[1]) ? $argv[1] : 'vi_VN';
require '/var/www/projects/slaunchpad/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om        = $bootstrap->getObjectManager();

$om->get(\Magento\Framework\App\State::class)->setAreaCode(Area::AREA_FRONTEND);

$theme = $om->get(\Magento\Framework\View\Design\Theme\ThemeProviderInterface::class)
    ->getThemeByFullPath('frontend/Secomm/launchpad');
$design = $om->get(\Magento\Framework\View\DesignInterface::class);
$design->setDesignTheme($theme, Area::AREA_FRONTEND);

/** @var \Magento\Framework\Translate $translate */
$translate = $om->get(\Magento\Framework\Translate::class);
$translate->setLocale($locale);
$translate->loadData(Area::AREA_FRONTEND, true);
$data = $translate->getData();
printf("locale=%s theme=%s dict=%d entries\n", $locale, $theme->getCode(), count($data));

$cases = [
    ['We found other products you might like!', []], // key mới đợt 3
    ['Related Products', []],                        // control key đợt 1
];
Phrase::setRenderer($om->get(\Magento\Framework\Phrase\RendererInterface::class));
foreach ($cases as [$text, $args]) {
    printf(
        "dict=%s\n  render=%s\n",
        $data[$text] ?? '(MISS — fallback identity)',
        (string)(new Phrase($text, $args))
    );
}
