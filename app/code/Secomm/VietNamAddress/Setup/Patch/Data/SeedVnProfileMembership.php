<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Setup\Patch\Data;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Secomm\AddressDropdown\Model\AddressProfileResolver;

/**
 * FEAT-YA2C0W / TASK-R83FXW, amended by DEC-FEATYA2C0W-003 (2026-08-27):
 * seed the VN_ADMIN_2025 dataset membership (subtree-claim on the 34 current VN region roots)
 * and set the country→profile default mapping VN => vn_admin_2025 when unset.
 *
 * Idempotent by construction:
 * - membership rows upsert on the (profile_code, location_type, location_id) primary key;
 * - the config default is only written when `address/profiles/mapping` is still unset.
 *
 * On dev DBs seeded with the pre-DEC-003 profile code (vn_current), this patch does not re-run
 * (setup_patch_list); the first `secomm:vietnam-address:import` heals those rows through the
 * alias map (VnSchemes::schemeForProfile) — no purge, city_id preserved.
 */
class SeedVnProfileMembership implements DataPatchInterface, PatchRevertableInterface
{
    private const PROFILE_CODE = 'vn_admin_2025';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ConfigInterface $configWriter
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();

        $currentVnRegionIds = $connection->fetchCol(
            $connection->select()
                ->from($this->moduleDataSetup->getTable('directory_country_region'), ['region_id'])
                ->where('country_id = ?', 'VN')
        );

        $rows = array_map(
            static fn (int $regionId): array => [
                'profile_code' => self::PROFILE_CODE,
                'location_type' => 'region',
                'location_id' => $regionId,
                'include_subtree' => 1,
            ],
            array_map('intval', $currentVnRegionIds)
        );

        if (!empty($rows)) {
            // Upsert on the membership PK: re-running never duplicates rows.
            $connection->insertOnDuplicate(
                $this->moduleDataSetup->getTable('secomm_address_profile_location'),
                $rows
            );
        }

        if ($this->scopeConfig->getValue(AddressProfileResolver::XML_PATH_PROFILE_MAPPING) === null) {
            $this->configWriter->saveConfig(
                AddressProfileResolver::XML_PATH_PROFILE_MAPPING,
                serialize(['VN' => self::PROFILE_CODE]),
                'default',
                0
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function revert(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->delete(
            $this->moduleDataSetup->getTable('secomm_address_profile_location'),
            ['profile_code = ?' => self::PROFILE_CODE]
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        // The 34 current VN regions must exist before membership can claim them.
        // TASK-6MKF0V: they are now created by the scheme import (the legacy 2-level
        // bootstrap patch is removed), so seed membership AFTER it.
        return [ImportVnAdmin2025SchemePatch::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
