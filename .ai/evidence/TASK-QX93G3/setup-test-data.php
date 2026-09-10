<?php
/**
 * TASK-QX93G3 local test data (idempotent):
 *  - Cart price rule "QuickCart QC 10%" with specific coupon QCQUICK10
 *  - CMS block identifier "cart-drawer-promo" (active, all-store default scope 0)
 * Cleanup after QC: delete both via Admin.
 */

use Magento\Framework\App\Bootstrap;

require '/var/www/projects/slaunchpad/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

// --- Cart price rule with coupon ---
$ruleCollection = $objectManager->create(\Magento\SalesRule\Model\ResourceModel\Rule\Collection::class);
$ruleCollection->addFieldToFilter('name', 'QuickCart QC 10%');
if ($ruleCollection->getSize() === 0) {
    /** @var \Magento\SalesRule\Model\Rule $rule */
    $rule = $objectManager->create(\Magento\SalesRule\Model\Rule::class);
    $rule->setName('QuickCart QC 10%')
        ->setDescription('TASK-QX93G3 test rule — delete after QC')
        ->setCouponType(2) // COUPON_TYPE_SPECIFIC_COUPON
        ->setCouponCode('QCQUICK10')
        ->setSimpleAction('by_percent')
        ->setDiscountAmount(10)
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([0, 1, 2, 3])
        ->setFromDate('2026-09-01') // backdated — validator date filter runs behind local date
        ->setIsActive(1)
        ->setSortOrder(100)
        ->setStopRulesProcessing(0)
        ->save();
    echo "created salesrule id=" . $rule->getId() . " coupon=QCQUICK10\n";
} else {
    echo "salesrule exists id=" . $ruleCollection->getFirstItem()->getId() . "\n";
}

// --- CMS block for drawer promo ---
$blockCollection = $objectManager->create(\Magento\Cms\Model\ResourceModel\Block\Collection::class);
$blockCollection->addFieldToFilter('identifier', 'cart-drawer-promo');
if ($blockCollection->getSize() === 0) {
    /** @var \Magento\Cms\Model\Block $cmsBlock */
    $cmsBlock = $objectManager->create(\Magento\Cms\Model\Block::class);
    $cmsBlock->setTitle('QuickCart drawer promo (TASK-QX93G3)')
        ->setIdentifier('cart-drawer-promo')
        ->setContent('<div class="quickcart-promo message info">🎉 Giảm thêm 10% với mã <strong>QCQUICK10</strong> — áp dụng ngay trong giỏ hàng!</div>')
        ->setIsActive(1)
        ->save();
    echo "created cms_block id=" . $cmsBlock->getId() . " identifier=cart-drawer-promo\n";
} else {
    echo "cms_block exists id=" . $blockCollection->getFirstItem()->getId() . "\n";
}
