<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Plugin\Adminhtml;

use Secomm\GiaoHangNhanh\Model\Config;
use Magento\Sales\Block\Adminhtml\Order\View;
use Magento\Sales\Model\Order;

/**
 * Adds "Sync GHN" button to Admin Order View when:
 * - Manual sync is enabled in config
 * - Order uses GHN shipping
 * - Order has not been synced yet (no tracking_code)
 */
class OrderViewPlugin
{
    /**
     * @param Config $config
     */
    public function __construct(private readonly Config $config) {}

    /**
     * @param View $subject
     * @return void
     */
    public function beforeSetLayout(View $subject)
    {
        if (!$this->config->isEnableAdminManualSyncButton()) {
            return;
        }

        $order = $subject->getOrder();
        if (!$order instanceof Order) {
            return;
        }

        // Only GHN shipping
        if (false === strpos((string) $order->getShippingMethod(), Config::GHN_CODE)) {
            return;
        }

        // Only if not yet synced
        if ($order->getTrackingCode()) {
            return;
        }

        $subject->addButton(
            'ghn_sync',
            [
                'label' => __('Sync GHN'),
                'class' => 'ghn-sync',
                'onclick' => 'setLocation(\'' . $subject->getUrl('ghn/order/sync', ['order_id' => $order->getId()]) . '\')'
            ]
        );
    }
}
