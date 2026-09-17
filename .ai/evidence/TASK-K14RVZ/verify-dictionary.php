<?php
/**
 * TASK-K14RVZ (SLP-217) verify framework-level: theme dictionary per-locale
 * cho 6 browser-validation message override keys.
 * Pattern: SLP-225 (explicit design theme + locale, không dựa vào store emulation).
 * Expect: vi_VN → bản dịch VI; en_US → identity (mirror en_US.csv).
 * Chạy: sudo -u secomm php verify-dictionary.php
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

$phrases = [
    'Please fill out this field.',
    'Value must be less than or equal to %1.',
    'Value must be greater than or equal to %1.',
    'Please match the format requested.',
    'Please enter a valid value.',
    'Please enter a valid number.',
];

$expect = [
    'vi_VN' => [
        'Vui lòng điền vào trường này.',
        'Giá trị phải nhỏ hơn hoặc bằng %1.',
        'Giá trị phải lớn hơn hoặc bằng %1.',
        'Vui lòng nhập đúng định dạng.',
        'Vui lòng nhập một giá trị hợp lệ.',
        'Vui lòng nhập một số hợp lệ.',
    ],
    'en_US' => $phrases, // identity mirror
];

Phrase::setRenderer($om->get(\Magento\Framework\Phrase\RendererInterface::class));

$pass = 0; $fail = 0;
foreach ($expect as $locale => $expected) {
    $translate = $om->get(\Magento\Framework\Translate::class);
    $translate->setLocale($locale);
    $translate->loadData(Area::AREA_FRONTEND, true);
    $data = $translate->getData();
    printf("\n=== locale=%s dict=%d entries ===\n", $locale, count($data));
    foreach ($phrases as $i => $text) {
        $dict  = $data[$text] ?? '(MISSING)';
        $rend  = (string)(new Phrase($text));
        $ok    = ($rend === $expected[$i]);
        $ok ? $pass++ : $fail++;
        printf(
            "%s  [%s] dict=%s\n      render=%s\n",
            $ok ? 'PASS' : 'FAIL',
            $text,
            $dict,
            $rend
        );
    }
}
printf("\nTOTAL: %d PASS, %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
