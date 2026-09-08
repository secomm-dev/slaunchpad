<?php
/**
 * BUG-5NR0PD / SLP-150 — verify language pack app/i18n/Secomm/vi_VN through the
 * CHECKOUT runtime context: theme Magento/luma (the scope the OSC checkout page
 * actually runs — LL-0011), locale per store, Phrase renderer wired.
 *
 * ONE STORE PER PROCESS — usage: php verify-pack-checkout.php <storeCode>
 *   store 'default'      => vi_VN  (expect VI values)
 *   store 'launchpad_en' => en_US  (expect identity EN)
 * Run as secomm: php verify-pack-checkout.php default
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

$storeCode = $argv[1] ?? 'default';
$store     = $storeManager->getStore($storeCode);
$storeManager->setCurrentStore($store->getId());
$localeResolver->emulate($store->getId());
// checkout OSC renders under Magento/luma — NOT Secomm/launchpad (LL-0011)
$om->get(\Magento\Framework\View\DesignInterface::class)->setDesignTheme('Magento/luma');
$translate->loadData(Area::AREA_FRONTEND, true);

$isVi = $storeCode !== 'launchpad_en';
$expectations = $isVi
    ? [
        'Your coupon was successfully applied.'            => 'Mã giảm giá đã được áp dụng thành công.',
        'Your coupon was successfully removed.'            => 'Mã giảm giá đã được xóa thành công.',
        'The selected shipping method is not applicable to your order. Please contact us for more details.'
                                                           => 'Phương thức vận chuyển đã chọn không áp dụng cho đơn hàng của bạn. Vui lòng liên hệ với chúng tôi để biết thêm chi tiết.',
      ]
    : [
        'Your coupon was successfully applied.'            => 'Your coupon was successfully applied.',
        'Your coupon was successfully removed.'            => 'Your coupon was successfully removed.',
      ];

$pass = 0;
foreach ($expectations as $source => $expected) {
    $actual = (string) __($source);
    $ok     = $actual === $expected;
    $pass  += $ok ? 1 : 0;
    printf(
        "[%s] %s\n  expected: %s\n  actual  : %s\n",
        $ok ? 'PASS' : 'FAIL',
        $source,
        $expected,
        $actual
    );
}
printf("%s: %d/%d PASS (store=%s, theme=Magento/luma)\n", $isVi ? 'VI STORE' : 'EN STORE', $pass, count($expectations), $storeCode);
exit($pass === count($expectations) ? 0 : 1);
