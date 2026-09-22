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
use Secomm\CodRisk\Model\Service\OverrideManager;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Per-order manual override (mockup Flow D, D-04): BLOCK -> ALLOW for THIS order
 * only, mandatory reason, fully audited.
 */
class Override extends AbstractOrderAction implements HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        OrderRepositoryInterface $orderRepository,
        private readonly OverrideManager $overrideManager,
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

        $user = $this->backendAuthSession->getUser();

        try {
            $this->overrideManager->createForOrder(
                (int)$this->getRequest()->getParam('order_id'),
                (string)$this->getRequest()->getParam('reason'),
                (string)$this->getRequest()->getParam('note'),
                $user !== null ? (string)$user->getUsername() : 'system'
            );

            $this->messageManager->addSuccessMessage(
                __('COD allowed for this order. The override is audited; the phone\'s checkout behavior is unchanged.')
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
