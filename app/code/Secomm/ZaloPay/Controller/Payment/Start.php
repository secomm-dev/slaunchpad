<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Controller\Payment;

use Secomm\ZaloPay\Gateway\Helper\TransactionReader;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\PaymentFailuresInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Class Get Pay Url
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Start extends Action implements CsrfAwareActionInterface, HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Start constructor.
     *
     * @param Context $context
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param Session $checkoutSession
     * @param PaymentFailuresInterface $paymentFailures
     */
    public function __construct(
        Context $context,
        private readonly CommandPoolInterface $commandPool,
        private readonly LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentDataObjectFactory $paymentDataObjectFactory,
        private readonly Session $checkoutSession,
        private readonly PaymentFailuresInterface $paymentFailures
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        try {
            $orderId = $this->checkoutSession->getLastOrderId();
            if ($orderId) {
                /** @var Order $order */
                $order = $this->orderRepository->get($orderId);
                $payment = $order->getPayment();
                ContextHelper::assertOrderPayment($payment);
                $paymentDataObject = $this->paymentDataObjectFactory->create($payment);
                $commandResult = $this->commandPool->get('get_pay_url')->execute(
                    [
                        'payment' => $paymentDataObject,
                        'amount' => $order->getTotalDue(),
                    ]
                );

                $payUrl = TransactionReader::readPayUrl($commandResult->get());
                if ($payUrl) {
                    $this->getResponse()->setRedirect($payUrl);
                }
            }
        } catch (Exception $e) {
            try {
                $this->paymentFailures->handle((int)$this->checkoutSession->getLastQuoteId(), $e->getMessage());
            } catch (\Exception $exception) {
                //Handle Case No such entity with cartId = 0
                $this->logger->critical($exception->getMessage());
            }
            $this->logger->critical($e);

            $this->messageManager->addErrorMessage(__($e->getMessage()));
            return $this->_redirect('checkout/cart/index');
        }
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return boolean|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
