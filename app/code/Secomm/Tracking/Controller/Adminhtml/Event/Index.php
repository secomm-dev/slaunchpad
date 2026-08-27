<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Controller\Adminhtml\Event;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * FEAT-31X6N2 / TASK-WY5JRN — read-only outbox grid (pending/sent/failed/skipped).
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'Secomm_Tracking::outbox';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Secomm_Tracking::outbox');
        $resultPage->getConfig()->getTitle()->prepend(__('Commerce Tracking — Event Outbox'));

        return $resultPage;
    }
}
