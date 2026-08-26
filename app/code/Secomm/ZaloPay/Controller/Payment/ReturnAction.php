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
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
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
class ReturnAction extends AppAction
{
    /**
     * ReturnAction constructor.
     *
     * @param Context $context
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
        Context                                $context,
        private readonly Session               $checkoutSession,
        private readonly MethodInterface       $method,
        private readonly PaymentDataObjectFactory $paymentDataObjectFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CommandPoolInterface  $commandPool,
        protected \Secomm\ZaloPay\Helper\Data $data,
        private readonly LoggerInterface       $logger,
        private readonly ReturnProcessor       $returnProcessor
    ) {
        parent::__construct($context);
    }

    /**
     * Dispatch the ZaloPay browser return redirect.
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();

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
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    private function executePaymentFirst(string $appTransId, array $params)
    {
        try {
            $path = $this->returnProcessor->process($params);
            $this->_redirect($path);

            return;
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->_redirect('checkout/cart/index');

            return;
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
            $this->_redirect('checkout/cart/index');

            return;
        }
    }

    /**
     * Historical order-first flow (unchanged behavior).
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    private function executeLegacy()
    {
        try {
            $orderId = $this->checkoutSession->getLastOrderId();
            if ($orderId) {
                $response = $this->getRequest()->getParams();
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
                    $this->_redirect('checkout/onepage/success');
                    return;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'ZaloPay return action failed: ' . $e->getMessage(),
                [
                    'exception' => get_class($e),
                    'params' => $this->getRequest()->getParams(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            $this->messageManager->addErrorMessage(__('Transaction has been declined. Please try again later.'));
            $this->_redirect('checkout/onepage/failure');
            return;
        }

        $this->logger->warning('ZaloPay return: order not processable', [
            'last_order_id' => $this->checkoutSession->getLastOrderId(),
            'params' => $this->getRequest()->getParams(),
        ]);
        $this->_redirect('checkout/onepage/failure');
    }
}
