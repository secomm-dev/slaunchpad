<?php
/***********************************************************************
 * *
 *  *
 *  * @copyright Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  * @author    Secomm Teams
 * *
 */

namespace Secomm\ZaloPay\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Framework\App\Action\Context;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class ReturnAction extends AppAction
{
    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var MethodInterface
     */
    private $method;

    /**
     * @var PaymentDataObjectFactory
     */
    private $paymentDataObjectFactory;

    /**
     * ReturnAction constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $method
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CommandPoolInterface $commandPool
     */
    public function __construct(
        Context                                $context,
        Session                                $checkoutSession,
        MethodInterface                        $method,
        PaymentDataObjectFactory               $paymentDataObjectFactory,
        OrderRepositoryInterface               $orderRepository,
        CommandPoolInterface                   $commandPool,
        protected \Secomm\ZaloPay\Helper\Data $data,
        LoggerInterface                        $logger
    ) {
        parent::__construct($context);
        $this->commandPool = $commandPool;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->method = $method;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->logger = $logger;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
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
        return;
    }
}
