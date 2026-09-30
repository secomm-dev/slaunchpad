<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\CarrierCoverage;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;

/**
 * TASK-WY6WP5 — adapter boundary between the generic CoverageTarget world and the FROZEN
 * carrier coverage config storage (renames TASK-G3K9V2's PolicyConfig; was FEAT-QA23PZ).
 * Writes the SAME generic `carriers/<code>/...` paths the carriers (and the runtime readers
 * CarrierDestinationScopeConfig / GhnConfig) already consume — relocation of the admin UX
 * therefore requires NO data migration and NO runtime change. Writes DEFAULT scope only
 * (P1 single-store; documented limitation — store-scoped overrides are not editable from
 * this screen), while read-side status helpers look at the PERSISTED rows of every
 * supported scope straight from `core_config_data` (never ScopeConfig effective merge,
 * same data-integrity view as CarrierZoneIndex).
 *
 * This class is the only writer ShippingCore ships for these paths; the runtime never
 * reads through it.
 */
class CarrierCoverageConfigAdapter
{
    public const XML_PATH_DESTINATION_SCOPE = 'carriers/%s/destination_scope';
    public const XML_PATH_ALLOWED_ZONE_CODES = 'carriers/%s/allowed_zone_codes';
    public const XML_PATH_RATE_SOURCE_MODE = 'carriers/%s/rate_source_mode';
    public const XML_PATH_ADDRESS_RESOLUTION_POLICY = 'carriers/%s/address_resolution_policy';

    private const CONFIG_CACHE_TYPE = 'config';

    private const CONFIG_TABLE = 'core_config_data';

    private ScopeConfigInterface $scopeConfig;

    private WriterInterface $configWriter;

    private TypeListInterface $cacheTypeList;

    private Validator $validator;

    private ResourceConnection $resource;

    private StoreManagerInterface $storeManager;

    /** @var array<string, bool> per-request memo of hasExplicitConfig, keyed by identity key */
    private array $explicitConfigCache = [];

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        Validator $validator,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->validator = $validator;
        $this->resource = $resource;
        $this->storeManager = $storeManager;
    }

    /**
     * Raw DEFAULT-scope coverage values for the coverage form (the form displays what is
     * persisted, never the fail-closed coercion of the runtime readers). Empty strings
     * mean "not persisted at DEFAULT scope" — the form applies its own XML defaults.
     *
     * @return array{destination_scope: string, allowed_zone_codes: string[], rate_source_mode: string, address_resolution_policy: string}
     */
    public function load(CoverageTargetIdentity $target): array
    {
        $code = $target->code();
        $scope = (string) ($this->scopeConfig->getValue(
            sprintf(self::XML_PATH_DESTINATION_SCOPE, $code)
        ) ?? '');
        $rawZones = $this->scopeConfig->getValue(sprintf(self::XML_PATH_ALLOWED_ZONE_CODES, $code));
        $zones = is_array($rawZones) ? $rawZones : explode(',', (string) $rawZones);

        return [
            'destination_scope' => $scope,
            'allowed_zone_codes' => array_values(array_filter(array_map('trim', $zones), static fn (string $c): bool => $c !== '')),
            'rate_source_mode' => (string) ($this->scopeConfig->getValue(
                sprintf(self::XML_PATH_RATE_SOURCE_MODE, $code)
            ) ?? ''),
            'address_resolution_policy' => (string) ($this->scopeConfig->getValue(
                sprintf(self::XML_PATH_ADDRESS_RESOLUTION_POLICY, $code)
            ) ?? ''),
        ];
    }

    /**
     * Whether an explicit coverage config exists for the target in ANY supported scope
     * (DEFAULT / WEBSITE / STORE). Reads the persisted rows directly — scoped values are
     * live for their stores (runtime store-scoped fallback), so a website/store-only
     * config still counts as Configured (never mislabeled "Not Configured").
     */
    public function hasExplicitConfig(CoverageTargetIdentity $target): bool
    {
        $key = $target->key();
        if (!array_key_exists($key, $this->explicitConfigCache)) {
            $this->explicitConfigCache[$key] = $this->countPersistedRows($target) > 0;
        }

        return $this->explicitConfigCache[$key];
    }

    /**
     * Validate then persist the four coverage values (DEFAULT scope) and clean the config
     * cache so the runtime readers see the new values on the next request. Existing zone
     * selections are NEVER silently cleared: switching back to ALL keeps the persisted list
     * (non-destructive), it just stops being evaluated.
     *
     * @param string[] $zoneCodes
     * @return string[] normalized zone codes (for caller messaging)
     * @throws LocalizedException first validation failure wins
     */
    public function save(
        CoverageTargetIdentity $target,
        string $availability,
        array $zoneCodes,
        string $rateSourceMode,
        string $addressResolutionPolicy
    ): array {
        $normalizedZones = $this->validator->validate($availability, $zoneCodes, $rateSourceMode, $addressResolutionPolicy);
        $code = $target->code();

        $this->configWriter->save(sprintf(self::XML_PATH_DESTINATION_SCOPE, $code), $availability);
        $this->configWriter->save(sprintf(self::XML_PATH_ALLOWED_ZONE_CODES, $code), implode(',', $normalizedZones));
        $this->configWriter->save(sprintf(self::XML_PATH_RATE_SOURCE_MODE, $code), $rateSourceMode);
        $this->configWriter->save(sprintf(self::XML_PATH_ADDRESS_RESOLUTION_POLICY, $code), $addressResolutionPolicy);
        $this->cacheTypeList->cleanType(self::CONFIG_CACHE_TYPE);
        $this->explicitConfigCache[$target->key()] = true;

        return $normalizedZones;
    }

    /**
     * Remove the explicit DEFAULT-scope coverage values; the target stays registered and
     * falls back to the documented runtime defaults (missing destination_scope → ALL).
     * WEBSITE/STORE rows are intentionally untouched — use nonDefaultScopeRows() to warn
     * about them.
     *
     * @return int number of DEFAULT-scope paths that actually had a persisted row
     */
    public function reset(CoverageTargetIdentity $target): int
    {
        $removed = 0;
        foreach ($this->paths($target) as $path) {
            if ($this->hasDefaultRow($path)) {
                $removed++;
            }
            // Idempotent — deleting an absent row is a no-op.
            $this->configWriter->delete($path, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
        }
        if ($removed > 0) {
            $this->cacheTypeList->cleanType(self::CONFIG_CACHE_TYPE);
        }
        unset($this->explicitConfigCache[$target->key()]);

        return $removed;
    }

    /**
     * Human-readable descriptions of the persisted NON-DEFAULT rows ({scope, scope_id}
     * across the four paths, deduped) — the Reset warning names these because they stay
     * live for their stores after a DEFAULT-scope reset.
     *
     * @return string[] e.g. ["Website: vietnam_store", "Store View: fashion_vi"]
     */
    public function nonDefaultScopeRows(CoverageTargetIdentity $target): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(self::CONFIG_TABLE, ['scope', 'scope_id'])
                ->where('path IN (?)', $this->paths($target))
                ->where('scope <> ?', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
        );
        $labels = [];
        foreach ($rows as $row) {
            $label = $this->scopeLabel((string) $row['scope'], (int) $row['scope_id']);
            $labels[$label] = true;
        }

        return array_keys($labels);
    }

    /**
     * @param string[] $paths
     */
    private function countPersistedRows(CoverageTargetIdentity $target): int
    {
        $connection = $this->resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()
                ->from(self::CONFIG_TABLE, ['c' => 'COUNT(*)'])
                ->where('path IN (?)', $this->paths($target))
        );
    }

    private function hasDefaultRow(string $path): bool
    {
        $connection = $this->resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()
                ->from(self::CONFIG_TABLE, ['c' => 'COUNT(*)'])
                ->where('path = ?', $path)
                ->where('scope = ?', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
                ->where('scope_id = ?', 0)
                ->limit(1)
        ) > 0;
    }

    /**
     * @return string[]
     */
    private function paths(CoverageTargetIdentity $target): array
    {
        $code = $target->code();

        return [
            sprintf(self::XML_PATH_DESTINATION_SCOPE, $code),
            sprintf(self::XML_PATH_ALLOWED_ZONE_CODES, $code),
            sprintf(self::XML_PATH_RATE_SOURCE_MODE, $code),
            sprintf(self::XML_PATH_ADDRESS_RESOLUTION_POLICY, $code),
        ];
    }

    /**
     * "Default" is never returned here (nonDefaultScopeRows filters it); a scope id that
     * no longer resolves falls back to "#id" — the persisted row still counts.
     */
    private function scopeLabel(string $scope, int $scopeId): string
    {
        if ($scope === 'websites' || $scope === 'stores') {
            try {
                $name = $scope === 'websites'
                    ? $this->storeManager->getWebsite($scopeId)?->getName()
                    : $this->storeManager->getStore($scopeId)?->getName();
                if ($name !== null && $name !== '') {
                    return ($scope === 'websites' ? 'Website: ' : 'Store View: ') . $name;
                }
            } catch (LocalizedException) {
                // store manager unavailable / entity deleted — id fallback keeps the warning correct
            }
        }

        return sprintf('%s #%d', ucfirst($scope), $scopeId);
    }
}
