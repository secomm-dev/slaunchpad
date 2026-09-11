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

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\PaymentFailuresInterface;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Model\PaymentAttemptManagement;

/**
 * Start the ZaloPay payment — payment-first, the ONLY production flow.
 *
 * The redirect target is built from the ACTIVE QUOTE via
 * PaymentAttemptManagement: contract snapshot + attempt row + ZaloPay
 * create-order API. NO Magento order exists before the payment is verified
 * server-side (IpnProcessor/ReturnProcessor -> OrderFinalizer).
 *
 * A quote that is not initiable (inactive, empty, another method, totals
 * zero) fails safely: the cart is kept and the customer is redirected back
 * with an error. There is no order-first fallback.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Start implements CsrfAwareActionInterface, HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Start constructor.
     *
     * @param Session $checkoutSession
     * @param LoggerInterface $logger
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $redirectFactory
     * @param PaymentFailuresInterface $paymentFailures
     * @param PaymentAttemptManagement $attemptManagement
     */
    public function __construct(
        private readonly Session                  $checkoutSession,
        private readonly LoggerInterface          $logger,
        private readonly ManagerInterface         $messageManager,
        private readonly RedirectFactory          $redirectFactory,
        private readonly PaymentFailuresInterface $paymentFailures,
        private readonly PaymentAttemptManagement $attemptManagement
    ) {
    }

    /**
     * Start the ZaloPay payment from the active quote (payment-first).
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $quote = $this->checkoutSession->getQuote();
        try {
            if (!$this->attemptManagement->isInitiable($quote)) {
                throw new LocalizedException(
                    __('The cart is no longer payable with ZaloPay. Please refresh your cart and try again.')
                );
            }

            $attempt = $this->attemptManagement->initiate($quote);
            if ($attempt->getPayUrl()) {
                return $this->redirectFactory->create()->setUrl($attempt->getPayUrl());
            }
            throw new LocalizedException(__('ZaloPay payment URL is unavailable.'));
        } catch (Exception $e) {
            return $this->handleFailure((int)$quote->getId(), $e);
        }
    }

    /**
     * Failure handling: payment failures manager, log, keep the cart.
     *
     * @param int $quoteId
     * @param Exception $e
     * @return Redirect
     */
    private function handleFailure(int $quoteId, Exception $e): Redirect
    {
        try {
            $this->paymentFailures->handle($quoteId, $e->getMessage());
        } catch (\Exception $exception) {
            //Handle Case No such entity with cartId = 0
            $this->logger->critical($exception->getMessage());
        }
        $this->logger->critical($e);

        $this->messageManager->addErrorMessage(__($e->getMessage()));

        return $this->redirectFactory->create()->setPath('checkout/cart/index');
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
