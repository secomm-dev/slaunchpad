<?php
// Verify theme dictionary contains Quick View phrases (TASK-Z3DAH5 / SLP-157)
// Pattern: .ai/evidence/BUG-JMJYMJ/verify-i18n.php — run as secomm.
require '/var/www/projects/slaunchpad/app/bootstrap.php';

use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Translate;
use Magento\Framework\View\DesignInterface;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();

$om->get(\Magento\Framework\App\State::class)->setAreaCode(Area::AREA_FRONTEND);

$phrases = [
    'Quick view',
    'Choose an Option...',
    'You need to choose options for your item.',
    'There was a problem adding your item to the cart.',
    'Unable to load the product. Please try again.',
];
$expected = [
    'vi_VN' => [
        'Xem nhanh',
        'Chọn một tùy chọn...',
        'Bạn cần chọn tùy chọn cho sản phẩm.',
        'Đã xảy ra lỗi khi thêm sản phẩm vào giỏ hàng.',
        'Không thể tải thông tin sản phẩm. Vui lòng thử lại.',
    ],
    'en_US' => null, // identity
];

$pass = 0; $fail = 0;
// vi_VN: framework-level (merged dictionary, same pattern as BUG-JMJYMJ)
$om->get(ResolverInterface::class)->setLocale('vi_VN');
$om->get(DesignInterface::class)->setDesignTheme('Secomm/launchpad');
/** @var Translate $translate */
$translate = $om->get(Translate::class);
$translate->loadData(Area::AREA_FRONTEND, true);
$data = $translate->getData();
foreach ($phrases as $i => $phrase) {
    $got = (string)($data[$phrase] ?? '');
    $want = $expected['vi_VN'][$i];
    if ($got === $want) { $pass++; echo "PASS|vi_VN|$phrase|$got\n"; }
    else { $fail++; echo "FAIL|vi_VN|$phrase|got=$got want=$want\n"; }
}
// en_US: file-level identity check (Translate singleton caches the vi dict in CLI;
// live en identity is covered by Playwright T5b/T5c)
$file = '/var/www/projects/slaunchpad/app/design/frontend/Secomm/launchpad/i18n/en_US.csv';
$rows = [];
if (($h = fopen($file, 'r')) !== false) {
    while (($row = fgetcsv($h)) !== false) { if (count($row) >= 2) { $rows[$row[0]] = $row[1]; } }
    fclose($h);
}
foreach ($phrases as $phrase) {
    $got = $rows[$phrase] ?? null;
    if ($got === $phrase) { $pass++; echo "PASS|en_US(file)|$phrase|identity\n"; }
    else { $fail++; echo "FAIL|en_US(file)|$phrase|got=" . var_export($got, true) . " want=identity\n"; }
}
echo $fail === 0 ? "DICTIONARY VERIFY: {$pass}/{$pass} PASS\n" : "DICTIONARY VERIFY: {$fail} FAIL\n";
exit($fail === 0 ? 0 : 1);
