<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceCore\Model\Service\IssueInvoiceService;
use Throwable;

/**
 * Admin controller to refresh an order's MeInvoice status.
 */
class RefreshStatus extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_EInvoiceCore::issue';

    /**
     * @param Context $context
     * @param IssueInvoiceService $issueInvoiceService
     */
    public function __construct(
        Context $context,
        private readonly IssueInvoiceService $issueInvoiceService
    ) {
        parent::__construct($context);
    }

    /**
     * Refresh and persist invoice status.
     */
    public function execute(): Redirect
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if ($orderId <= 0) {
            $this->messageManager->addErrorMessage(__('Order ID is required.'));
            return $redirect->setPath('sales/order/index');
        }

        try {
            $this->issueInvoiceService->refreshStatus($orderId);
            $this->messageManager->addSuccessMessage(__('Invoice status refreshed from MeInvoice.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (Throwable $throwable) {
            $this->messageManager->addErrorMessage(__('Could not refresh the invoice status.'));
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId, 'active_tab' => 'order_einvoice']);
    }
}
