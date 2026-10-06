<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceCore\Model\InvoiceDocumentServicePool;
use Secomm\EInvoiceCore\Model\Service\IssueInvoiceService;

/**
 * Open published invoice view link from MeInvoice publishview.
 */
class ViewPublished extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_EInvoiceCore::issue';

    public function __construct(
        Context $context,
        private readonly IssueInvoiceService $issueInvoiceService,
        private readonly InvoiceDocumentServicePool $documentServicePool
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
            $log = $this->issueInvoiceService->getIssuedLogForOrder($orderId);
            if ($log === null || (string) $log->getData('transaction_id') === '') {
                throw new LocalizedException(__('No published invoice found for this order.'));
            }
            $storeId = (int) $log->getData('store_id');
            $response = $this->documentServicePool->get($storeId)
                ->getPublishedView((string) $log->getData('transaction_id'), $storeId);
            $url = (string) ($response['data'] ?? $response['link'] ?? '');
            if ($url === '') {
                throw new LocalizedException(__('MeInvoice did not return a published view link.'));
            }
            return $redirect->setUrl($url);
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
