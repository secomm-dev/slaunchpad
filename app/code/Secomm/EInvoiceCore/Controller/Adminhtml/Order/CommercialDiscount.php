<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceCore\Model\Service\IssueInvoiceService;
use Secomm\EInvoiceLog\Model\IssueLog;
use Throwable;

/**
 * Issue commercial discount invoice (ReferenceType=5).
 */
class CommercialDiscount extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_EInvoiceCore::issue';

    public function __construct(
        Context $context,
        private readonly IssueInvoiceService $issueInvoiceService
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if ($orderId <= 0) {
            $this->messageManager->addErrorMessage(__('Order ID is required.'));
            return $redirect->setPath('sales/order/index');
        }

        $discount = [
            'list_no' => (string) $this->getRequest()->getParam('list_no'),
            'list_date' => (string) $this->getRequest()->getParam('list_date'),
            'invoice_note' => (string) $this->getRequest()->getParam('invoice_note'),
            'amount' => (float) $this->getRequest()->getParam('amount'),
            'vat_amount' => (float) $this->getRequest()->getParam('vat_amount'),
        ];

        try {
            $log = $this->issueInvoiceService->issueCommercialDiscount($orderId, $discount);
            if ($log->getStatus() === IssueLog::STATUS_SUCCESS) {
                $this->messageManager->addSuccessMessage(__('Commercial discount invoice issued successfully.'));
            } else {
                $this->messageManager->addErrorMessage(
                    (string) ($log->getErrorMessage() ?: __('Commercial discount invoice issuance failed.'))
                );
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (Throwable) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while issuing the commercial discount invoice.')
            );
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId, 'active_tab' => 'order_einvoice']);
    }
}
