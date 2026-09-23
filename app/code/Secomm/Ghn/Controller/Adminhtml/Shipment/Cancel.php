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
use Secomm\Ghn\Model\Shipment\GhnCancelService;
use Secomm\Ghn\Model\Tracking\ShipmentReconciler;

/**
 * TASK-PWHG0V (GHN-E3-B) — Admin "Cancel GHN Shipment" (POST-only, ACL + form key + confirm).
 *
 * Input is the Magento shipment id ONLY — the GHN order_code is resolved internally through
 * `secomm_ghn_shipment` (brief §36: no provider identity accepted from request parameters).
 * The controller validates, delegates to {@see GhnCancelService}, and responds — no business
 * logic here (§7.2): outcome messaging lives in {@see GhnActionOutcomeNotifier}, and Magento
 * order/shipment business state is NEVER mutated. After SUCCESS an immediate E1 reconcile
 * syncs CANCELLED; a reconcile failure is non-fatal — the webhook remains the authoritative
 * lifecycle source.
 */
class Cancel extends Action
{
    /** Provider-accepted free-text reason cap (matches the UI maxlength; UTF-8 safe). */
    private const REASON_MAX_LENGTH = 255;

    public const ADMIN_RESOURCE = 'Secomm_Ghn::cancel_shipment';

    public function __construct(
        Context $context,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly GhnCancelService $cancelService,
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

        $reason = trim((string) $this->getRequest()->getParam('reason'));
        if (mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            // Rejected, NOT truncated — operator input must stay auditable verbatim (§7).
            $this->messageManager->addErrorMessage(
                __('The cancellation reason must not exceed %1 characters.', self::REASON_MAX_LENGTH)
            );

            return $resultRedirect;
        }

        try {
            $shipment = $this->shipmentRepository->get($shipmentId);
        } catch (NoSuchEntityException | InputException) {
            $this->messageManager->addErrorMessage(__('Shipment %1 not found.', $shipmentId));

            return $this->resultRedirectFactory->create()->setPath('sales_shipment/index');
        }

        $outcome = $this->cancelService->cancel(
            $shipment,
            (string) $this->getRequest()->getParam('reason_code'),
            $reason
        );

        $this->notifier->notify($outcome);

        if ($outcome->isSuccessful()) {
            // Immediate normalized-state sync through the E1 pipeline (non-fatal).
            $this->reconciler->reconcileByOrderCode($outcome->getProviderOrderCode());
        }

        return $resultRedirect;
    }
}
