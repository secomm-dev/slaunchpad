<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Controller\Adminhtml\Risk;

use Magento\Backend\Model\Auth\Session as BackendAuthSession;
use Magento\Sales\Api\OrderRepositoryInterface;
use Secomm\CodRisk\Model\Service\ListManager;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Quick add-to-list straight from Order View (mockup Flow A) — phone prefilled
 * from the order, source = ORDER.
 */
class AddList extends AbstractOrderAction implements HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        OrderRepositoryInterface $orderRepository,
        private readonly ListManager $listManager,
        private readonly BackendAuthSession $backendAuthSession,
    ) {
        parent::__construct($context, $orderRepository);
    }

    public function execute()
    {
        $redirect = $this->getOrderRedirect();

        if (!$this->isPost()) {
            return $redirect;
        }

        $order = $this->orderRepository->get((int)$this->getRequest()->getParam('order_id'));
        $user = $this->backendAuthSession->getUser();

        try {
            $record = $this->listManager->saveRecord([
                'list_type' => $this->getRequest()->getParam('list_type'),
                'raw_phone' => (string)$order->getShippingAddress()?->getTelephone(),
                'website_id' => (int)$order->getStore()->getWebsiteId(),
                'reason' => (string)$this->getRequest()->getParam('reason'),
                'note' => (string)$this->getRequest()->getParam('note'),
                'source' => 'ORDER',
                'effective_from' => null,
                'effective_to' => null,
                'created_by' => $user !== null ? $user->getUsername() : '',
            ]);

            $this->messageManager->addSuccessMessage(
                __('List record saved for %1.', $record->getData('normalized_phone'))
            );
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Secomm_CodRisk::manage');
    }
}
