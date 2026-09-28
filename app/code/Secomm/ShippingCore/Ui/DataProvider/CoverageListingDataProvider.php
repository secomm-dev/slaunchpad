<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Ui\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetType;
use Secomm\ShippingCore\Model\CarrierCoverage\Availability;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;
use Secomm\ShippingCore\Model\Config\Source\AvailabilityOptions;
use Secomm\ShippingCore\Model\Config\Source\ConfigurationStatusOptions;
use Secomm\ShippingCore\Model\ResourceModel\Zone\CollectionFactory;

/**
 * TASK-WY6WP5 — Shipping Coverage GRID data: one row per REGISTERED coverage target of
 * type CARRIER (P1), joined with its persisted coverage state (directive §5 — the list
 * may show all registered coverage-capable targets and must NOT fabricate persisted
 * config to display a row: a target without explicit config shows "Not Configured" with
 * the effective default availability).
 *
 * Targets are DI-registered, not DB rows, so the provider is collection-less — the zone
 * grid collection injected below only absorbs the framework's mandatory
 * addFilter()/setLimit() calls (AbstractDataProvider routes them into the collection;
 * null → fatal. Same integration defect hotfixed for the form provider 2026-09-22).
 * Rows are deterministic (registry order); the grid has no filter toolbar and static
 * columns, so paging/sorting are not applied.
 */
class CoverageListingDataProvider extends AbstractDataProvider
{
    private const ZONES_PREVIEW_COUNT = 3;

    private CoverageTargetRegistry $targetRegistry;

    private CarrierCoverageConfigAdapter $configAdapter;

    private AvailabilityOptions $availabilityOptions;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CoverageTargetRegistry $targetRegistry,
        CarrierCoverageConfigAdapter $configAdapter,
        AvailabilityOptions $availabilityOptions,
        CollectionFactory $zoneCollectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        // Absorbs framework addFilter()/setLimit() calls — never queried for data.
        $this->collection = $zoneCollectionFactory->create();
        $this->targetRegistry = $targetRegistry;
        $this->configAdapter = $configAdapter;
        $this->availabilityOptions = $availabilityOptions;
    }

    /**
     * @return array{totalRecords: int, items: array}
     */
    public function getData(): array
    {
        $items = [];
        foreach ($this->targetRegistry->getAllByType(CoverageTargetType::CARRIER) as $target) {
            $identity = $target->getIdentity();
            $configured = $this->configAdapter->hasExplicitConfig($identity);
            // A target with a scoped-only config can legitimately have no DEFAULT values.
            $coverage = $this->configAdapter->load($identity) ?? [];
            $coverage += ['destination_scope' => '', 'allowed_zone_codes' => []];
            $items[] = [
                'target_key' => $identity->key(),
                'target_type' => $identity->type(),
                'target_type_label' => (string) __('Carrier'),
                'target_code' => $identity->code(),
                'target_label' => $target->getLabel(),
                'configuration_status' => $configured
                    ? ConfigurationStatusOptions::CONFIGURED
                    : ConfigurationStatusOptions::NOT_CONFIGURED,
                'configuration_status_label' => $configured
                    ? (string) __('Configured')
                    : (string) __('Not Configured'),
                'availability_label' => $this->availabilityLabel($configured, (string) $coverage['destination_scope']),
                'zones_label' => $this->zonesLabel($coverage['allowed_zone_codes']),
                'action_mode' => $configured ? 'edit' : 'configure',
            ];
        }

        return [
            'totalRecords' => count($items),
            'items' => $items,
        ];
    }

    /**
     * Effective availability: the persisted DEFAULT value when configured, otherwise the
     * documented runtime default (missing destination_scope → ALL).
     */
    private function availabilityLabel(bool $configured, string $persistedScope): string
    {
        if (!$configured) {
            return (string) __('All Vietnam (default — not explicitly configured)');
        }
        foreach ($this->availabilityOptions->toOptionArray() as $option) {
            if ($option['value'] === $persistedScope) {
                return (string) $option['label'];
            }
        }
        if ($persistedScope === Availability::ALL) {
            return (string) __('All Vietnam');
        }

        // Persisted value outside the admin vocabulary — show it verbatim (runtime keeps
        // its own fail-closed handling for unknown scopes; never coerce here).
        return $persistedScope;
    }

    /**
     * @param string[] $zones
     */
    private function zonesLabel(array $zones): string
    {
        if ($zones === []) {
            return (string) __('—');
        }
        if (count($zones) <= self::ZONES_PREVIEW_COUNT) {
            return implode(', ', $zones);
        }

        return implode(', ', array_slice($zones, 0, self::ZONES_PREVIEW_COUNT))
            . ' … +' . (count($zones) - self::ZONES_PREVIEW_COUNT);
    }
}
