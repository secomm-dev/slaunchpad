<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Secomm\VietNamAddress\Model\Import\VnAddressSchemeImporter;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-ADT94K / DEC-FEATYA2C0W-003 — fresh installs get the VN_ADMIN_2025 dataset:
 * re-key bridge (city_id preserved) + code-based upsert + historical unit snapshot +
 * registry sync + membership reseed + config defaults (active_scheme + profile mapping).
 * VN_ADMIN_PRE_2025 stays opt-in via `secomm:vietnam-address:import --scheme … --swap`.
 *
 * Idempotent: re-running only performs updates (0 inserts). Validation failures throw so a
 * broken dataset fails setup loudly instead of half-installing.
 */
class ImportVnAdmin2025SchemePatch implements DataPatchInterface
{
    public function __construct(
        private readonly VnAddressSchemeImporter $importer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $this->importer->import(VnSchemes::VN_ADMIN_2025);
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        // TASK-6MKF0V: the legacy VN_Address_2Level bootstrap patch is removed — this importer
        // bootstraps a fresh DB entirely (regions + units + membership + mapping + active_scheme)
        // and therefore runs FIRST. SeedVnProfileMembership depends on it: its region claims
        // need the dataset regions created here.
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
