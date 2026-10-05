<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Controller\Adminhtml\IssueLog;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Admin read-only detail page for a single issue log row.
 */
class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_EInvoiceLog::issue_log';

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
     * Render issue log detail form.
     *
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Secomm_EInvoiceCore::issue_logs');
        $entityId = (int) $this->getRequest()->getParam('entity_id');
        $resultPage->getConfig()->getTitle()->prepend(__('EInvoice Log #%1', $entityId));

        return $resultPage;
    }
}
