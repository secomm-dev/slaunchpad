<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Plugin\Adminhtml;

use Magento\Backend\Model\UrlInterface;
use Magento\Sales\Block\Adminhtml\Order\View;
use Secomm\Ahamove\Helper\Data as AhamoveHelper;

class AddPushAhamoveOrderButtonPlugin
{
    public function __construct(
        protected UrlInterface   $urlBuilder,
        protected AhamoveHelper $ahamoveHelper
    ) {
    }

    /**
     * Add "Send to Ahamove" button to Admin Sales Order View page
     *
     * @param View $subject
     * @return void
     */
    public function beforeSetLayout(View $subject): void
    {
        try {
            $order = $subject->getOrder();
            if (!$order) {
                return;
            }

            $shippingMethod = (string)$order->getShippingMethod();
            if (!str_contains($shippingMethod, 'ahamove')) {
                return;
            }

            $storeId = $order->getStoreId();
            $manualButton = (bool)$this->ahamoveHelper->getConfig('ahamove/general/manual_push_button', $storeId);
            if (!$manualButton) {
                return;
            }

            // Do not show button for canceled, holded, closed orders or un-shippable orders without shipment
            if ($order->isCanceled()
                || $order->getState() === \Magento\Sales\Model\Order::STATE_HOLDED
                || $order->getState() === \Magento\Sales\Model\Order::STATE_CLOSED
            ) {
                return;
            }

            if (!$order->canShip() && !$order->hasShipments()) {
                return;
            }

            // Check if order already has an Ahamove tracking code in any of its shipments
            $hasTracking = false;
            $shipments = $order->getShipmentsCollection();
            if ($shipments && $shipments->getSize() > 0) {
                foreach ($shipments as $shipment) {
                    foreach ($shipment->getAllTracks() as $track) {
                        if (str_contains((string)$track->getCarrierCode(), 'ahamove') && !empty($track->getTrackNumber())) {
                            $hasTracking = true;
                            break 2;
                        }
                    }
                }
            }

            if ($hasTracking) {
                return;
            }

            $pushUrl = $this->urlBuilder->getUrl(
                'ahamove/order/pushAhamove',
                ['order_id' => $order->getId()]
            );

            $subject->addButton(
                'push_ahamove_order',
                [
                    'label' => __('Send to Ahamove'),
                    'class' => 'action-secondary ahamove-push-btn',
                    'onclick' => 'confirmSetLocation(\'' . __('Send order #' . $order->getIncrementId() . ' to Ahamove for delivery?') . '\', \'' . $pushUrl . '\')'
                ]
            );
        } catch (\Exception $e) {
            // Ignore button injection error gracefully
        }
    }
}
