<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CanonicalResolutionSnapshotInterface;
use Secomm\ShippingCore\Model\Address\CanonicalResolutionSnapshot;

/**
 * TASK-Y3X6H5 (Delta B/C) — canonical resolution snapshot VO: canonical identities ONLY
 * (no provider-value fields exist structurally), failure classes preserved un-collapsed,
 * provenance required for externally resolved snapshots.
 */
class CanonicalResolutionSnapshotTest extends TestCase
{
    public function testResolvedSnapshotShape(): void
    {
        $snapshot = new CanonicalResolutionSnapshot(
            canonical2025Scheme: 'VN_ADMIN_2025',
            canonical2025UnitCode: 'VNA25-0A1B2C3D4E',
            status: CanonicalResolutionSnapshotInterface::STATUS_RESOLVED,
            failureClass: CanonicalResolutionSnapshotInterface::FAILURE_CLASS_NONE,
            source: CanonicalResolutionSnapshotInterface::SOURCE_LOCAL_MAPPING,
            pre2025ProvinceUnitCode: 'VNAP25-P000000001',
            pre2025DistrictUnitCode: 'VNAP25-D000000001',
            pre2025WardUnitCode: 'VNAP25-W000000001',
            provenanceTimestamp: '2026-09-11T00:00:00Z',
            provenanceMappingVersion: 'v1.0.0'
        );

        $this->assertSame(CanonicalResolutionSnapshotInterface::STATUS_RESOLVED, $snapshot->getStatus());
        $this->assertSame(CanonicalResolutionSnapshotInterface::FAILURE_CLASS_NONE, $snapshot->getFailureClass());
        $this->assertSame(CanonicalResolutionSnapshotInterface::SOURCE_LOCAL_MAPPING, $snapshot->getSource());
        $this->assertSame('VNA25-0A1B2C3D4E', $snapshot->getCanonical2025UnitCode());
        // Every unit code on the snapshot is a SECOMM canonical code — provider ids have no field.
        $this->assertSame('VNAP25-W000000001', $snapshot->getPre2025WardUnitCode());
        $this->assertNull($snapshot->getProvenanceResolver());
    }

    public function testUnresolvedPreservesEachFailureClass(): void
    {
        foreach ([
            CanonicalResolutionSnapshotInterface::FAILURE_CLASS_AMBIGUOUS,
            CanonicalResolutionSnapshotInterface::FAILURE_CLASS_UNMAPPED,
            CanonicalResolutionSnapshotInterface::FAILURE_CLASS_TECHNICAL,
        ] as $failureClass) {
            $snapshot = new CanonicalResolutionSnapshot(
                canonical2025Scheme: 'VN_ADMIN_2025',
                canonical2025UnitCode: '',
                status: CanonicalResolutionSnapshotInterface::STATUS_UNRESOLVED,
                failureClass: $failureClass,
                source: CanonicalResolutionSnapshotInterface::SOURCE_LOCAL_MAPPING
            );

            $this->assertSame(CanonicalResolutionSnapshotInterface::STATUS_UNRESOLVED, $snapshot->getStatus());
            $this->assertSame($failureClass, $snapshot->getFailureClass());
            $this->assertFalse($snapshot->getFailureClass() === CanonicalResolutionSnapshotInterface::FAILURE_CLASS_NONE);
        }
    }

    public function testExternalSourceRequiresProvenanceResolver(): void
    {
        $snapshot = new CanonicalResolutionSnapshot(
            canonical2025Scheme: 'VN_ADMIN_2025',
            canonical2025UnitCode: 'VNA25-0A1B2C3D4E',
            status: CanonicalResolutionSnapshotInterface::STATUS_RESOLVED,
            failureClass: CanonicalResolutionSnapshotInterface::FAILURE_CLASS_NONE,
            source: CanonicalResolutionSnapshotInterface::SOURCE_EXTERNAL_RESOLVER,
            provenanceResolver: 'vietmap'
        );

        $this->assertSame('vietmap', $snapshot->getProvenanceResolver());
        $this->addToAssertionCount(1);
    }

    public function testExternalSourceWithoutResolverIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must name its resolver');
        new CanonicalResolutionSnapshot(
            canonical2025Scheme: 'VN_ADMIN_2025',
            canonical2025UnitCode: 'VNA25-0A1B2C3D4E',
            status: CanonicalResolutionSnapshotInterface::STATUS_RESOLVED,
            failureClass: CanonicalResolutionSnapshotInterface::FAILURE_CLASS_NONE,
            source: CanonicalResolutionSnapshotInterface::SOURCE_EXTERNAL_RESOLVER
        );
    }

    public function testRejectsResolvedWithFailureClass(): void
    {
        $this->expectException(\LogicException::class);
        new CanonicalResolutionSnapshot(
            canonical2025Scheme: 'VN_ADMIN_2025',
            canonical2025UnitCode: 'VNA25-0A1B2C3D4E',
            status: CanonicalResolutionSnapshotInterface::STATUS_RESOLVED,
            failureClass: CanonicalResolutionSnapshotInterface::FAILURE_CLASS_UNMAPPED,
            source: CanonicalResolutionSnapshotInterface::SOURCE_LOCAL_MAPPING
        );
    }

    public function testRejectsResolvedWithoutCanonicalIdentity(): void
    {
        $this->expectException(\LogicException::class);
        new CanonicalResolutionSnapshot(
            canonical2025Scheme: 'VN_ADMIN_2025',
            canonical2025UnitCode: '',
            status: CanonicalResolutionSnapshotInterface::STATUS_RESOLVED,
            failureClass: CanonicalResolutionSnapshotInterface::FAILURE_CLASS_NONE,
            source: CanonicalResolutionSnapshotInterface::SOURCE_LOCAL_MAPPING
        );
    }

    public function testRejectsUnresolvedWithNoneFailureClass(): void
    {
        $this->expectException(\LogicException::class);
        new CanonicalResolutionSnapshot(
            canonical2025Scheme: 'VN_ADMIN_2025',
            canonical2025UnitCode: '',
            status: CanonicalResolutionSnapshotInterface::STATUS_UNRESOLVED,
            failureClass: CanonicalResolutionSnapshotInterface::FAILURE_CLASS_NONE,
            source: CanonicalResolutionSnapshotInterface::SOURCE_LOCAL_MAPPING
        );
    }

    public function testRejectsUnknownStatusAndSource(): void
    {
        try {
            new CanonicalResolutionSnapshot('VN_ADMIN_2025', '', 'PARTIAL', 'NONE', 'LOCAL_MAPPING');
            $this->fail('Expected unknown status rejection.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('status', $exception->getMessage());
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('source');
        new CanonicalResolutionSnapshot('VN_ADMIN_2025', '', 'UNRESOLVED', 'UNMAPPED', 'VIETMAP');
    }
}
