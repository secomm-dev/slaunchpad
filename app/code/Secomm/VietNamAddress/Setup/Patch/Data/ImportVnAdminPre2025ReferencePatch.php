<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Secomm\VietNamAddress\Model\Import\VnReferenceSchemeImporter;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-F9XJ5G — populate the HISTORICAL VN_ADMIN_PRE_2025 dataset into the reference layer
 * (secomm_vietnam_address_unit + secomm_vietnam_address_scheme) on every environment via
 * setup:upgrade — no manual `--reference-only` CLI run and no runtime swap required.
 *
 * Uses the reference-only importer on purpose: it reads + validates the dataset, upserts the
 * historical units and registers the scheme as HISTORICAL in ONE transaction, and structurally
 * cannot touch the runtime directory tables, active_scheme config, membership or caches.
 * VN_ADMIN_2025 stays the active runtime scheme (registry CURRENT is never demoted — a
 * PRE_2025 row that somehow is CURRENT keeps its status; the import only refreshes metadata).
 *
 * Idempotent: re-running only upserts (UNIQUE(scheme_code, code)); validation failures throw
 * so a broken historical dataset fails setup loudly instead of half-installing (same contract
 * as ImportVnAdmin2025SchemePatch).
 */
class ImportVnAdminPre2025ReferencePatch implements DataPatchInterface
{
    public function __construct(
        private readonly VnReferenceSchemeImporter $referenceImporter
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $this->referenceImporter->import(VnSchemes::VN_ADMIN_PRE_2025);
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        // Fresh installs: run AFTER the VN_ADMIN_2025 bootstrap so the registry already holds
        // the CURRENT scheme before PRE_2025 is marked HISTORICAL (and the unit table keeps
        // the 2025-first accumulation order).
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
