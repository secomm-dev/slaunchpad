<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Controller\Adminhtml\Lists;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Secomm\CodRisk\Controller\Adminhtml\AbstractAction;
use Secomm\CodRisk\Model\CodRiskListFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Edit extends AbstractAction implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly CodRiskListFactory $listFactory,
        private readonly Registry $registry,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('id');
        $record = $this->listFactory->create();
        if ($id > 0) {
            $record->load($id);
            if ($record->getId() === null) {
                $this->messageManager->addErrorMessage(__('List record does not exist.'));

                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }

        $this->registry->register('secomm_codrisk_list', $record);

        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Secomm_CodRisk::lists');
        $resultPage->getConfig()->getTitle()->prepend(
            $id > 0 ? __('Edit COD Risk List Record') : __('Add Phone to COD Risk List')
        );

        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Secomm_CodRisk::manage');
    }
}
