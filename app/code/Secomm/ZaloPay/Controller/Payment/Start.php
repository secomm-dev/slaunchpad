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
use Secomm\ZaloPay\Model\PaymentAttemptManagement;
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
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\PaymentFailuresInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Class Get Pay Url
 *
 * Payment-first mode (payment/zalopay/payment_first = 1): creates the ZaloPay
 * transaction from the ACTIVE QUOTE via PaymentAttemptManagement — no Magento
 * order exists at this point. When the session quote is not initiable (e.g.
 * a checkout type like Mageplaza OSC that placed the order first through its
 * own Place Order button), falls back to the historical order-first path so
 * both checkout surfaces keep working.
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
     * @param PaymentAttemptManagement $attemptManagement
     * @param ConfigInterface $config
     */
    public function __construct(
        Context                             $context,
        private readonly CommandPoolInterface        $commandPool,
        private readonly LoggerInterface            $logger,
        private readonly OrderRepositoryInterface   $orderRepository,
        private readonly PaymentDataObjectFactory   $paymentDataObjectFactory,
        private readonly Session                   $checkoutSession,
        private readonly PaymentFailuresInterface  $paymentFailures,
        private readonly PaymentAttemptManagement  $attemptManagement,
        private readonly ConfigInterface           $config
    ) {
        parent::__construct($context);
    }

    /**
     * Start the ZaloPay payment (payment-first or legacy path).
     *
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        if ($this->config->getValue('payment_first')) {
            return $this->executePaymentFirst();
        }

        return $this->executeLegacy();
    }

    /**
     * Payment-first: active quote -> attempt -> ZaloPay transaction -> redirect.
     *
     * @return ResponseInterface|ResultInterface|void
     */
    private function executePaymentFirst()
    {
        $quote = $this->checkoutSession->getQuote();
        try {
            if (!$this->attemptManagement->isInitiable($quote)) {
                // Not payable from the quote (empty/inactive/other method):
                // fall back to the order-first path — e.g. Mageplaza OSC's own
                // Place Order button already created the order.
                return $this->executeLegacy();
            }

            $attempt = $this->attemptManagement->initiate($quote);
            if ($attempt->getPayUrl()) {
                $this->getResponse()->setRedirect($attempt->getPayUrl());

                return;
            }
            throw new LocalizedException(__('ZaloPay payment URL is unavailable.'));
        } catch (Exception $e) {
            return $this->handleFailure((int)$quote->getId(), $e);
        }
    }

    /**
     * Historical order-first flow (unchanged behavior).
     *
     * @return ResponseInterface|ResultInterface|void
     */
    private function executeLegacy()
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
            return $this->handleFailure((int)$this->checkoutSession->getLastQuoteId(), $e);
        }
    }

    /**
     * Shared failure handling: payment failures manager, log, cart redirect.
     *
     * @param int $quoteId
     * @param Exception $e
     * @return ResultInterface
     */
    private function handleFailure(int $quoteId, Exception $e)
    {
        try {
            $this->paymentFailures->handle($quoteId, $e->getMessage());
        } catch (\Exception $exception) {
            //Handle Case No such entity with cartId = 0
            $this->logger->critical($exception->getMessage());
        }
        $this->logger->critical($e);

        $this->messageManager->addErrorMessage(__($e->getMessage()));

        return $this->_redirect('checkout/cart/index');
    }

    /**
     * Create exception in case CSRF validation failed.
     *
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     *
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     * @return boolean|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
