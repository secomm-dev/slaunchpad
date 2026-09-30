<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Controller\Adminhtml\Lists;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as BackendAuthSession;
use Magento\Framework\Controller\ResultInterface;
use Secomm\CodRisk\Controller\Adminhtml\AbstractAction;
use Secomm\CodRisk\Model\Service\ListManager;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Save extends AbstractAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly ListManager $listManager,
        private readonly BackendAuthSession $backendAuthSession,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $request = $this->getRequest();
        $redirect = $this->resultRedirectFactory->create();

        if (!$request->isPost()) {
            return $redirect->setPath('*/*/index');
        }

        $user = $this->backendAuthSession->getUser();

        try {
            $record = $this->listManager->saveRecord([
                'list_type' => $request->getParam('list_type'),
                'raw_phone' => $request->getParam('raw_phone'),
                'website_id' => (int)$request->getParam('website_id', 0),
                'reason' => $request->getParam('reason'),
                'note' => $request->getParam('note'),
                'source' => 'ADMIN',
                'effective_from' => $request->getParam('effective_from'),
                'effective_to' => $request->getParam('effective_to'),
                'is_active' => $request->getParam('is_active'),
                'created_by' => $user !== null ? $user->getUsername() : '',
            ], $request->getParam('list_id') !== null ? (int)$request->getParam('list_id') : null);

            $this->messageManager->addSuccessMessage(
                __('List record saved for %1.', $record->getData('normalized_phone'))
            );
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('*/*/edit', ['id' => $request->getParam('list_id')]);
        }

        return $redirect->setPath('*/*/index');
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Secomm_CodRisk::manage');
    }
}
