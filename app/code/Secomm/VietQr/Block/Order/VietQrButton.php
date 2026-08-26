<?php
declare(strict_types=1);

namespace Secomm\VietQr\Block\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Secomm\VietQr\Model\Config;

/**
 * Renders the "View VietQR Payment" button on the customer order view page.
 *
 * The button is shown only for orders placed with the VietQR payment method
 * that are still in the pending payment status.
 */
class VietQrButton extends \Magento\Framework\View\Element\Template
{
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether the "View VietQR Payment" button should be shown for the given order.
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function canShowButton(OrderInterface $order): bool
    {
        $payment = $order->getPayment();
        if (!$payment || $payment->getMethod() !== 'secomm_vietqr') {
            return false;
        }
        return $order->getStatus() === $this->config->getNewOrderStatus();
    }

    /**
     * Get the URL to the VietQR payment view page for the given order.
     *
     * @param OrderInterface $order
     * @return string
     */
    public function getPaymentViewUrl(OrderInterface $order): string
    {
        $params = ['order_id' => $order->getId(), 'from' => 'order'];
        if ((bool)$order->getCustomerIsGuest()) {
            $params['key'] = $order->getProtectCode();
        }
        return $this->getUrl('vietqr/payment/view', $params);
    }
}
