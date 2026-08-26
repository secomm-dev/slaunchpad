<?php

declare(strict_types=1);

namespace Secomm\VietQr\Plugin;

use Magento\Checkout\Controller\Onepage\Success;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\UrlInterface;

/**
 * Redirects the checkout success page to the VietQR payment view
 * when the order was placed with the VietQR payment method.
 *
 * Replaces the broken checkout_onepage_controller_success_action
 * observer (that event does not carry a 'response' key, so
 * $response?->setRedirect() was a silent no-op).
 */
class SuccessRedirectPlugin
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @param Success $subject
     * @param callable $proceed
     * @return Redirect|\Magento\Framework\Controller\ResultInterface
     */
    public function aroundExecute(Success $subject, callable $proceed)
    {
        if ($subject->getRequest()->getParam('skip_vietqr')) {
            return $proceed();
        }

        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getId()) {
            $payment = $order->getPayment();
            if ($payment && $payment->getMethod() === 'secomm_vietqr') {
                $additionalInfo = $payment->getAdditionalInformation() ?: [];
                // If customer already confirmed bank transfer, proceed to Thank You page
                if (!empty($additionalInfo['vietqr_customer_confirmed'])) {
                    return $proceed();
                }

                $params = ['order_id' => (int)$order->getId()];
                if ((bool)$order->getCustomerIsGuest()) {
                    $params['key'] = $order->getProtectCode();
                }

                return $this->redirectFactory->create()
                    ->setPath('vietqr/payment/view', $params);
            }
        }

        return $proceed();
    }
}