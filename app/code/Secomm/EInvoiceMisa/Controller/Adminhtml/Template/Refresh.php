<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Controller\Adminhtml\Template;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplateListProvider;
use Throwable;

/**
 * Admin controller to invalidate the cached MeInvoice template list.
 */
class Refresh extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_EInvoiceMisa::template';

    /**
     * @param Context $context
     * @param InvoiceTemplateListProvider $listProvider
     */
    public function __construct(
        Context $context,
        private readonly InvoiceTemplateListProvider $listProvider
    ) {
        parent::__construct($context);
    }

    /**
     * Refresh template cache for the selected store scope.
     */
    public function execute(): Redirect
    {
        $storeParam = $this->getRequest()->getParam('store');
        $storeId = $storeParam !== null && $storeParam !== '' ? (int) $storeParam : null;

        try {
            $this->listProvider->invalidate($storeId);
            $this->listProvider->getList($storeId);
            $this->messageManager->addSuccessMessage(__('Invoice templates refreshed from MeInvoice.'));
        } catch (Throwable $throwable) {
            $this->messageManager->addErrorMessage(__('Could not refresh templates: %1', $throwable->getMessage()));
        }

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $redirect->setPath('secomm_einvoice_misa/template/index');
    }
}
