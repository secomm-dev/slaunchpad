<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceLog\Model\IssueLog;
use Secomm\EInvoiceCore\Model\Service\IssueInvoiceService;
use Throwable;

/**
 * Admin controller to issue electronic invoice for an order.
 */
class Issue extends Action implements HttpPostActionInterface
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
     * Issue electronic invoice for the given order.
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

        $context = [];
        $templateId = (string) $this->getRequest()->getParam('invoice_template_id', '');
        if ($templateId !== '') {
            $context['template_id'] = $templateId;
        }

        try {
            $log = $this->issueInvoiceService->issueNow($orderId, $context);
            if ($log->getStatus() === IssueLog::STATUS_SUCCESS) {
                $this->messageManager->addSuccessMessage(
                    __('EInvoice issued. External reference: %1', (string) $log->getExternalId())
                );
            } else {
                $this->messageManager->addErrorMessage(
                    (string) ($log->getErrorMessage() ?: __('EInvoice issuance failed.'))
                );
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (Throwable $throwable) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while issuing the EInvoice.')
            );
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId, 'active_tab' => 'order_einvoice']);
    }
}
