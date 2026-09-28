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
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — grid mass enable/disable. Flag flips go through
 * `setEnabled()` (no code-list re-validation — SPEC/DEC; the codes are untouched).
 * POST-only.
 *
 * TASK-G3K9V2 (TL decision 3): disabling zones that are still referenced by a registered
 * carrier surfaces an EXPLICIT impact warning naming the zones + carriers (the disable
 * itself proceeds — soft state; only DELETE is blocked while referenced).
 */
class MassStatus extends Action implements HttpPostActionInterface
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
        $zoneIds = $this->resolveZoneIds();
        if ($zoneIds === []) {
            $this->messageManager->addErrorMessage(__('Please select at least one zone.'));

            return $redirect;
        }
        // Explicit enum — a missing/garbled status param must NEVER silently disable zones.
        $statusParam = (string) $this->getRequest()->getParam('status');
        if (!in_array($statusParam, ['0', '1'], true)) {
            $this->messageManager->addErrorMessage(__('Invalid mass action status requested.'));

            return $redirect;
        }
        $enabled = $statusParam === '1';
        $referenced = $enabled ? [] : $this->referencedDescriptions($zoneIds);
        $changed = 0;
        foreach ($zoneIds as $zoneId) {
            try {
                $this->zoneRepository->setEnabled((int) $zoneId, $enabled);
                $changed++;
            } catch (NoSuchEntityException $exception) {
                continue;
            }
        }
        $this->messageManager->addSuccessMessage(
            __('A total of %1 zone(s) have been updated.', $changed)
        );
        if ($referenced !== []) {
            $this->messageManager->addWarningMessage(
                __('Disabled zone(s) stop matching until re-enabled — still referenced by carrier coverage: %1. For carriers using "All Except Selected Zones", disabling an excluded zone EXPANDS their service area.', implode(', ', $referenced))
            );
        }

        return $redirect;
    }

    /**
     * "CODE (Carrier (Scope), …)" descriptions for the selected zones that are referenced
     * by a registered carrier in ANY supported scope.
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

    /**
     * Standard grid massaction contract: `selected` ids, or all ids minus `excluded`
     * (all-rows selection). All-rows minus excluded is NOT resolvable without the full id
     * list — when `excluded` is used, the grid sends the complement explicitly in `selected`;
     * an `excluded=-1` value with no `selected` is treated as an empty selection.
     *
     * @return int[]
     */
    private function resolveZoneIds(): array
    {
        $selected = (array) $this->getRequest()->getParam('selected', []);
        $zoneIds = [];
        foreach ($selected as $zoneId) {
            if ((string) $zoneId !== '') {
                $zoneIds[] = (int) $zoneId;
            }
        }

        return $zoneIds;
    }
}
