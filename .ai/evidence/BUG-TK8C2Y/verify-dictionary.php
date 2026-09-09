<?php
/**
 * BUG-TK8C2Y / SLP-144 — framework verification (dictionary level + template fallback).
 * Boot Magento, area frontend, resolve phrases through the theme dictionary per
 * store (vi_VN / launchpad_en) + assert the child-theme override wins the template
 * fallback for Mageplaza_ExtraFee::hyva/cart/totals/extra-fee.phtml + CSV format.
 *
 * Translation boot pattern: BUG-5NR0PD verify-dictionary.php (originally BUG-GJT6C1).
 * ONE STORE PER PROCESS — usage: php verify-dictionary.php <storeCode>
 *   store 'default'      => vi_VN  (expect VI values)
 *   store 'launchpad_en' => en_US  (expect identity)
 * Run as secomm: php verify-dictionary.php default
 */
require '/var/www/projects/slaunchpad/app/bootstrap.php';

use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Translate;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om        = $bootstrap->getObjectManager();

$om->get(\Magento\Framework\App\State::class)->setAreaCode(Area::AREA_FRONTEND);

$storeManager   = $om->get(\Magento\Store\Model\StoreManagerInterface::class);
$localeResolver = $om->get(ResolverInterface::class);
$translate      = $om->get(Translate::class);

// wire the Phrase renderer chain (web bootstrap does this; CLI does not)
\Magento\Framework\Phrase::setRenderer($om->get(\Magento\Framework\Phrase\RendererInterface::class));

// one store per process
$storeCode = $argv[1] ?? 'default';
$store     = $storeManager->getStore($storeCode);
$storeManager->setCurrentStore($store->getId());
$localeResolver->emulate($store->getId());
$design = $om->get(\Magento\Framework\View\DesignInterface::class);
$design->setDesignTheme('Secomm/launchpad');
$translate->loadData(Area::AREA_FRONTEND, true);

$expectations = $storeCode === 'launchpad_en'
    ? [
        ' (Excl. Tax)' => ' (Excl. Tax)',   // ticket keys — must stay identity
        ' (Incl. Tax)' => ' (Incl. Tax)',
        'This is a required field.' => 'This is a required field.', // ticket key 2 (scope ext 09-09): extra fee form validation
        'Delivery Date' => 'Delivery Date', // regression: dict intact
      ]
    : [
        ' (Excl. Tax)' => ' (chưa gồm thuế)', // ticket keys
        ' (Incl. Tax)' => ' (đã gồm thuế)',
        'This is a required field.' => 'Trường này là bắt buộc.', // ticket key 2 (scope ext 09-09) — pre-existing key (SLP-133)
        'Delivery Date' => 'Ngày giao hàng',  // regression: dict intact
      ];

$fail = 0;
$pass = 0;
foreach ($expectations as $phrase => $expected) {
    $actual = (string) new \Magento\Framework\Phrase($phrase);
    $ok     = $actual === $expected;
    printf("[%s] %-16s %s => %s\n", $storeCode, $ok ? 'PASS' : 'FAIL', $phrase, $actual);
    $ok ? $pass++ : $fail++;
}

// Template fallback: the child-theme override must win over the vendor file.
$fallback = $om->get(\Magento\Framework\View\Design\FileResolution\Fallback\TemplateFile::class);
$resolved = $fallback->getFile(
    Area::AREA_FRONTEND,
    $design->getDesignTheme(),
    'Mageplaza_ExtraFee',
    'hyva/cart/totals/extra-fee.phtml'
);
$expectedPath = BP . '/app/design/frontend/Secomm/launchpad/Mageplaza_ExtraFee/templates/hyva/cart/totals/extra-fee.phtml';
$ok = $resolved === $expectedPath && is_readable($resolved);
printf("[%s] %-16s template fallback => %s\n", $storeCode, $ok ? 'PASS' : 'FAIL', $resolved ?: '(null)');
$ok ? $pass++ : $fail++;

// CSV format sanity: every row exactly 2 columns, no new duplicate keys.
// Tolerant line-split (file mixes CRLF + LF-only rows from earlier tickets — fgetcsv parses both).
// Whitelist pre-existing same-value dups (BUG-5S2Z25): Email, Comments, Enter your comment here.
$dupWhitelist = ['Email', 'Comments', 'Enter your comment here'];
foreach (['app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv',
          'app/design/frontend/Secomm/launchpad/i18n/en_US.csv'] as $csv) {
    $raw  = file_get_contents(BP . '/' . $csv);
    $rows = array_filter(preg_split('/\r\n|\n|\r/', $raw), static fn ($r) => $r !== '');
    $seen = [];
    $bad  = 0;
    foreach ($rows as $i => $row) {
        $f = str_getcsv($row);
        if (count($f) !== 2) { printf("[%s] CSV-COL FAIL line %d (%d cols): %s\n", basename($csv), $i + 1, count($f), substr($row, 0, 80)); $bad++; continue; }
        if (isset($seen[$f[0]]) && !in_array($f[0], $dupWhitelist, true)) { printf("[%s] CSV-DUP FAIL: \"%s\"\n", basename($csv), $f[0]); $bad++; }
        $seen[$f[0]] = true;
    }
    printf("[%s] %-16s rows=%d %s\n", basename($csv), 'format', count($rows), $bad === 0 ? 'PASS' : "FAIL x{$bad}");
    $bad === 0 ? $pass++ : $fail += $bad;
}

$localeResolver->revert();
echo $fail === 0 ? "ALL PASS ({$pass} checks)\n" : "{$fail} FAIL / {$pass} PASS\n";
exit($fail === 0 ? 0 : 1);
