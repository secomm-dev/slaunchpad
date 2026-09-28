<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Controller\Adminhtml\Zone;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRepositoryInterface;
use Secomm\ShippingCore\Model\CarrierCoverage\ZoneReferenceGuard;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\ShippingCore\Model\ZoneFactory;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — delete a zone. POST-only.
 *
 * TASK-G3K9V2 (TL conditional approval, decision 2): a zone referenced by a registered
 * carrier's coverage config MUST NOT be deleted — the deletion is BLOCKED with an error
 * naming the carriers; the admin removes the reference in Secomm → Shipping Coverage first.
 * Unreferenced zones delete cleanly (no dangling carrier refs can be produced from admin).
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_ShippingCore::zones_manage';

    private CanonicalZoneRepositoryInterface $zoneRepository;

    private ZoneFactory $zoneFactory;

    private ZoneResource $zoneResource;

    private ZoneReferenceGuard $referenceGuard;

    public function __construct(
        Context $context,
        CanonicalZoneRepositoryInterface $zoneRepository,
        ZoneFactory $zoneFactory,
        ZoneResource $zoneResource,
        ZoneReferenceGuard $referenceGuard
    ) {
        parent::__construct($context);
        $this->zoneRepository = $zoneRepository;
        $this->zoneFactory = $zoneFactory;
        $this->zoneResource = $zoneResource;
        $this->referenceGuard = $referenceGuard;
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('*/*/index');
        if (!$this->getRequest()->isPost()) {
            return $redirect;
        }
        $zoneId = (int) $this->getRequest()->getParam('zone_id');
        $zone = $this->zoneFactory->create();
        $this->zoneResource->load($zone, $zoneId);
        if (!$zone->getId()) {
            $this->messageManager->addErrorMessage(__('This zone no longer exists.'));

            return $redirect;
        }
        $references = $this->referenceGuard->describeReferences($zone->getCode());
        if ($references !== '') {
            $this->messageManager->addErrorMessage(
                __('Zone "%1" cannot be deleted: it is referenced by %2. Remove the reference in Secomm → Shipping Coverage first.', $zone->getCode(), $references)
            );

            return $redirect;
        }
        try {
            $this->zoneRepository->deleteById($zoneId);
            $this->messageManager->addSuccessMessage(__('The shipping zone has been deleted.'));
        } catch (NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage(__('This zone no longer exists.'));
        }

        return $redirect;
    }
}
