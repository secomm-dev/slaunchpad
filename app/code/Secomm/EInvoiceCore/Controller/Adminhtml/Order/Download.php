<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceCore\Api\InvoiceDocumentServiceInterface;
use Secomm\EInvoiceCore\Model\Service\IssueInvoiceService;
use Throwable;

/**
 * Admin controller to download an order's issued invoice file.
 */
class Download extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_EInvoiceCore::issue';

    /**
     * @param Context $context
     * @param IssueInvoiceService $issueInvoiceService
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        private readonly IssueInvoiceService $issueInvoiceService,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Stream the invoice file to the browser.
     *
     * @return \Magento\Framework\App\ResponseInterface|Redirect
     */
    public function execute()
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        $type = (string) $this->getRequest()->getParam('type', InvoiceDocumentServiceInterface::DOWNLOAD_PDF);

        if ($orderId <= 0) {
            $this->messageManager->addErrorMessage(__('Order ID is required.'));
            /** @var Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setPath('sales/order/index');
        }

        try {
            $file = $this->issueInvoiceService->downloadInvoice($orderId, $type);

            return $this->fileFactory->create(
                $file['filename'],
                [
                    'type' => 'string',
                    'value' => $file['contents'],
                    'rm' => false,
                ],
                \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
                $file['mime']
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (Throwable $throwable) {
            $this->messageManager->addErrorMessage(__('Could not download the invoice file.'));
        }

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
