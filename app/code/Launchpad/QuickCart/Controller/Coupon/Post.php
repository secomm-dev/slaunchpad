<?php

declare(strict_types=1);

namespace Launchpad\QuickCart\Controller\Coupon;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CouponManagementInterface;
use Throwable;

/**
 * AJAX coupon endpoint for the Hyvä cart drawer.
 *
 * Validates the form key, delegates apply/remove to CouponManagement on the
 * session quote (server-side revalidation through the native sales rule
 * validation and totals collection) and responds with the JSON contract the
 * drawer JS expects: {success: bool, error_message?: string}.
 */
class Post extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly Session $checkoutSession,
        private readonly CouponManagementInterface $couponManagement,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $request = $this->getRequest();

        if (!$this->formKeyValidator->validate($request)) {
            return $this->createResult(false, (string) __('Invalid Form Key. Please refresh the page.'));
        }

        try {
            $quoteId = (int) $this->checkoutSession->getQuote()->getId();

            if (!$quoteId) {
                return $this->createResult(false, (string) __('You have no items in your shopping cart.'));
            }

            $couponCode = trim((string) $request->getParam('coupon_code'));

            if ((bool) $request->getParam('remove')) {
                $this->couponManagement->remove($quoteId);
            } else {
                $this->couponManagement->set($quoteId, $couponCode);
            }
        } catch (LocalizedException $e) {
            return $this->createResult(false, (string) $e->getMessage());
        } catch (Throwable $e) {
            throw $e;
        }

        return $this->createResult(true, '');
    }

    private function createResult(bool $success, string $errorMessage): Json
    {
        $result = $this->resultJsonFactory->create();

        $result->setData($success
            ? ['success' => true]
            : ['success' => false, 'error_message' => $errorMessage]);

        return $result;
    }
}
