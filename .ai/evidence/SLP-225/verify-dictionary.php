<?php
/**
 * SLP-225 verify v2: load theme dictionary with explicitly set design theme + locale.
 * Isolates translation pack loading from store-emulation design resolution.
 */
use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Phrase;

require '/var/www/projects/slaunchpad/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om        = $bootstrap->getObjectManager();

$om->get(\Magento\Framework\App\State::class)->setAreaCode(Area::AREA_FRONTEND);

$theme = $om->get(\Magento\Framework\View\Design\Theme\ThemeProviderInterface::class)
    ->getThemeByFullPath('frontend/Secomm/launchpad');
printf("theme=%s id=%d\n", $theme->getCode(), $theme->getId());

$design = $om->get(\Magento\Framework\View\DesignInterface::class);
$design->setDesignTheme($theme, Area::AREA_FRONTEND);

/** @var \Magento\Framework\Translate $translate */
$translate = $om->get(\Magento\Framework\Translate::class);
$translate->setLocale('vi_VN');
$translate->loadData(Area::AREA_FRONTEND, true);
$data = $translate->getData();
printf("locale=vi_VN dict=%d entries\n", count($data));

$cases = [
    ['We received too many requests for password resets. Please wait and try again later or contact %1.', ['support@secomm.vn']],
    ['Please correct the email address.', []],
];
Phrase::setRenderer($om->get(\Magento\Framework\Phrase\RendererInterface::class));
foreach ($cases as [$text, $args]) {
    printf(
        "dict=%s\n  %s\n",
        $data[$text] ?? '(fallback EN)',
        (string)(new Phrase($text, $args))
    );
}

echo "--- sample keys containing 'password reset':\n";
foreach ($data as $k => $v) {
    if (stripos($k, 'password reset') !== false) {
        echo "  $k => $v\n";
    }
}
