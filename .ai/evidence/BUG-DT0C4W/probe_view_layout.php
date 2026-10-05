<?php
/**
 * BUG-DT0C4W — code-level verification: the shipment view page handle
 * `adminhtml_order_shipment_view` now hosts the Secomm sections (dead handle fixed) and the
 * "Show Packages" button is hidden for markers-only shipments.
 *
 * Run: php .ai/evidence/BUG-DT0C4W/probe_view_layout.php <shipment_id>
 */
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Sales\Api\ShipmentRepositoryInterface;

require __DIR__ . '/../../../app/bootstrap.php';
$params = $_SERVER;
$params[\Magento\Store\Model\StoreManager::PARAM_RUN_CODE] = 'admin';
$params[\Magento\Store\Model\Store::ADMIN_CODE] = 'admin';
$om = \Magento\Framework\App\Bootstrap::create(BP, $params)->getObjectManager();
$om->get(State::class)->setAreaCode(Area::AREA_ADMINHTML);

$shipmentId = (int) ($argv[1] ?? 0);
$shipment = $shipmentId > 0
    ? $om->get(ShipmentRepositoryInterface::class)->get($shipmentId)
    : null;
if (!$shipment || !$shipment->getId()) {
    echo "usage: php probe_view_layout.php <shipment_id>\n";
    exit(1);
}
$om->get(\Magento\Framework\Registry::class)->register('current_shipment', $shipment);

$packagesRaw = (array) $shipment->getPackages();
echo 'shipment ', $shipmentId, ' packages keys: ', implode(',', array_keys($packagesRaw)) ?: '(none)', "\n";

/** @var \Magento\Framework\View\Result\Page $page */
$page = $om->create(\Magento\Framework\View\Result\Page::class);
$page->addHandle(['default', 'adminhtml_order_shipment_view']);
$page->getLayout();
$layout = $page->getLayout();

$failures = 0;
$check = function (string $name, bool $ok, string $detail = '') use (&$failures): void {
    printf("%s %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $detail !== '' ? " — {$detail}" : '');
    if (!$ok) {
        $failures++;
    }
};

foreach ([
    'secomm_ghn.shipment.view.provider_status',
    'secomm_ghn.shipment.view.actions',
    'secomm_shippingcore.shipment.view.fulfillment_status',
] as $blockName) {
    $block = $layout->getBlock($blockName);
    $check("layout has block {$blockName}", $block !== null);
    if ($block !== null) {
        try {
            $html = $block->toHtml();
            // Rendered-ness must MATCH the block's own visibility contract (0 bytes is the
            // CORRECT outcome for a hidden section — e.g. Actions on a CANCELLED shipment).
            $shouldRender = match (true) {
                method_exists($block, 'canShow') => (bool) $block->canShow(),
                // Actions template gate: section only when the section shows AND at least one
                // button applies (canCancel()/canReturn()).
                method_exists($block, 'canShowActions') => (bool) $block->canShowActions()
                    && (
                        !method_exists($block, 'canCancel')
                        || (bool) $block->canCancel()
                        || (bool) $block->canReturn()
                    ),
                default => true,
            };
            $check(
                "block {$blockName} renders-according-to-visibility",
                $shouldRender === ($html !== ''),
                strlen($html) . ' bytes, expected ' . ($shouldRender ? 'visible' : 'hidden')
            );
        } catch (\Throwable $e) {
            $check("block {$blockName} renders", false, get_class($e) . ': ' . $e->getMessage());
        }
    }
}

$form = $layout->getBlock('form');
if ($form !== null) {
    $button = $form->getShowPackagesButton();
    $nativeCount = 0;
    foreach ($packagesRaw as $key => $value) {
        if (is_int($key)) {
            $nativeCount++;
        }
    }
    if ($nativeCount === 0) {
        $check('Show Packages button hidden (no native packages)', $button === '', 'html="' . substr($button, 0, 60) . '"');
    } else {
        $check('Show Packages button kept (native packages)', $button !== '');
    }
} else {
    $check('layout has core form block', false);
}

exit($failures === 0 ? 0 : 1);
