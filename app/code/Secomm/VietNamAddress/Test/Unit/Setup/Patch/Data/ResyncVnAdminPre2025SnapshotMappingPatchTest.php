<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Setup\Patch\Data;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Import\VnImportValidationException;
use Secomm\VietNamAddress\Model\Import\VnSnapshotMappingResync;
use Secomm\VietNamAddress\Setup\Patch\Data\ImportVnAdminPre2025To2025MappingPatch;
use Secomm\VietNamAddress\Setup\Patch\Data\RefreshVnAdminPre2025Snapshot2024;
use Secomm\VietNamAddress\Setup\Patch\Data\ResyncVnAdminPre2025SnapshotMappingPatch;

/**
 * TASK-SEC-1.1 (r2) — the patch is a thin trigger for the EXACT keyset resync; the whole
 * applied-patch chain runs before it, so every environment converges on the snapshot
 * contract regardless of what any earlier seed left behind. Count is NOT an identity.
 */
class ResyncVnAdminPre2025SnapshotMappingPatchTest extends TestCase
{
    private VnSnapshotMappingResync&MockObject $resync;

    private ResyncVnAdminPre2025SnapshotMappingPatch $patch;

    protected function setUp(): void
    {
        $this->resync = $this->createMock(VnSnapshotMappingResync::class);
        $this->patch = new ResyncVnAdminPre2025SnapshotMappingPatch($this->resync);
    }

    public function testApplyPerformsTheExactResync(): void
    {
        $this->resync->expects($this->once())->method('sync')->willReturn([
            'upserted_snapshot_edges' => 10418,
            'removed_stale_keys' => 41,
            'manifest' => [],
        ]);

        $this->patch->apply();
    }

    public function testDependsOnTheFullHistoricalChain(): void
    {
        $this->assertSame(
            [ImportVnAdminPre2025To2025MappingPatch::class, RefreshVnAdminPre2025Snapshot2024::class],
            $this->patch::getDependencies()
        );
        $this->assertSame([], $this->patch->getAliases());
    }

    public function testValidationFailurePropagatesToFailSetupLoudly(): void
    {
        $this->resync->method('sync')->willThrowException(
            new VnImportValidationException(
                new \Magento\Framework\Phrase('Mapping validation failed with 1 error(s); nothing was written.'),
                ['Line 7: orphan target code "VNA25-GHOST" not in unit table for VN_ADMIN_2025.']
            )
        );

        $this->expectException(VnImportValidationException::class);
        $this->patch->apply();
    }
}
