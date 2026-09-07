<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Secomm\VietQr\Block\PaymentInfo;
use Secomm\VietQr\Model\Config;
use Secomm\VietQr\Model\RateLimiter;

/**
 * Handles VietQR payment confirmation form submission from the payment view page
 * (TASK-N35E28 / SPEC-FEAT-ZKD4VA §3 AC-013, AC-014).
 *
 * Validates the form key, rate-limits requests, verifies order ownership,
 * marks the order as "customer confirmed", and transitions the status.
 */
class Submit implements HttpPostActionInterface
{
    private CheckoutSession $checkoutSession;

    public function __construct(
        private readonly HttpRequest $request,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly Validator $formKeyValidator,
        private readonly PaymentInfo $paymentInfoBlock,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger,
        private readonly RateLimiter $rateLimiter,
        private readonly MessageManagerInterface $messageManager,
        private readonly Config $config,
        ?CheckoutSession $checkoutSession = null
    ) {
        $this->checkoutSession = $checkoutSession
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(CheckoutSession::class);
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        $orderId = (int)$this->request->getParam('order_id', 0);
        if ($orderId <= 0) {
            return $this->redirectBack();
        }

        if (!$this->rateLimiter->isAllowed()) {
            $this->messageManager->addErrorMessage(
                __('Too many requests. Please try again later.')
            );

            return $this->redirectBack();
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            return $this->redirectBack();
        }

        try {
            $this->paymentInfoBlock->init($orderId, $this->request->getParam('key'));
        } catch (NoSuchEntityException) {
            return $this->redirectBack();
        }

        $order = $this->paymentInfoBlock->getOrder();
        $payment = $order->getPayment();

        // Guard duplicate submit
        $additionalInfo = $payment->getAdditionalInformation() ?: [];
        if (!empty($additionalInfo['vietqr_customer_confirmed'])) {
            return $this->redirectToSuccessPage($order);
        }
        if ($order->getStatus() !== $this->config->getNewOrderStatus()) {
            return $this->redirectToSuccessPage($order);
        }

        try {
            $now = (new \DateTime())->format('Y-m-d H:i:s');
            $transactionRef = trim((string)$this->request->getParam('transaction_ref', ''));
            $customerNotes = trim((string)$this->request->getParam('customer_notes', ''));

            $newInfo = array_merge($additionalInfo, [
                'vietqr_customer_confirmed' => true,
                'vietqr_customer_confirmed_at' => $now,
                'vietqr_transaction_ref' => $transactionRef,
                'vietqr_customer_notes' => $customerNotes,
            ]);
            $payment->setAdditionalInformation($newInfo);
            $payment->save();

            $order->setStatus($this->config->getAwaitingConfirmStatus());

            $defaultComment = $this->config->getCustomerConfirmComment();
            $commentLines = [__($defaultComment)];
            if ($transactionRef !== '') {
                $commentLines[] = __('Transaction Reference: %1', $transactionRef);
            }
            if ($customerNotes !== '') {
                $commentLines[] = __('Customer Notes: %1', $customerNotes);
            }

            $order->addCommentToStatusHistory(
                implode("\n", $commentLines),
                false,
                false
            );
            $this->orderRepository->save($order);

            $this->messageManager->addSuccessMessage(
                __('Thank you! We have received your payment confirmation for order #%1.', $order->getIncrementId())
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'VietQR submit failed for order ' . $order->getIncrementId(),
                ['error' => $e->getMessage()]
            );
        }

        return $this->redirectToSuccessPage($order);
    }

    /**
     * @return Redirect
     */
    private function redirectBack(): Redirect
    {
        return $this->resultRedirectFactory->create()->setPath('/');
    }

    /**
     * Redirect to the appropriate success page based on the "from" parameter.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface|null $order
     * @return Redirect
     */
    private function redirectToSuccessPage($order): Redirect
    {
        $from = $this->request->getParam('from');
        if ($from === 'order' && $order && $order->getId()) {
            $params = ['order_id' => $order->getId()];
            if ((bool)$order->getCustomerIsGuest()) {
                $params['key'] = $order->getProtectCode();
            }
            return $this->resultRedirectFactory->create()->setPath('sales/order/view', $params);
        }

        if ($order && $order->getId()) {
            $this->checkoutSession->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId())
                ->setLastOrderStatus($order->getStatus());
            if ($order->getQuoteId()) {
                $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId())
                    ->setLastQuoteId($order->getQuoteId());
            }
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }
}
