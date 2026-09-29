<?php
/**
 * TASK-7EYJ4C — render each footer CMS block under store emulation (store 1 = vi,
 * store 2 = en) to prove per-store content resolution, independent of the local
 * store-switch quirk (LL-0011).
 *
 * Usage: sudo -u secomm php verify-blocks-per-store.php
 */

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Store\Model\App\Emulation;

$root = dirname(__DIR__, 3);
require $root . '/app/bootstrap.php';

$_SERVER['HTTP_HOST'] = 'slaunchpad.localhost';
$bootstrap = Bootstrap::create($root, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$objectManager->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');
$emulation = $objectManager->get(Emulation::class);

$checks = [
    1 => [
        'footer_newsletter' => ['HÃY LIÊN HỆ VỚI CHÚNG TÔI!', 'Launchpad\CmsContent\Block\Newsletter\Subscribe'],
        'footer_links' => ['Công ty', 'Tài khoản của tôi'],
        'footer_social' => ['THEO DÕI CHÚNG TÔI TẠI', 'social-facebook.png'],
        'footer_trust_payments' => ['bo-cong-thuong-badge.png', 'payment-visa.png'],
    ],
    2 => [
        'footer_newsletter' => ['Subscribe to our newsletter', 'Launchpad\CmsContent\Block\Newsletter\Subscribe'],
        'footer_links' => ['Company', 'My orders'],
        'footer_social' => ['Follow us on', 'social-twitter.png'],
        'footer_trust_payments' => ['bo-cong-thuong-badge.png', 'payment-google-pay.png'],
    ],
];

foreach ($checks as $storeId => $blocks) {
    $emulation->startEnvironmentEmulation($storeId);
    echo "== store $storeId ==\n";
    foreach ($blocks as $identifier => $needles) {
        $html = $objectManager->create(\Magento\Cms\Block\Block::class)
            ->setBlockId($identifier)
            ->toHtml();
        $missing = array_filter(
            $needles,
            fn (string $n): bool => !str_contains($html, $n)
        );
        $leak = str_contains($html, '{{block') || str_contains($html, '{{media') || str_contains($html, '{{store');
        printf(
            "%-22s %6d bytes | %s%s\n",
            $identifier,
            strlen($html),
            $missing === [] ? 'OK ' : 'MISSING: ' . implode(', ', $missing),
            $leak ? ' | DIRECTIVE LEAK' : ''
        );
    }
    $emulation->stopEnvironmentEmulation();
}
