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
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRepositoryInterface;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\CarrierCoverage\ZoneReferenceGuard;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\ShippingCore\Model\ZoneFactory;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — create/update through the repository (validation +
 * cache flush). Invalid canonical data is REJECTED with the validator message — never
 * silently dropped (SPEC §4). POST-only.
 *
 * TASK-G3K9V2 (TL conditional approval): the admin form no longer carries an Excluded Wards
 * field, so an edit WITHOUT the `exclude_ward_codes` key PRESERVES the persisted list — a
 * UI save can never silently wipe it. A POST that DOES carry the key (API-style) still sets
 * it explicitly, including an intentional clear.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_ShippingCore::zones_manage';

    private CanonicalZoneRepositoryInterface $zoneRepository;

    private DataPersistorInterface $dataPersistor;

    private ZoneFactory $zoneFactory;

    private ZoneResource $zoneResource;

    private ZoneReferenceGuard $referenceGuard;

    public function __construct(
        Context $context,
        CanonicalZoneRepositoryInterface $zoneRepository,
        DataPersistorInterface $dataPersistor,
        ZoneFactory $zoneFactory,
        ZoneResource $zoneResource,
        ZoneReferenceGuard $referenceGuard
    ) {
        parent::__construct($context);
        $this->zoneRepository = $zoneRepository;
        $this->dataPersistor = $dataPersistor;
        $this->zoneFactory = $zoneFactory;
        $this->zoneResource = $zoneResource;
        $this->referenceGuard = $referenceGuard;
    }

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $redirect = $this->resultRedirectFactory->create();
        $zoneId = (int) $request->getParam('zone_id');
        if (!$request->isPost()) {
            $redirect->setPath('*/*/index');

            return $redirect;
        }

        $data = (array) $request->getPostValue();
        $excludeCodes = array_key_exists('exclude_ward_codes', $data)
            ? $this->stringList($data['exclude_ward_codes'])
            : $this->persistedExcludeCodes($zoneId);
        try {
            $zone = new CanonicalZone(
                (string) ($data['code'] ?? ''),
                (string) ($data['label'] ?? ''),
                (bool) ($data['enabled'] ?? false),
                $this->stringList($data['include_province_codes'] ?? []),
                $this->stringList($data['include_ward_codes'] ?? []),
                $excludeCodes
            );
            $this->zoneRepository->save($zone, $zoneId ?: null);
            $this->messageManager->addSuccessMessage(__('The shipping zone has been saved.'));
            $this->warnOnDisabledReferencedZone($zone, (bool) ($data['enabled'] ?? false));
            $redirect->setPath('*/*/index');
        } catch (NoSuchEntityException $exception) {
            // MUST precede the LocalizedException catch — NoSuchEntity extends it.
            $this->messageManager->addErrorMessage(__('This zone no longer exists.'));
            $redirect->setPath('*/*/index');
        } catch (LocalizedException $validationException) {
            $this->messageManager->addErrorMessage($validationException->getMessage());
            $this->persistInput($data, $zoneId);
            $redirect->setPath('*/*/edit', $zoneId ? ['zone_id' => $zoneId] : []);
        }

        return $redirect;
    }

    /**
     * @param mixed $value
     * @return string[] scalar list (multiselect posts arrays; code fields post strings)
     */
    private function stringList($value): array
    {
        if (is_string($value)) {
            $value = $value === '' ? [] : [$value];
        }
        if (!is_array($value)) {
            return [];
        }

        return array_map('strval', $value);
    }

    /**
     * The excluded-ward list currently persisted for the zone (empty for a new zone or an
     * id that no longer resolves — the latter surfaces as NoSuchEntity from the repository).
     *
     * @return string[]
     */
    private function persistedExcludeCodes(int $zoneId): array
    {
        if ($zoneId === 0) {
            return [];
        }
        $zone = $this->zoneFactory->create();
        $this->zoneResource->load($zone, $zoneId);

        return $zone->getId() ? $zone->getExcludeWardCodes() : [];
    }

    /**
     * Explicit impact warning (TL decision 3 + integration review §11): saving a zone as
     * DISABLED while a registered carrier still references it changes carrier service
     * areas — SELECTED_ZONES carriers shrink; "All Except Selected Zones" carriers EXPAND
     * (a disabled zone can no longer exclude destinations). The disable still happens
     * (soft state, unlike delete which is blocked).
     */
    private function warnOnDisabledReferencedZone(CanonicalZone $zone, bool $enabled): void
    {
        if ($enabled) {
            return;
        }
        $references = $this->referenceGuard->describeReferences($zone->getCode());
        if ($references !== '') {
            $this->messageManager->addWarningMessage(
                __('Zone "%1" is saved as disabled while referenced by %2 — referenced carriers cannot match it until re-enabled; for carriers using "All Except Selected Zones" this EXPANDS their service area.', $zone->getCode(), $references)
            );
        }
    }

    /**
     * Keep the merchant's input on a validation failure so nothing typed is lost.
     */
    private function persistInput(array $data, int $zoneId): void
    {
        $data['zone_id'] = $zoneId ?: null;
        $this->dataPersistor->set('secomm_shippingcore_zone_form', $data);
    }
}
