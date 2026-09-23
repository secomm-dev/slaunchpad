<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Controller\Adminhtml\Inspector;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Secomm\CodRisk\Controller\Adminhtml\AbstractAction;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends AbstractAction implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Secomm_CodRisk::inspector');
        $resultPage->getConfig()->getTitle()->prepend(__('Phone Inspector'));

        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Secomm_CodRisk::manage');
    }
}
