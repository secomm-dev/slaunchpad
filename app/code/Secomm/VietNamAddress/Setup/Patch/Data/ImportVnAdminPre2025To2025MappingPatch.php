<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Setup\Patch\Data;

use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Secomm\VietNamAddress\Model\Import\VnMappingImporter;

/**
 * TASK-NDSZ7V (seed per TASK-J9AVGK / DEC-FEATYA2C0W-003 §5) — import the reviewed canonical
 * mapping baseline VN_ADMIN_PRE_2025 → VN_ADMIN_2025 (10,064 edges: 63 regions + 10,001 wards)
 * via setup:upgrade — no manual CLI run needed.
 *
 * Delegates to the existing VnMappingImporter (validate ALL rows first — scheme/catalog checks,
 * relation types, duplicate canonical edges, orphan codes against secomm_vietnam_address_unit —
 * then one idempotent upsert on the UNIQUE(source_scheme, source_code, target_scheme,
 * target_code) edge). Any validation failure throws, so a broken baseline fails setup loudly
 * with zero partial writes (same contract as the other Secomm_VietNamAddress data patches).
 *
 * Deliberately data-only: the runtime directory tables, active_scheme config and registry
 * statuses are untouched — VN_ADMIN_2025 stays CURRENT, VN_ADMIN_PRE_2025 stays HISTORICAL.
 * Coverage below 100% is accepted by design: PRE_2025 wards without a reviewed edge resolve
 * as UNMAPPED (never guessed, never synthesised).
 */
class ImportVnAdminPre2025To2025MappingPatch implements DataPatchInterface
{
    private const MAPPING_FILE = 'VN_ADMIN_PRE_2025_TO_2025_mapping.csv';

    public function __construct(
        private readonly VnMappingImporter $mappingImporter,
        private readonly ComponentRegistrarInterface $componentRegistrar
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $path = rtrim((string)$this->componentRegistrar->getPath('module', 'Secomm_VietNamAddress'), '/')
            . '/Files/' . self::MAPPING_FILE;
        $this->mappingImporter->import($path, false);
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        // Both canonical scheme datasets must exist first: the importer's orphan validation
        // looks every edge code up in secomm_vietnam_address_unit, so the reference import
        // (which populates the PRE_2025 units) must have run before this seed.
        return [ImportVnAdminPre2025ReferencePatch::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
