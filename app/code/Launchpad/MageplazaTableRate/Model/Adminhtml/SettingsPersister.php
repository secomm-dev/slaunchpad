<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — admin persistence for per-method settings/membership.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model\Adminhtml;

use Launchpad\MageplazaTableRate\Model\MethodSettingsProvider;
use Magento\Framework\App\ResourceConnection;

/**
 * Writes the Launchpad extension tables from admin posts. A method WITHOUT a posted settings
 * payload keeps whatever it had (e.g. legacy flows that never submit the Launchpad tab);
 * a posted payload is the full source of truth (capabilities + full member replace).
 */
class SettingsPersister
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MethodSettingsProvider $settingsProvider
    ) {
    }

    /**
     * Persist the "Launchpad Settings" tab payload for one Mageplaza method.
     *
     * @param int $methodId
     * @param array|null $data null = nothing posted, keep existing state
     */
    public function saveMethodSettings(int $methodId, ?array $data): void
    {
        if ($methodId <= 0 || $data === null) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $settingTable = $this->resourceConnection->getTableName(MethodSettingsProvider::TABLE_SETTING);
        $memberTable = $this->resourceConnection->getTableName(MethodSettingsProvider::TABLE_MEMBER);

        $showToCustomer = (int) (!empty($data['show_to_customer']));
        $useAsFallback = (int) (!empty($data['use_as_fallback']));

        $connection->insertOnDuplicate(
            $settingTable,
            ['method_id' => $methodId, 'show_to_customer' => $showToCustomer, 'use_as_fallback' => $useAsFallback],
            ['show_to_customer', 'use_as_fallback']
        );

        $connection->delete($memberTable, ['method_id = ?' => $methodId]);
        $pairs = $this->parseMemberPairs(is_array($data['members'] ?? null) ? $data['members'] : []);
        foreach ($pairs as $pair) {
            $connection->insert($memberTable, [
                'method_id' => $methodId,
                'carrier_code' => $pair['carrier_code'],
                'method_code' => $pair['method_code'],
                'enabled' => 1,
            ]);
        }
    }

    /**
     * Persist the optional City/Area constraint posted with one rate row
     * (empty value = wildcard — the constraint row is removed).
     */
    public function saveRateCity(int $rateId, ?string $cityCode): void
    {
        if ($rateId <= 0) {
            return;
        }

        $cityCode = trim((string) $cityCode);
        $table = $this->resourceConnection->getTableName(MethodSettingsProvider::TABLE_RATE_CITY);
        $connection = $this->resourceConnection->getConnection();

        if ($cityCode === '') {
            $connection->delete($table, ['rate_id = ?' => $rateId]);

            return;
        }

        if (!$this->settingsProvider->cityCodeExists($cityCode)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('City / Area code "%1" does not exist in the address hierarchy — import or save a valid code.', $cityCode)
            );
        }

        $connection->insertOnDuplicate($table, ['rate_id' => $rateId, 'city_code' => $cityCode], ['city_code']);
    }

    /**
     * @param array<int, string> $rawPairs encoded `carrier_code||method_code` values
     * @return array<int, array{carrier_code: string, method_code: string}>
     */
    private function parseMemberPairs(array $rawPairs): array
    {
        $parsed = [];
        foreach ($rawPairs as $raw) {
            $parts = explode(\Launchpad\MageplazaTableRate\Model\Source\ShippingMethod::PAIR_SEPARATOR, (string) $raw);
            [$carrierCode, $methodCode] = array_pad($parts, 2, '');
            $carrierCode = trim($carrierCode);
            $methodCode = trim($methodCode);
            // Recursion guard, mirrored from the option source (defense in depth).
            if ($carrierCode === '' || $methodCode === '' || $carrierCode === 'mptablerate') {
                continue;
            }
            $parsed[$carrierCode . '||' . $methodCode] = [
                'carrier_code' => $carrierCode,
                'method_code' => $methodCode,
            ];
        }

        return array_values($parsed);
    }

    /**
     * @see MethodSettingsProvider::cityCodeExists() — convenience delegate for the importer.
     */
    public function cityCodeExists(string $cityCode): bool
    {
        return $this->settingsProvider->cityCodeExists($cityCode);
    }

    /**
     * @see MethodSettingsProvider::cityRegionId() — TASK-JZXM66 delegate for the importer.
     */
    public function cityRegionId(string $cityCode): ?int
    {
        return $this->settingsProvider->cityRegionId($cityCode);
    }

    /**
     * @see MethodSettingsProvider::cityBelongsToRegion() — TASK-JZXM66 delegate for the
     *      rate-save plugin (region consistency guard).
     */
    public function cityBelongsToRegion(string $cityCode, int $regionId): bool
    {
        return $this->settingsProvider->cityBelongsToRegion($cityCode, $regionId);
    }
}
