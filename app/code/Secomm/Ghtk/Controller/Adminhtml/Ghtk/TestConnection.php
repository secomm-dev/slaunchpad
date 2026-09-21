<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Controller\Adminhtml\Ghtk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Secomm\Ghtk\Model\Pickup\TestConnectionResult;
use Secomm\Ghtk\Model\Pickup\TestConnectionService;

/**
 * Connectivity/health check (AC-005; TASK-3HPB76) — GET
 * `/services/shipment/list_pick_add` through {@see TestConnectionService}:
 * verifies API auth/connectivity and exact-validates the configured
 * `pick_address_id` when one is configured. Admin-only (ACL below + backend
 * form key); NEVER part of the checkout/rate/create paths.
 */
class TestConnection extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_Ghtk::config';

    public function __construct(
        Context $context,
        private readonly TestConnectionService $testConnectionService
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setRefererUrl();

        $storeIdParam = $this->getRequest()->getParam('store', 0);
        $result = $this->testConnectionService->test($storeIdParam !== null && $storeIdParam !== '' ? (int) $storeIdParam : null);

        $message = __($result->getMessage());
        match ($result->getStatus()) {
            TestConnectionResult::CONNECTED => $this->messageManager->addSuccessMessage($message),
            TestConnectionResult::PICKUP_ID_INVALID => $this->messageManager->addWarningMessage($message),
            TestConnectionResult::AUTH_FAILED => $this->messageManager->addErrorMessage($message),
            default => $this->messageManager->addErrorMessage($message),
        };

        return $resultRedirect;
    }
}
