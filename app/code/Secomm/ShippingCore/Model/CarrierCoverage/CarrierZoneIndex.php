<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\CarrierCoverage;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetType;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 (final TL verification) — read-only index of PERSISTED zone
 * references across ALL supported config scopes.
 *
 * Scope contract (verified against runtime 2026-09-22): the carrier readers
 * (`GhnConfig`, `CarrierDestinationScopeConfig`) are store-scoped —
 * `Ghn::collect()` passes the rate-request storeId into `ScopeConfig::getValue(...,
 * SCOPE_STORE, $storeId)`, whose Magento resolution is single-parent fallback
 * (store explicit → website explicit → default). WEBSITE- and STORE-level persisted
 * references are therefore LIVE for their stores, and the DEFAULT value additionally
 * governs context-less reads. The legacy carrier system.xml fields were
 * showInWebsite/showInStore enabled, so scoped values can exist independently.
 *
 * This index therefore reads the PERSISTED rows per scope straight from
 * `core_config_data` (never ScopeConfig effective resolution, which merges/shadows and
 * can hide a layered reference). It is a data-integrity view: no runtime diagnostics,
 * no evaluator involvement. Only REGISTERED coverage targets of type CARRIER (the
 * Shipping Coverage surface, TASK-WY6WP5) are indexed. `config.php` carries no
 * `carriers/*` entries (verified 2026-09-22), so the DB is the complete persisted truth
 * for these paths on this system.
 */
class CarrierZoneIndex
{
    private const XML_PATH_ALLOWED_ZONE_CODES = 'carriers/%s/allowed_zone_codes';

    private const CONFIG_TABLE = 'core_config_data';

    /** scope priority for deterministic ordering: default < websites < stores */
    private const SCOPE_PRIORITY = ['default' => 0, 'websites' => 1, 'stores' => 2];

    private ResourceConnection $resource;

    private CoverageTargetRegistry $targetRegistry;

    private StoreManagerInterface $storeManager;

    public function __construct(
        ResourceConnection $resource,
        CoverageTargetRegistry $targetRegistry,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
        $this->targetRegistry = $targetRegistry;
        $this->storeManager = $storeManager;
    }

    /**
     * Every persisted reference to the zone code, one entry per (carrier, scope layer).
     *
     * @return array<int, array{carrier: string, carrier_label: string, scope: string, scope_id: int, scope_label: string}>
     */
    public function findReferences(string $zoneCode): array
    {
        $zoneCode = strtoupper(trim($zoneCode));
        if ($zoneCode === '') {
            return [];
        }
        $references = [];
        foreach ($this->persistedZoneMaps() as $entry) {
            if (!in_array($zoneCode, $entry['codes'], true)) {
                continue;
            }
            $references[] = [
                'carrier' => $entry['carrier'],
                'carrier_label' => $this->targetRegistry->getLabel(CoverageTargetIdentity::carrier($entry['carrier'])),
                'scope' => $entry['scope'],
                'scope_id' => $entry['scope_id'],
                'scope_label' => $entry['scope_label'],
            ];
        }

        return $references;
    }

    /**
     * Distinct carrier codes referencing the zone in ANY supported scope (ordered by
     * scope layer: default → websites → stores, then carrier path — same as findReferences).
     *
     * @return string[]
     */
    public function carriersForZone(string $zoneCode): array
    {
        $carriers = [];
        foreach ($this->findReferences($zoneCode) as $reference) {
            if (!in_array($reference['carrier'], $carriers, true)) {
                $carriers[] = $reference['carrier'];
            }
        }

        return $carriers;
    }

    /**
     * Persisted allowed-zone code lists for every registered carrier, all scope layers,
     * deterministically ordered (default → websites by id → stores by id → carrier path).
     *
     * @return array<int, array{carrier: string, codes: string[], scope: string, scope_id: int, scope_label: string}>
     */
    private function persistedZoneMaps(): array
    {
        $paths = [];
        foreach ($this->targetRegistry->getAllByType(CoverageTargetType::CARRIER) as $target) {
            $paths[] = sprintf(self::XML_PATH_ALLOWED_ZONE_CODES, $target->getIdentity()->code());
        }
        if ($paths === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(self::CONFIG_TABLE, ['scope', 'scope_id', 'value', 'path'])
                ->where('path IN (?)', $paths)
        );
        // Deterministic order without DB-specific expressions: default → websites by id →
        // stores by id → carrier path.
        usort($rows, static function (array $a, array $b): int {
            $priority = self::SCOPE_PRIORITY;
            $scopeCompare = ($priority[(string) $a['scope']] ?? 3) <=> ($priority[(string) $b['scope']] ?? 3);
            if ($scopeCompare !== 0) {
                return $scopeCompare;
            }
            $idCompare = (int) $a['scope_id'] <=> (int) $b['scope_id'];

            return $idCompare !== 0 ? $idCompare : strcmp((string) $a['path'], (string) $b['path']);
        });

        $labels = $this->scopeLabels();
        $maps = [];
        foreach ($rows as $row) {
            $carrierCode = $this->carrierFromPath((string) $row['path']);
            // A stray/malformed code in core_config_data must never brick the zone admin
            // pages: identity validation throws on such codes, so indexability is checked
            // WITHOUT constructing an identity (same data-integrity stance as before —
            // un-indexable paths are simply not references).
            if ($carrierCode === null || !self::isIndexableCarrierCode($carrierCode)) {
                continue;
            }
            if (!$this->targetRegistry->has(CoverageTargetIdentity::carrier($carrierCode))) {
                continue;
            }
            $scope = (string) $row['scope'];
            $scopeId = (int) $row['scope_id'];
            $codes = [];
            foreach (explode(',', (string) $row['value']) as $code) {
                $code = strtoupper(trim($code));
                if ($code !== '' && !in_array($code, $codes, true)) {
                    $codes[] = $code;
                }
            }
            if ($codes === []) {
                continue;
            }
            $maps[] = [
                'carrier' => $carrierCode,
                'codes' => $codes,
                'scope' => $scope,
                'scope_id' => $scopeId,
                'scope_label' => $labels[$scope][$scopeId] ?? ucfirst($scope),
            ];
        }

        return $maps;
    }

    /**
     * Same alphabet as CoverageTargetIdentity (carrier codes are lowercase alphanumerics +
     * underscore) — lets the index discard corrupt paths without throwing.
     */
    private static function isIndexableCarrierCode(string $code): bool
    {
        return preg_match('/^[a-z0-9_]+$/', $code) === 1;
    }

    /**
     * @param string $path e.g. carriers/secomm_ghn/allowed_zone_codes
     */
    private function carrierFromPath(string $path): ?string
    {
        $parts = explode('/', trim($path, '/'));
        if (count($parts) !== 3 || $parts[0] !== 'carriers' || $parts[2] !== 'allowed_zone_codes') {
            return null;
        }
        $carrierCode = trim($parts[1]);

        return $carrierCode !== '' ? $carrierCode : null;
    }

    /**
     * Human-readable scope labels: "Default", "Website: <name>", "Store View: <name>".
     * A scope id that no longer resolves (deleted website/store) falls back to "#id" —
     * the persisted row still counts for the integrity guard.
     *
     * @return array<string, array<int, string>>
     */
    private function scopeLabels(): array
    {
        $labels = ['default' => [0 => 'Default']];
        try {
            foreach ($this->storeManager->getWebsites(true) as $website) {
                $labels['websites'][(int) $website->getId()] = 'Website: ' . $website->getName();
            }
        } catch (\Magento\Framework\Exception\LocalizedException) {
            // store manager unavailable in this context — id fallback keeps the guard correct
        }
        try {
            foreach ($this->storeManager->getStores(true) as $store) {
                $labels['stores'][(int) $store->getId()] = 'Store View: ' . $store->getName();
            }
        } catch (\Magento\Framework\Exception\LocalizedException) {
            // ditto
        }

        return $labels;
    }
}
