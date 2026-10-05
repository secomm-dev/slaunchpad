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
 * Issue replacement invoice (ReferenceType=1).
 */
class Replace extends Action implements HttpPostActionInterface
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

        try {
            $log = $this->issueInvoiceService->issueReplace($orderId);
            if ($log->getStatus() === IssueLog::STATUS_SUCCESS) {
                $this->messageManager->addSuccessMessage(__('Replacement invoice issued successfully.'));
            } else {
                $this->messageManager->addErrorMessage(
                    (string) ($log->getErrorMessage() ?: __('Replacement invoice issuance failed.'))
                );
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (Throwable) {
            $this->messageManager->addErrorMessage(__('An error occurred while issuing the replacement invoice.'));
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId, 'active_tab' => 'order_einvoice']);
    }
}
