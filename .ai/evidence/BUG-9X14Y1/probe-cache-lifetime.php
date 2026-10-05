<?php
/**
 * BUG-9X14Y1 probe — isSaleEnded() + getCacheLifetime() cho các giá trị sale_end.
 * Run as secomm từ magento root: php probe-cache-lifetime.php
 */
use Magento\Framework\App\Bootstrap;

require '/var/www/projects/slaunchpad/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();

$getLifetime = new ReflectionMethod(
    \Launchpad\CmsContent\Block\Product\FlashSaleList::class,
    'getCacheLifetime'
);
$getLifetime->setAccessible(true);

$cases = [
    '2026-10-01 12:00:00', // tương lai gần (~2h) — cap phải < 86400
    '2026-10-21 17:00:00', // tương lai xa (~20 ngày) — cap parent 86400 thắng
    '2020-01-01 00:00:00', // quá khứ — floor 1s
    '',                    // rỗng — midnight kế tiếp, evergreen
    'not-a-date',          // sai format — fallback midnight
];
$now = time();
foreach ($cases as $raw) {
    $block = $om->create(\Launchpad\CmsContent\Block\Product\FlashSaleList::class, [
        'data' => ['sale_end' => $raw],
    ]);
    $lifetime = $getLifetime->invoke($block);
    $ended = $block->isSaleEnded() ? 'ENDED ' : 'active';
    $left = $lifetime !== null ? ' lifetime=' . $lifetime . 's' : ' lifetime=null';
    echo sprintf(
        "sale_end=%-22s %s%s (isSaleEnded=%s, endMillis-left=%ds)\n",
        $raw === '' ? '(empty)' : $raw,
        $ended,
        $left,
        $block->isSaleEnded() ? '1' : '0',
        (int) ceil(($block->getSaleEndMillis() - $now * 1000) / 1000)
    );
}
