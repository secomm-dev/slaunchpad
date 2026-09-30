<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Ui\DataProvider;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetType;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;
use Secomm\ShippingCore\Model\ResourceModel\Zone\CollectionFactory;

/**
 * FEAT-QA23PZ / TASK-WY6WP5 — Shipping Coverage form data for the (type, code) target
 * addressed by the `target_code` request param (create mode when absent: the form's XML
 * defaults apply and `is_create` stays 1). The form decides Configure vs Edit from
 * `hasExplicitConfig()` — same form for both (directive §7).
 *
 * The UI form framework (`Magento\Ui\Component\Form::getDataSourceData()`) ALWAYS calls
 * `addFilter()` on a form data provider before `getData()` — AbstractDataProvider routes
 * that into the collection. A coverage target is NOT a DB row, so the collection is only
 * there to absorb that call (zone grid collection, never loaded); the record itself comes
 * from `getData()` below. (Integration defect 2026-09-22: null collection →
 * `addFieldToFilter() on null` when rendering the edit page.)
 */
class CoverageFormDataProvider extends AbstractDataProvider
{
    private CarrierCoverageConfigAdapter $configAdapter;

    private CoverageTargetRegistry $targetRegistry;

    private RequestInterface $request;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CarrierCoverageConfigAdapter $configAdapter,
        CoverageTargetRegistry $targetRegistry,
        RequestInterface $request,
        CollectionFactory $zoneCollectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        // Absorbs the framework's mandatory addFilter() call — never queried for data.
        $this->collection = $zoneCollectionFactory->create();
        $this->configAdapter = $configAdapter;
        $this->targetRegistry = $targetRegistry;
        $this->request = $request;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        $code = trim((string) $this->request->getParam($this->requestFieldName));
        if ($code === '') {
            // Create mode (Add Coverage) — XML defaults + is_create=1 from the form config.
            return [];
        }
        $type = strtoupper(trim((string) $this->request->getParam('target_type', CoverageTargetType::CARRIER)));
        try {
            $identity = CoverageTargetIdentity::create($type, $code);
            $target = $this->targetRegistry->get($identity);
        } catch (NoSuchEntityException) {
            // Edit controller already refuses unregistered targets — defensive no-record.
            return [];
        }

        $configured = $this->configAdapter->hasExplicitConfig($identity);
        $coverage = $this->configAdapter->load($identity);

        // RECORD-KEY CONTRACT (Magento\Ui\Component\Form::getDataSourceData()): the form
        // hydrates `$data[$id]` where $id is the REQUEST VALUE of requestFieldName
        // (target_code) — the record array MUST be keyed by the target code, not by the
        // composite identity key, or the client silently falls back to XML defaults.
        return [
            $identity->code() => [
                'target_type' => $identity->type(),
                'target_code' => $identity->code(),
                'is_create' => $configured ? '0' : '1',
                'applies_to' => $identity->type() === CoverageTargetType::CARRIER ? (string) __('Carrier') : $identity->type(),
                'destination_scope' => $coverage['destination_scope'] !== '' ? $coverage['destination_scope'] : 'ALL',
                'allowed_zone_codes' => $coverage['allowed_zone_codes'],
                'rate_source_mode' => $coverage['rate_source_mode'] !== '' ? $coverage['rate_source_mode'] : 'CARRIER_WITH_FALLBACK',
                'address_resolution_policy' => $coverage['address_resolution_policy'] !== '' ? $coverage['address_resolution_policy'] : 'FALLBACK',
                'target_label' => $target->getLabel(),
            ],
        ];
    }

    /**
     * Meta adjustments for the target_code field: on an EXPLICITLY CONFIGURED target the
     * field becomes read-only and offers exactly the current target (directive §9 —
     * carrier selectable on create, readonly on edit). In every other mode the XML
     * `RegisteredCarrierOptions` option source already provides the create-time list.
     *
     * @return array
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();
        $code = trim((string) $this->request->getParam($this->requestFieldName));
        if ($code === '') {
            return $meta;
        }
        try {
            $identity = CoverageTargetIdentity::create(
                strtoupper(trim((string) $this->request->getParam('target_type', CoverageTargetType::CARRIER))),
                $code
            );
            $target = $this->targetRegistry->get($identity);
        } catch (NoSuchEntityException) {
            return $meta;
        }
        if (!$this->configAdapter->hasExplicitConfig($identity)) {
            return $meta;
        }

        // In this Magento version the framework passes NO meta into form data providers
        // (parent::getMeta() is empty at render time), so the edit-mode adjustments are
        // built unconditionally — mergeMetadata array-merges them into the field config.
        $meta['coverage']['children']['target_code']['arguments']['data']['config'] = [
            'disabled' => true,
            'options' => [['value' => $identity->code(), 'label' => $target->getLabel()]],
        ];

        return $meta;
    }
}
