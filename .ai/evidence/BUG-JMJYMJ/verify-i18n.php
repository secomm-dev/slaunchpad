<?php
// Verify theme dictionary loads + contains search result page phrases (BUG-JMJYMJ / SLP-206)
// Pattern: .ai/runtime/evidence/BUG-8K1TBB/verify-i18n.php — run as secomm.
require '/var/www/projects/slaunchpad/app/bootstrap.php';

use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Translate;
use Magento\Framework\View\DesignInterface;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();

$om->get(ResolverInterface::class)->setLocale('vi_VN');
$om->get(\Magento\Framework\App\State::class)->setAreaCode(Area::AREA_FRONTEND);
$om->get(DesignInterface::class)->setDesignTheme('Secomm/launchpad');

/** @var Translate $translate */
$translate = $om->get(Translate::class);
$translate->loadData(Area::AREA_FRONTEND, true);
$data = $translate->getData();

$phrases = [
    "Search results for: '%1'",
    "No exact results found for: <b>'%1'</b>. The displayed items are the closest matches.",
    'Did you mean',
    'Relevance',
    'Your search returned no results.',
    'Related search terms',
];

$expected = [
    "Kết quả tìm kiếm cho: '%1'",
    "Không tìm thấy kết quả chính xác cho: <b>'%1'</b>. Các sản phẩm hiển thị là những kết quả phù hợp nhất.",
    'Ý của bạn là',
    'Độ phù hợp',
    'Tìm kiếm của bạn không trả về kết quả nào.',
    'Từ khoá tìm kiếm liên quan',
];

$missing = 0;
printf("Dictionary entries loaded: %d%s", count($data), PHP_EOL);
foreach ($phrases as $i => $p) {
    if (isset($data[$p]) && $data[$p] === $expected[$i]) {
        printf("OK       %s => %s%s", $p, $data[$p], PHP_EOL);
    } else {
        $missing++;
        printf(
            "FAIL     %s => %s (expected: %s)%s",
            $p,
            $data[$p] ?? '(not in dictionary)',
            $expected[$i],
            PHP_EOL
        );
    }
}
printf("Failed: %d/%d%s", $missing, count($phrases), PHP_EOL);
exit($missing === 0 ? 0 : 1);
