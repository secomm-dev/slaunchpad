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
use Secomm\CodRisk\Controller\Adminhtml\AbstractAction;
use Secomm\CodRisk\Model\Service\ListManager;

class Deactivate extends AbstractAction
{
    public function __construct(
        Context $context,
        private readonly ListManager $listManager,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('id');
        $active = (bool)$this->getRequest()->getParam('active');
        $redirect = $this->resultRedirectFactory->create();

        try {
            $this->listManager->setActive($id, $active);
            $this->messageManager->addSuccessMessage(
                $active ? __('List record activated.') : __('List record deactivated.')
            );
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        // Return to where the action was triggered from (grid or Order View section).
        return $redirect->setUrl($this->_redirect->getRefererUrl());
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Secomm_CodRisk::manage');
    }
}
