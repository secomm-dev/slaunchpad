<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Secomm\Ghn\Model\Admin\GhnActionOutcomeNotifier;
use Secomm\Ghn\Model\Shipment\GhnReturnService;
use Secomm\Ghn\Model\Tracking\ShipmentReconciler;

/**
 * TASK-PWHG0V (GHN-E3-B) — Admin "Request GHN Return" (POST-only, ACL + form key + confirm).
 *
 * Label deliberately says "Request GHN Return": this is the CARRIER return action (provider
 * R2S request via {@see GhnReturnService}) — NOT a Magento customer return/RMA/refund; no
 * sales business state is touched. Input is the shipment id only; provider identity is resolved
 * internally (brief §36). Outcome messaging lives in {@see GhnActionOutcomeNotifier};
 * UNKNOWN_RESULT renders the reconcile-first message with no retry button (brief §20).
 */
class ReturnShipment extends Action
{
    public const ADMIN_RESOURCE = 'Secomm_Ghn::return_shipment';

    public function __construct(
        Context $context,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly GhnReturnService $returnService,
        private readonly ShipmentReconciler $reconciler,
        private readonly GhnActionOutcomeNotifier $notifier
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $shipmentId = (int) $this->getRequest()->getParam('shipment_id');
        $resultRedirect = $this->resultRedirectFactory->create()
            ->setPath('sales_shipment/view', ['shipment_id' => $shipmentId]);

        if (!$this->getRequest()->isPost()) {
            $this->messageManager->addErrorMessage(__('Invalid request method.'));

            return $this->resultRedirectFactory->create()->setPath('dashboard');
        }

        if ($shipmentId <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid shipment id.'));

            return $resultRedirect;
        }

        try {
            $shipment = $this->shipmentRepository->get($shipmentId);
        } catch (NoSuchEntityException | InputException) {
            $this->messageManager->addErrorMessage(__('Shipment %1 not found.', $shipmentId));

            return $this->resultRedirectFactory->create()->setPath('sales_shipment/index');
        }

        $outcome = $this->returnService->requestReturn($shipment);

        $this->notifier->notify($outcome);

        if ($outcome->isSuccessful()) {
            // Immediate normalized-state sync through the E1 pipeline (non-fatal).
            $this->reconciler->reconcileByOrderCode($outcome->getProviderOrderCode());
        }

        return $resultRedirect;
    }
}
