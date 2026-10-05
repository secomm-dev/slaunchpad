<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Controller\Adminhtml\Template;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Admin grid page listing MeInvoice invoice templates.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_EInvoiceMisa::template';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Render template listing grid.
     *
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Secomm_EInvoiceMisa::template');
        $resultPage->getConfig()->getTitle()->prepend(__('MISA Invoice Templates'));

        return $resultPage;
    }
}
