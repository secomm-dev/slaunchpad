<?php
/**
 * TASK-WN9VB0 — I18N verify cho 5 key mới của Quick View modal restyle:
 *   Information / Materials / "SKU code:" / "Decrease quantity" / "Increase quantity"
 *
 * Pattern ghép:
 *   - TASK-Z3DAH5/verify-i18n.php (verify-i18n cho QV, dict + file-level)
 *   - SLP-225: sau startEnvironmentEmulation phải set explicit design theme
 *     (emulation có thể không resolve theme -> dict thiếu theme CSV)
 *   - BUG-KFJ49A đợt 3: locale set trên OBJECT Translate (không phải ResolverInterface);
 *     Translate::getData() = flat map original => translated
 *   - LL-0007: CLI không tự wire Phrase renderer -> phải Phrase::setRenderer(...)
 *     trước khi render new Phrase, nếu không render trả English dù dict đúng
 *   - LL pattern: 1 process per store (dict không cache lại giữa 2 store)
 *
 * Usage (MUST run as secomm — root-run PHP CLI gây 500 sau đó):
 *   sudo -u secomm php /var/www/projects/slaunchpad/.ai/evidence/TASK-WN9VB0/verify-i18n-restyle.php default
 *   sudo -u secomm php /var/www/projects/slaunchpad/.ai/evidence/TASK-WN9VB0/verify-i18n-restyle.php launchpad_en
 *
 * Lưu ý (BUG-KFJ49A trap): theme en_US.csv KHÔNG load vào CLI dict — check EN qua
 * emulation chỉ là fallback-identity; bằng chứng EN chính thức = file-level CSV
 * identity (phần dưới, luôn chạy) + không curl được EN local (LL-0011).
 */

use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Phrase;

$storeCode = isset($argv[1]) ? $argv[1] : 'default';
require '/var/www/projects/slaunchpad/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();

// setAreaCode TRƯỚC startEnvironmentEmulation — nếu không fatal "Area code is not set"
$om->get(\Magento\Framework\App\State::class)->setAreaCode(Area::AREA_FRONTEND);

$storeId = $om->get(\Magento\Store\Model\StoreManagerInterface::class)
    ->getStore($storeCode)->getId();

$om->get(\Magento\Store\Model\App\Emulation::class)
    ->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

// SLP-225: set explicit theme
$theme = $om->get(\Magento\Framework\View\Design\Theme\ThemeProviderInterface::class)
    ->getThemeByFullPath('frontend/Secomm/launchpad');
$om->get(\Magento\Framework\View\DesignInterface::class)
    ->setDesignTheme($theme, Area::AREA_FRONTEND);

// BUG-KFJ49A: locale phải set trên object Translate. KHÔNG đọc từ store config:
// store launchpad_en KHÔNG có row general/locale/code (core_config_data chỉ có
// default=vi_VN + stores/1=vi_VN) nên config fallback vi_VN cho cả EN store
// (finding môi trường, khớp LL-0011 store-switch broken local). Map explicit:
$localeByStore = ['default' => 'vi_VN', 'launchpad_en' => 'en_US'];
if (!isset($localeByStore[$storeCode])) {
    fwrite(STDERR, "Unknown store code: {$storeCode}\n");
    exit(2);
}
$locale = $localeByStore[$storeCode];
$configLocale = $om->get(\Magento\Framework\App\Config\ScopeConfigInterface::class)
    ->getValue('general/locale/code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeCode);

/** @var \Magento\Framework\Translate $translate */
$translate = $om->get(\Magento\Framework\Translate::class);
$translate->setLocale($locale);
$translate->loadData(Area::AREA_FRONTEND, true);
$data = $translate->getData();

// LL-0007: wire renderer cho render-check thật
Phrase::setRenderer($om->get(\Magento\Framework\Phrase\RendererInterface::class));

$keys = ['Information', 'Materials', 'SKU code:', 'Decrease quantity', 'Increase quantity'];
$expectedVi = [
    'Information'       => 'Thông tin',
    'Materials'         => 'Chất liệu',
    'SKU code:'         => 'Mã SKU:',
    'Decrease quantity' => 'Giảm số lượng',
    'Increase quantity' => 'Tăng số lượng',
];
$isVi = ($storeCode !== 'launchpad_en');

printf(
    "== store=%s id=%d locale=%s (config locale=%s) theme=%s dict=%d entries ==\n",
    $storeCode,
    $storeId,
    $locale,
    var_export($configLocale, true),
    ($theme->getCode() ?: '(EMPTY)'),
    count($data)
);

$pass = 0;
$fail = 0;
foreach ($keys as $key) {
    $dict = array_key_exists($key, $data) ? (string)$data[$key] : null;
    $rendered = (string)(new Phrase($key));
    $want = $isVi ? $expectedVi[$key] : $key;
    // VI: dict phải hit đúng + render đúng. EN: render identity là đủ ở mức CLI
    // (theme en_US.csv không load vào CLI dict — xem header), file CSV check riêng.
    $ok = ($rendered === $want) && (!$isVi || $dict === $want);
    if ($ok) {
        $pass++;
        printf("PASS|%s|%s|dict=%s render=%s\n", $locale, $key, $dict ?? '(MISS->identity)', $rendered);
    } else {
        $fail++;
        printf("FAIL|%s|%s|dict=%s render=%s want=%s\n", $locale, $key, $dict ?? '(MISS->identity)', $rendered, $want);
    }
}

// File-level CSV check (cả 2 store): đúng file, đúng dòng — bằng chứng EN chính thức ở mức local
$themeI18n = '/var/www/projects/slaunchpad/app/design/frontend/Secomm/launchpad/i18n/';
foreach (['vi_VN.csv' => $expectedVi, 'en_US.csv' => array_combine($keys, $keys)] as $file => $wantMap) {
    $rows = [];
    if (($h = fopen($themeI18n . $file, 'r')) !== false) {
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 2) {
                $rows[$row[0]] = $row[1];
            }
        }
        fclose($h);
    }
    foreach ($wantMap as $key => $want) {
        $got = $rows[$key] ?? null;
        if ($got === $want) {
            $pass++;
            printf("PASS|file(%s)|%s|%s\n", $file, $key, $want);
        } else {
            $fail++;
            printf("FAIL|file(%s)|%s|got=%s want=%s\n", $file, $key, var_export($got, true), $want);
        }
    }
}

echo $fail === 0
    ? "I18N VERIFY (TASK-WN9VB0): {$pass}/{$pass} PASS\n"
    : "I18N VERIFY (TASK-WN9VB0): {$fail} FAIL\n";
exit($fail === 0 ? 0 : 1);
