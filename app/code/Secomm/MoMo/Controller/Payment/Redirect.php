<?php
/**
 * MoMo redirect (start) controller — payment-first (MOMO-01).
 *
 * Builds the MoMo transaction from the ACTIVE QUOTE and redirects the
 * browser to the MoMo wallet payUrl. NO Magento order exists at any point
 * (AC1): the attempt row (order_ref/request_id under the quote lock, frozen
 * amount + contract fingerprint) is persisted BEFORE the provider call, the
 * provider transaction is created from the attempt, and the order only ever
 * comes from IpnProcessor/ReturnProcessor -> OrderFinalizer after
 * authoritative verification.
 *
 * A quote that is not initiable (inactive, empty, another method, totals
 * zero, non-VND) fails safely: the cart is kept and the customer is
 * redirected back with an error. There is no order-first fallback.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2000-2026 Secomm. All rights reserved (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect as ResultRedirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\PaymentFailuresInterface;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Model\PaymentAttemptManagement;

/**
 * MoMo redirect (start) controller — quote-first, composition style.
 */
class Redirect implements CsrfAwareActionInterface, HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Redirect controller constructor.
     *
     * @param Session $checkoutSession
     * @param LoggerInterface $logger
     * @param MessageManager $messageManager
     * @param RedirectFactory $redirectFactory
     * @param PaymentFailuresInterface $paymentFailures
     * @param PaymentAttemptManagement $attemptManagement
     */
    public function __construct(
        private readonly Session $checkoutSession,
        private readonly LoggerInterface $logger,
        private readonly ManagerInterface $messageManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly PaymentFailuresInterface $paymentFailures,
        private readonly PaymentAttemptManagement $attemptManagement
    ) {
    }

    /**
     * Start the MoMo payment from the active quote (payment-first).
     *
     * @return ResultRedirect
     */
    public function execute(): ResultRedirect
    {
        $quote = $this->checkoutSession->getQuote();
        try {
            if (!$this->attemptManagement->isInitiable($quote)) {
                throw new LocalizedException(
                    __('The cart is no longer payable with MoMo. Please refresh your cart and try again.')
                );
            }

            $attempt = $this->attemptManagement->initiate($quote);
            $payUrl = (string)$attempt->getPayUrl();
            if ($payUrl !== '') {
                return $this->redirectFactory->create()->setUrl($payUrl);
            }
            throw new LocalizedException(__('MoMo payment URL is unavailable.'));
        } catch (Exception $e) {
            return $this->handleFailure((int)$quote->getId(), $e);
        }
    }

    /**
     * Failure handling: payment failures manager, log, keep the cart.
     *
     * @param int $quoteId
     * @param Exception $e
     * @return ResultRedirect
     */
    private function handleFailure(int $quoteId, Exception $e): ResultRedirect
    {
        try {
            $this->paymentFailures->handle($quoteId, $e->getMessage());
        } catch (\Exception $exception) {
            // Handle "No such entity" for a cart that already went away.
            $this->logger->critical($exception->getMessage());
        }
        $this->logger->critical($e);

        $this->messageManager->addErrorMessage(__($e->getMessage()));

        return $this->redirectFactory->create()->setPath('checkout/cart/index');
    }

    /**
     * Create exception in case CSRF validation failed.
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
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
