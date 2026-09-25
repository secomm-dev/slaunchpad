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
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — grid mass delete. POST-only.
 *
 * TASK-G3K9V2 (TL conditional approval, decision 2): same reference guard as the single
 * delete — if ANY selected zone is referenced by a registered carrier, the WHOLE batch is
 * blocked (no partial delete) with the referenced zones + carriers named.
 */
class MassDelete extends Action implements HttpPostActionInterface
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
        $zoneIds = [];
        foreach ((array) $this->getRequest()->getParam('selected', []) as $zoneId) {
            if ((string) $zoneId !== '') {
                $zoneIds[] = (int) $zoneId;
            }
        }
        if ($zoneIds === []) {
            $this->messageManager->addErrorMessage(__('Please select at least one zone.'));

            return $redirect;
        }
        $referenced = $this->referencedDescriptions($zoneIds);
        if ($referenced !== []) {
            $this->messageManager->addErrorMessage(
                __('No zones were deleted — referenced zone(s) must be removed from carrier coverage first: %1.', implode(', ', $referenced))
            );

            return $redirect;
        }
        $deleted = 0;
        foreach ($zoneIds as $zoneId) {
            try {
                $this->zoneRepository->deleteById($zoneId);
                $deleted++;
            } catch (NoSuchEntityException $exception) {
                continue;
            }
        }
        $this->messageManager->addSuccessMessage(
            __('A total of %1 zone(s) have been deleted.', $deleted)
        );

        return $redirect;
    }

    /**
     * "CODE (Carrier (Scope), …)" descriptions for the selected zones that are referenced
     * by a registered carrier in ANY supported scope (missing zones are skipped — the
     * delete loop reports them).
     *
     * @param int[] $zoneIds
     * @return string[]
     */
    private function referencedDescriptions(array $zoneIds): array
    {
        $descriptions = [];
        foreach ($zoneIds as $zoneId) {
            $zone = $this->zoneFactory->create();
            $this->zoneResource->load($zone, $zoneId);
            if (!$zone->getId()) {
                continue;
            }
            $description = $this->referenceGuard->describeZoneReference($zone->getCode());
            if ($description !== null) {
                $descriptions[] = $description;
            }
        }

        return $descriptions;
    }
}
