<?php
/**
 * TASK-8V7ANH / SLP-216 — framework verification.
 * Per-store (1 process/store — LL-0004): emulate store frontend, load translate
 * (theme dictionary Secomm/launchpad), wire Phrase renderer, then:
 *   1. assert phrase "Track your order" resolves per expected dictionary value
 *   2. render the REAL block+template Magento_Shipping::tracking/link.phtml with
 *      a layout-style label argument (new Phrase) against a real order from DB —
 *      output must contain expected label and must NOT contain the other locale.
 *
 * Translation boot pattern: BUG-GJT6C1 verify-render.php (LL-0007 — CLI must wire
 * Phrase renderer explicitly; web bootstrap does it, CLI does not).
 * Run as secomm: php verify-dictionary.php <storeCode> <expectedLabel>
 *   php verify-dictionary.php default "Theo dõi đơn hàng"
 *   php verify-dictionary.php launchpad_en "Track your order"
 */
require '/var/www/projects/slaunchpad/app/bootstrap.php';

use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Translate;
use Magento\Framework\View\DesignInterface;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om        = $bootstrap->getObjectManager();

$om->get(State::class)->setAreaCode(Area::AREA_FRONTEND);
Phrase::setRenderer($om->get(Phrase\RendererInterface::class));

$storeCode  = $argv[1] ?? 'default';
$expected   = $argv[2] ?? 'Theo dõi đơn hàng';
$source     = 'Track your order';

$storeManager   = $om->get(\Magento\Store\Model\StoreManagerInterface::class);
$localeResolver = $om->get(ResolverInterface::class);
$design         = $om->get(DesignInterface::class);
$translate      = $om->get(Translate::class);

$store = $storeManager->getStore($storeCode);
$storeManager->setCurrentStore($store->getId());
$localeResolver->emulate($store->getId());
$design->setDesignTheme('Secomm/launchpad');
$translate->loadData(Area::AREA_FRONTEND, true);

$fail = 0;
$pass = 0;

// 1) dictionary level — theme CSV row resolves
$rendered = (string) new Phrase($source);
$ok = $rendered === $expected;
printf("[%s] 1.dict_phrase      %s  rendered=%s\n", $storeCode, $ok ? 'PASS' : 'FAIL', $rendered);
$ok ? $pass++ : $fail++;

// 2) render level — real template via real block, label as layout argument would
//    produce it (TranslateDecorator wraps translate="true" args into Phrase)
$order = null;
try {
    $order = $om->create(\Magento\Sales\Model\OrderFactory::class)->create()
        ->getCollection()->setPageSize(1)->getFirstItem();
} catch (\Throwable $e) {
    printf("[%s] 2.render_block     SKIP  (no sales_order: %s)\n", $storeCode, $e->getMessage());
}
if ($order && $order->getId()) {
    $om->get(\Magento\Framework\Registry::class)->register('current_order', $order);
    $block = $om->create(\Magento\Shipping\Block\Tracking\Link::class);
    $block->setLabel(new Phrase($source));
    $block->setTemplate('Magento_Shipping::tracking/link.phtml');
    $html = $block->toHtml();
    $ok = strpos($html, $expected) !== false;
    printf("[%s] 2.render_block     %s  contains-expected=%s\n", $storeCode, $ok ? 'PASS' : 'FAIL', var_export($ok, true));
    $ok ? $pass++ : $fail++;
    $wrong = $storeCode !== 'launchpad_en' ? $source : 'Theo dõi đơn hàng';
    $ok = strpos($html, $wrong) === false;
    printf("[%s] 3.render_no_other  %s  other-locale-absent=%s\n", $storeCode, $ok ? 'PASS' : 'FAIL', var_export($ok, true));
    $ok ? $pass++ : $fail++;
    @file_put_contents(__DIR__ . sprintf('/render-%s.html', $storeCode), $html);
}

printf("[%s] RESULT             %d pass / %d fail\n", $storeCode, $pass, $fail);
exit($fail === 0 ? 0 : 1);
