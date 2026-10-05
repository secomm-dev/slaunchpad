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
use Secomm\EInvoiceCore\Model\IssueRequestBuilderPool;
use Secomm\EInvoiceCore\Model\OrderStoreIdProvider;

/**
 * Preview unpublished invoice via MeInvoice unpublishview (TTL ~5 minutes).
 */
class Preview extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_EInvoiceCore::issue';

    public function __construct(
        Context $context,
        private readonly IssueRequestBuilderPool $requestBuilderPool,
        private readonly InvoiceDocumentServicePool $documentServicePool,
        private readonly OrderStoreIdProvider $orderStoreIdProvider
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
            $storeId = $this->orderStoreIdProvider->getStoreIdByOrderId($orderId);
            $request = $this->requestBuilderPool->get($storeId)->build($orderId, []);
            $documentService = $this->documentServicePool->get($storeId);
            if (!method_exists($documentService, 'previewUnpublished')) {
                throw new LocalizedException(__('Preview is not supported for the configured provider.'));
            }
            /** @var \Secomm\EInvoiceMisa\Model\Document\MisaInvoiceDocumentService $documentService */
            $response = $documentService->previewUnpublished($request->getPayload(), $storeId);
            $url = (string) ($response['data'] ?? $response['link'] ?? '');
            if ($url === '') {
                throw new LocalizedException(__('MeInvoice preview did not return a view link.'));
            }
            return $redirect->setUrl($url);
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
