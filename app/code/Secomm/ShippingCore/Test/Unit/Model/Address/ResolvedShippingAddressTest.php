<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Address\ResolvedShippingAddress;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;

/**
 * TASK-AQT7V3 — result VO invariants: status reuse, candidate preservation, resolved-state guard.
 */
class ResolvedShippingAddressTest extends TestCase
{
    public function testExactIsResolvedWithUnitCodeAndNoCandidates(): void
    {
        $resolved = new ResolvedShippingAddress(
            status: VnAddressResolutionInterface::STATUS_EXACT,
            schemeCode: 'VN_ADMIN_2025',
            unitCode: 'VNA25-0A1B2C3D4E'
        );

        $this->assertSame(VnAddressResolutionInterface::STATUS_EXACT, $resolved->getStatus());
        $this->assertSame('VN_ADMIN_2025', $resolved->getSchemeCode());
        $this->assertSame('VNA25-0A1B2C3D4E', $resolved->getUnitCode());
        $this->assertSame([], $resolved->getCandidateCodes());
        $this->assertTrue($resolved->isResolved());
    }

    public function testMappedIsResolvedWithSingleDeterministicUnitCode(): void
    {
        $resolved = new ResolvedShippingAddress(
            status: VnAddressResolutionInterface::STATUS_MAPPED,
            schemeCode: 'VN_ADMIN_PRE_2025',
            unitCode: 'VNAP25-9F8E7D6C5B'
        );

        $this->assertSame(VnAddressResolutionInterface::STATUS_MAPPED, $resolved->getStatus());
        $this->assertSame('VNAP25-9F8E7D6C5B', $resolved->getUnitCode());
        $this->assertSame([], $resolved->getCandidateCodes());
        $this->assertTrue($resolved->isResolved());
    }

    public function testAmbiguousPreservesAllCandidatesAndNeverExposesOneAsUnitCode(): void
    {
        $candidates = ['VNAP25-B2B2B2B2B2', 'VNAP25-A1A1A1A1A1', 'VNAP25-C3C3C3C3C3'];

        $resolved = new ResolvedShippingAddress(
            status: VnAddressResolutionInterface::STATUS_AMBIGUOUS,
            schemeCode: 'VN_ADMIN_PRE_2025',
            unitCode: null,
            candidateCodes: $candidates
        );

        $this->assertSame(VnAddressResolutionInterface::STATUS_AMBIGUOUS, $resolved->getStatus());
        $this->assertNull($resolved->getUnitCode());
        $this->assertSame($candidates, $resolved->getCandidateCodes());
        $this->assertFalse($resolved->isResolved());
    }

    public function testUnmappedHasNoUnitCodeAndNoCandidates(): void
    {
        $resolved = new ResolvedShippingAddress(
            status: VnAddressResolutionInterface::STATUS_UNMAPPED,
            schemeCode: 'VN_ADMIN_PRE_2025',
            unitCode: null
        );

        $this->assertSame(VnAddressResolutionInterface::STATUS_UNMAPPED, $resolved->getStatus());
        $this->assertNull($resolved->getUnitCode());
        $this->assertSame([], $resolved->getCandidateCodes());
        $this->assertFalse($resolved->isResolved());
    }

    public function testIsResolvedMatrix(): void
    {
        $cases = [
            VnAddressResolutionInterface::STATUS_EXACT => true,
            VnAddressResolutionInterface::STATUS_MAPPED => true,
            VnAddressResolutionInterface::STATUS_AMBIGUOUS => false,
            VnAddressResolutionInterface::STATUS_UNMAPPED => false,
        ];
        foreach ($cases as $status => $expected) {
            $resolved = new ResolvedShippingAddress(
                status: $status,
                schemeCode: 'VN_ADMIN_2025',
                unitCode: $expected ? 'VNA25-0A1B2C3D4E' : null,
                candidateCodes: $status === VnAddressResolutionInterface::STATUS_AMBIGUOUS
                    ? ['VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2']
                    : []
            );
            $this->assertSame($expected, $resolved->isResolved(), "isResolved() for $status");
        }
    }

    public function testRejectsUnknownStatus(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown shipping address resolution status');
        new ResolvedShippingAddress(status: 'RESOLVED_LOCAL', schemeCode: 'VN_ADMIN_2025', unitCode: 'VNA25-0A1B2C3D4E');
    }

    public function testRejectsResolvedStatusWithoutUnitCode(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('requires a resolved unit code');
        new ResolvedShippingAddress(status: VnAddressResolutionInterface::STATUS_MAPPED, schemeCode: 'VN_ADMIN_2025', unitCode: null);
    }

    public function testRejectsAmbiguousWithoutCandidates(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('requires at least one candidate');
        new ResolvedShippingAddress(status: VnAddressResolutionInterface::STATUS_AMBIGUOUS, schemeCode: 'VN_ADMIN_2025', unitCode: null);
    }

    public function testRejectsAmbiguousWithUnitCode(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('never exposed as resolved');
        new ResolvedShippingAddress(
            status: VnAddressResolutionInterface::STATUS_AMBIGUOUS,
            schemeCode: 'VN_ADMIN_2025',
            unitCode: 'VNA25-0A1B2C3D4E',
            candidateCodes: ['VNAP25-A1A1A1A1A1']
        );
    }

    public function testRejectsUnmappedWithUnitCode(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('never exposed as resolved');
        new ResolvedShippingAddress(
            status: VnAddressResolutionInterface::STATUS_UNMAPPED,
            schemeCode: 'VN_ADMIN_2025',
            unitCode: 'VNA25-0A1B2C3D4E'
        );
    }

    public function testRejectsEmptySchemeCode(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('non-empty scheme code');
        new ResolvedShippingAddress(
            status: VnAddressResolutionInterface::STATUS_UNMAPPED,
            schemeCode: '  ',
            unitCode: null
        );
    }

    public function testRejectsNonStringCandidateCode(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('non-empty strings');
        new ResolvedShippingAddress(
            status: VnAddressResolutionInterface::STATUS_AMBIGUOUS,
            schemeCode: 'VN_ADMIN_PRE_2025',
            unitCode: null,
            candidateCodes: ['VNAP25-A1A1A1A1A1', 1456]
        );
    }
}
