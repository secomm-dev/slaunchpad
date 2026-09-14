<?php
// SLP-212 analysis: extract __() phrases from product-list page templates, diff vs vi_VN.csv dictionaries
chdir('/var/www/projects/slaunchpad');
$hyva = 'vendor/hyva-themes/magento2-default-theme';

$files = array_merge(
  glob($hyva . '/Magento_Catalog/templates/product/list*.phtml'),
  glob($hyva . '/Magento_Catalog/templates/product/list/*.phtml'),
  glob($hyva . '/Magento_Catalog/templates/product/list/toolbar/*.phtml'),
  glob($hyva . '/Magento_LayeredNavigation/templates/*.phtml'),
  glob($hyva . '/Magento_LayeredNavigation/templates/**/*.phtml'),
  glob($hyva . '/Magento_Swatches/templates/*.phtml'),
  glob($hyva . '/Magento_Review/templates/*.phtml'),
  glob($hyva . '/Magento_Review/templates/**/*.phtml'),
  glob($hyva . '/Magento_Wishlist/templates/product/list/*.phtml'),
  glob('app/design/frontend/Secomm/launchpad/Hyva_SmileElasticsuite/templates/**/*.phtml'),
  glob($hyva . '/Magento_Theme/templates/*.phtml')
);

// load ALL vi dictionaries (theme + Hyva module-level) since merged dict = theme + every module
$dict = [];
$loadCsv = function ($path) use (&$dict) {
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $row = str_getcsv($line);
        if (count($row) >= 2) { $dict[trim($row[0])] = $row[1]; }
    }
};
$loadCsv('app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv');
foreach (glob('vendor/hyva-themes/magento2-*/i18n/vi_VN.csv') as $f) { $loadCsv($f); }
foreach (glob('vendor/*/*/i18n/vi_VN.csv') as $f) { $loadCsv($f); }

$missing = [];
$checked = 0;
foreach (array_unique(array_filter($files)) as $f) {
    if (!file_exists($f)) { continue; }
    $src = file_get_contents($f);
    preg_match_all("/__\(\s*(['\"])((?:[^'\"\\\\]|\\\\.)*?)\\1/", $src, $m);
    foreach ($m[2] as $p) {
        $checked++;
        $p = stripcslashes($p);
        if ($p === '' || !isset($dict[$p])) {
            $missing[$p] = str_replace('vendor/hyva-themes/magento2-default-theme/', '', str_replace('/var/www/projects/slaunchpad/', '', $f));
        }
    }
}
echo "=== PLP phrases WITHOUT vi translation (theme vi_VN.csv = " . count($dict) . " keys loaded) ===\n";
foreach ($missing as $p => $src) {
    printf("%-60s | %s\n", $p, $src);
}
echo 'TOTAL distinct missing: ' . count($missing) . " / phrases scanned: $checked / files: " . count(array_unique(array_filter($files))) . "\n";
