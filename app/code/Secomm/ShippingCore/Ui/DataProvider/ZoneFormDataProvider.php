<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Ui\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Secomm\ShippingCore\Controller\Adminhtml\Zone\Edit;
use Secomm\ShippingCore\Model\ResourceModel\Zone\CollectionFactory;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — zone edit form data: registry-loaded model (Edit
 * controller), with the last rejected submission re-applied on a validation failure so
 * nothing the merchant typed is lost.
 */
class ZoneFormDataProvider extends AbstractDataProvider
{
    private Registry $registry;

    private DataPersistorInterface $dataPersistor;

    private UrlInterface $urlBuilder;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        Registry $registry,
        DataPersistorInterface $dataPersistor,
        UrlInterface $urlBuilder,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->registry = $registry;
        $this->dataPersistor = $dataPersistor;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Injects the ward-options AJAX URL for the cascading ward selector and the code
     * field's edit-time immutability (the code is the zone identity — SPEC §9).
     * TASK-WY6WP5: the carrier reverse-reference is no longer exposed on the form —
     * the zone form is geography-only; the reference index stays internal (delete/
     * mass-delete/disable protection).
     *
     * @return array
     */
    public function getMeta()
    {
        $meta = parent::getMeta();
        // In this Magento version the framework passes NO meta into form data providers
        // (parent::getMeta() is empty at render time — verified via mergeMetadata debug,
        // TASK-WY6WP5). The dynamic configs are therefore BUILT here; the framework's
        // mergeMetadata array-merges them into the rendered field configs. The previous
        // isset()-guarded writes never executed — the ward AJAX URL was never delivered,
        // which is the root cause of the original "Included Wards renders nothing" defect.
        $optionsUrl = $this->urlBuilder->getUrl('secomm_shippingcore/zone/wardOptions');
        $meta['general']['children']['include_ward_codes']['arguments']['data']['config']['optionsUrl'] = $optionsUrl;
        $zone = $this->registry->registry(\Secomm\ShippingCore\Controller\Adminhtml\Zone\Edit::REGISTRY_KEY);
        if ($zone !== null && $zone->getId()) {
            $meta['general']['children']['code']['arguments']['data']['config']['disabled'] = true;
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $zone = $this->registry->registry(Edit::REGISTRY_KEY);
        $data = [];
        if ($zone !== null && $zone->getId()) {
            $data[$zone->getId()] = [
                'zone_id' => (int) $zone->getId(),
                'code' => $zone->getCode(),
                'label' => $zone->getLabel(),
                'enabled' => $zone->isEnabled() ? 1 : 0,
                'include_province_codes' => $zone->getIncludeProvinceCodes(),
                'include_ward_codes' => $zone->getIncludeWardCodes(),
            ];
        }
        $persisted = (array) $this->dataPersistor->get('secomm_shippingcore_zone_form');
        if ($persisted !== []) {
            $data[(int) ($persisted['zone_id'] ?? 0)] = $persisted;
            $this->dataPersistor->clear('secomm_shippingcore_zone_form');
        }

        return $data;
    }
}
