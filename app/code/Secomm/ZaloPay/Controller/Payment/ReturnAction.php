<?php
/***********************************************************************
 * *
 *  *
 *  * @copyright Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  * @author    Secomm Teams
 * *
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Service\ReturnProcessor;

/**
 * Return (browser redirect) action.
 *
 * Payment-first: when the redirect references a payment attempt
 * (apptransid), delegate to ReturnProcessor — attempt-first lookup,
 * authoritative v2/query verification and idempotent order placement
 * (IPN may have arrived before the customer returned). No attempt found ->
 * legacy order-first flow, unchanged.
 */
/**
 * Return (browser redirect) action — composition style.
 */
class ReturnAction implements HttpGetActionInterface
{
    /**
     * ReturnAction constructor.
     *
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $redirectFactory
     * @param Session $checkoutSession
     * @param MethodInterface $method
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CommandPoolInterface $commandPool
     * @param \Secomm\ZaloPay\Helper\Data $data
     * @param LoggerInterface $logger
     * @param ReturnProcessor $returnProcessor
     */
    public function __construct(
        private readonly RequestInterface      $request,
        private readonly ManagerInterface      $messageManager,
        private readonly RedirectFactory       $redirectFactory,
        private readonly Session               $checkoutSession,
        private readonly MethodInterface       $method,
        private readonly PaymentDataObjectFactory $paymentDataObjectFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CommandPoolInterface  $commandPool,
        protected \Secomm\ZaloPay\Helper\Data $data,
        private readonly LoggerInterface       $logger,
        private readonly ReturnProcessor       $returnProcessor
    ) {
    }

    /**
     * Dispatch the ZaloPay browser return redirect.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $params = $this->request->getParams();

        // Payment-first: the redirect carries our app transaction reference.
        $appTransId = trim((string)($params['apptransid'] ?? $params['app_trans_id'] ?? ''));
        if ($appTransId !== '') {
            return $this->executePaymentFirst($appTransId, $params);
        }

        return $this->executeLegacy();
    }

    /**
     * Handle the payment-first return (apptransid present).
     *
     * @param string $appTransId
     * @param array $params
     * @return Redirect
     */
    private function executePaymentFirst(string $appTransId, array $params): Redirect
    {
        try {
            $path = $this->returnProcessor->process($params);
            return $this->redirectTo($path);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->redirectTo('checkout/cart/index');
        } catch (\Exception $e) {
            $this->logger->error(
                'ZaloPay return action failed: ' . $e->getMessage(),
                [
                    'exception' => get_class($e),
                    'app_trans_id' => $appTransId,
                    'params' => $params,
                    'trace' => $e->getTraceAsString(),
                ]
            );
            $this->messageManager->addErrorMessage(__('Transaction has been declined. Please try again later.'));
            return $this->redirectTo('checkout/cart/index');
        }
    }

    /**
     * Build a redirect result for a Magento path.
     *
     * @param string $path
     * @return Redirect
     */
    private function redirectTo(string $path): Redirect
    {
        return $this->redirectFactory->create()->setPath($path);
    }

    /**
     * Historical order-first flow (unchanged behavior).
     *
     * @return Redirect
     */
    private function executeLegacy(): Redirect
    {
        try {
            $orderId = $this->checkoutSession->getLastOrderId();
            if ($orderId) {
                $response = $this->request->getParams();
                /** @var Order $order */
                $order = $this->orderRepository->get($orderId);
                $payment = $order->getPayment();
                ContextHelper::assertOrderPayment($payment);
                if ($payment->getMethod() === $this->method->getCode()) {
                    if ($order->getState() == Order::STATE_PENDING_PAYMENT) {
                        $paymentDataObject = $this->paymentDataObjectFactory->create($payment);
                        $this->commandPool->get('complete')->execute(
                            [
                                'payment' => $paymentDataObject,
                                'response' => $response,
                                'amount' => $order->getTotalDue()
                            ]
                        );
                    }
                    return $this->redirectTo('checkout/onepage/success');
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'ZaloPay return action failed: ' . $e->getMessage(),
                [
                    'exception' => get_class($e),
                    'params' => $this->request->getParams(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            $this->messageManager->addErrorMessage(__('Transaction has been declined. Please try again later.'));
            return $this->redirectTo('checkout/onepage/failure');
        }

        $this->logger->warning('ZaloPay return: order not processable', [
            'last_order_id' => $this->checkoutSession->getLastOrderId(),
            'params' => $this->request->getParams(),
        ]);
        return $this->redirectTo('checkout/onepage/failure');
    }
}
