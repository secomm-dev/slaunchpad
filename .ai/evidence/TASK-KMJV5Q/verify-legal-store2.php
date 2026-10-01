<?php
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;

require '/var/www/projects/slaunchpad/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(State::class)->setAreaCode('frontend');
$storeManager = $om->get(StoreManagerInterface::class);

foreach (['default', 'launchpad_en'] as $code) {
    $storeManager->setCurrentStore($code);
    $vm = $om->create(\Launchpad\CmsContent\ViewModel\LegalLinks::class);
    printf(
        "%s => terms_label=%s | terms_url=%s | privacy_url=%s\n",
        $code,
        $vm->getTermsLabel() ?? '(null)',
        $vm->getTermsUrl() ?? '(null)',
        $vm->getPrivacyUrl() ?? '(null)'
    );
}
