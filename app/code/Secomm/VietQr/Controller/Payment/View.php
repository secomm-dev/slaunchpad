<?php
declare(strict_types=1);

namespace Secomm\VietQr\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Secomm\VietQr\Block\PaymentInfo;
use Secomm\VietQr\Model\RateLimiter;

/**
 * Displays the VietQR payment page for a given order.
 *
 * Validates the order ID, applies rate limiting, verifies ownership,
 * and renders the payment view with the QR code and bank details.
 */
class View implements HttpGetActionInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly PageFactory $resultPageFactory,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly PaymentInfo $paymentInfoBlock,
        private readonly RateLimiter $rateLimiter,
        private readonly MessageManagerInterface $messageManager
    ) {
    }

    /**
     * @return \Magento\Framework\View\Result\Page|Redirect
     */
    public function execute()
    {
        $orderId = (int)$this->request->getParam('order_id', 0);
        $key = $this->request->getParam('key');

        if ($orderId <= 0) {
            return $this->redirectToHome();
        }

        if (!$this->rateLimiter->isAllowed()) {
            $this->messageManager->addErrorMessage(
                __('Too many requests. Please try again later.')
            );

            return $this->redirectToHome();
        }

        try {
            $this->paymentInfoBlock->init($orderId, $key);
        } catch (NoSuchEntityException) {
            return $this->redirectToHome();
        }

        $page = $this->resultPageFactory->create();
        $block = $page->getLayout()->getBlock('vietqr.payment.info');
        if ($block) {
            $block->setData('payment_info', $this->paymentInfoBlock);
        }

        return $page;
    }

    /**
     * @return Redirect
     */
    private function redirectToHome(): Redirect
    {
        return $this->resultRedirectFactory->create()->setPath('/');
    }
}
