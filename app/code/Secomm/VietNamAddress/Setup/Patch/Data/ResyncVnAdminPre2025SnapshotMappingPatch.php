<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Secomm\VietNamAddress\Model\Import\VnSnapshotMappingResync;

/**
 * TASK-SEC-1.1 (closure of TASK-SEC-B1) — idempotent MAPPING resync for environments whose
 * applied-patch history predates the authoritative end-of-2024 snapshot.
 *
 * WHY THIS PATCH EXISTS: the superseded baseline import (`ImportVnAdminPre2025To2025MappingPatch`)
 * originally seeded the 10,064-edge baseline file. Environments that applied that history have
 * since been corrected by `RefreshVnAdminPre2025Snapshot2024` (wipe + snapshot re-import), but
 * a reference layer can still drift — e.g. a CLI `import-mapping` of the superseded file. This
 * patch runs LAST in the chain and guarantees the 10,418 snapshot edges are present:
 *
 *   - the mapping table already holds exactly the snapshot edge count → cheap no-op;
 *   - otherwise it re-runs the existing VnMappingImporter against the SNAPSHOT file —
 *     validate-ALL then idempotent upsert (UNIQUE per source/target edge), NEVER a delete:
 *     no existing row is silently removed (a reconciliation report, not this patch, is the
 *     tool for reviewing stray edges).
 *
 * Deliberately scoped: units, registry statuses, active_scheme and the runtime directory
 * tables are untouched; no automatic scheme swap; repeat application is a no-op.
 */
class ResyncVnAdminPre2025SnapshotMappingPatch implements DataPatchInterface
{
    public function __construct(
        private readonly VnSnapshotMappingResync $resync
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        // Exact content sync (upsert snapshot + remove the recorded 41 bundled-stale keys) —
        // idempotent and convergent; count is deliberately NOT used as an identity.
        $this->resync->sync();
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        // After the whole historical chain: the baseline seed AND the corrective refresh —
        // this patch is the final mapping-consistency word of a setup:upgrade run.
        return [
            ImportVnAdminPre2025To2025MappingPatch::class,
            RefreshVnAdminPre2025Snapshot2024::class,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
