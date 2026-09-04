<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Data\VnAddressUnitData;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Secomm\VietNamAddress\Model\VnAdminAddressResolver;

/**
 * TASK-J9AVGK — resolution contract: EXACT / MAPPED / AMBIGUOUS (split + reverse-merge,
 * never auto-picked) / UNMAPPED (both reasons) + unknown-scheme exception.
 */
class VnAdminAddressResolverTest extends TestCase
{
    private VnAddressUnitProviderInterface&MockObject $unitProvider;
    private MappingCandidateFinderStub $finder;

    private VnAdminAddressResolver $resolver;

    protected function setUp(): void
    {
        $this->unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $this->finder = new MappingCandidateFinderStub();
        $this->resolver = new VnAdminAddressResolver($this->unitProvider, $this->finder);
    }

    public function testExactForSameSchemeWithExistingUnit(): void
    {
        $this->arrangeUnit(VnSchemes::VN_ADMIN_2025, 'VNA25-AAA');

        $result = $this->resolver->resolve(VnSchemes::VN_ADMIN_2025, 'VNA25-AAA', VnSchemes::VN_ADMIN_2025);

        $this->assertSame(VnAddressResolutionInterface::STATUS_EXACT, $result->getStatus());
        $this->assertSame('VNA25-AAA', $result->getResolvedCode());
        $this->assertSame([], $result->getCandidateCodes());
    }

    public function testUnmappedForSameSchemeWithUnknownUnit(): void
    {
        $this->arrangeUnit(VnSchemes::VN_ADMIN_2025, 'VNA25-AAA', exists: false);

        $result = $this->resolver->resolve(VnSchemes::VN_ADMIN_2025, 'VNA25-GHOST', VnSchemes::VN_ADMIN_2025);

        $this->assertSame(VnAddressResolutionInterface::STATUS_UNMAPPED, $result->getStatus());
        $this->assertSame(VnAddressResolutionInterface::REASON_UNKNOWN_SOURCE_UNIT, $result->getReason());
    }

    public function testMappedForSingleEdge(): void
    {
        $this->arrangeUnit(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-OLD');
        $this->finder->edges = [
            ['code' => 'VNA25-NEW', 'relation_type' => 'MERGED_INTO', 'direction' => 'outgoing'],
        ];

        $result = $this->resolver->resolve(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-OLD', VnSchemes::VN_ADMIN_2025);

        $this->assertSame(VnAddressResolutionInterface::STATUS_MAPPED, $result->getStatus());
        $this->assertSame('VNA25-NEW', $result->getResolvedCode());
        $this->assertSame('MERGED_INTO', $result->getRelationType());
    }

    public function testAmbiguousForForwardSplit(): void
    {
        $this->arrangeUnit(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-OLD');
        $this->finder->edges = [
            ['code' => 'VNA25-NEW1', 'relation_type' => 'SPLIT_INTO', 'direction' => 'outgoing'],
            ['code' => 'VNA25-NEW2', 'relation_type' => 'SPLIT_INTO', 'direction' => 'outgoing'],
        ];

        $result = $this->resolver->resolve(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-OLD', VnSchemes::VN_ADMIN_2025);

        $this->assertSame(VnAddressResolutionInterface::STATUS_AMBIGUOUS, $result->getStatus());
        $this->assertNull($result->getResolvedCode());
        $this->assertSame(['VNA25-NEW1', 'VNA25-NEW2'], $result->getCandidateCodes());
    }

    public function testReverseMergeIsDeterministicForwardAndAmbiguousBackward(): void
    {
        $this->arrangeUnit(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-A');
        $this->arrangeUnit(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-B');
        $this->arrangeUnit(VnSchemes::VN_ADMIN_2025, 'VNA25-C');

        // Authored single-direction rows: old A -> new C, old B -> new C (MERGED_INTO).
        // Forward (old -> new) is deterministic from the outgoing side.
        $this->finder->edges = [['code' => 'VNA25-C', 'relation_type' => 'MERGED_INTO', 'direction' => 'outgoing']];
        $forward = $this->resolver->resolve(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-A', VnSchemes::VN_ADMIN_2025);
        $this->assertSame(VnAddressResolutionInterface::STATUS_MAPPED, $forward->getStatus());
        $this->assertSame('VNA25-C', $forward->getResolvedCode());

        // Reverse (new -> old) sees BOTH incoming edges — AMBIGUOUS with both candidates.
        $this->finder->edges = [
            ['code' => 'VNAP25-A', 'relation_type' => 'MERGED_INTO', 'direction' => 'incoming'],
            ['code' => 'VNAP25-B', 'relation_type' => 'MERGED_INTO', 'direction' => 'incoming'],
        ];
        $reverse = $this->resolver->resolve(VnSchemes::VN_ADMIN_2025, 'VNA25-C', VnSchemes::VN_ADMIN_PRE_2025);
        $this->assertSame(VnAddressResolutionInterface::STATUS_AMBIGUOUS, $reverse->getStatus());
        $this->assertSame(['VNAP25-A', 'VNAP25-B'], $reverse->getCandidateCodes());
    }

    public function testUnmappedNoMappingReasonWhenSourceUnitExists(): void
    {
        $this->arrangeUnit(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-OLD');
        $this->finder->edges = [];

        $result = $this->resolver->resolve(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-OLD', VnSchemes::VN_ADMIN_2025);

        $this->assertSame(VnAddressResolutionInterface::STATUS_UNMAPPED, $result->getStatus());
        $this->assertSame(VnAddressResolutionInterface::REASON_NO_MAPPING, $result->getReason());
    }

    public function testUnmappedUnknownSourceUnitReasonCrossScheme(): void
    {
        $this->arrangeUnit(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-OLD', exists: false);
        $this->finder->edges = [];

        $result = $this->resolver->resolve(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-GHOST', VnSchemes::VN_ADMIN_2025);

        $this->assertSame(VnAddressResolutionInterface::STATUS_UNMAPPED, $result->getStatus());
        $this->assertSame(VnAddressResolutionInterface::REASON_UNKNOWN_SOURCE_UNIT, $result->getReason());
    }

    public function testUnknownSchemeThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown Vietnam administrative scheme');
        $this->resolver->resolve('vn_current', 'X', VnSchemes::VN_ADMIN_2025);
    }

    private function arrangeUnit(string $scheme, string $code, bool $exists = true): void
    {
        $this->unitProvider->method('getUnit')->willReturnCallback(
            function (string $schemeCode, string $unitCode) use ($scheme, $code, $exists): ?VnAddressUnitData {
                if ($schemeCode === $scheme && $unitCode === $code) {
                    return $exists
                        ? new VnAddressUnitData($scheme, $code, null, '01', 2, 'X', 'X')
                        : null;
                }

                return null;
            }
        );
    }
}

/**
 * Test double for the SQL collaborator.
 */
class MappingCandidateFinderStub extends \Secomm\VietNamAddress\Model\MappingCandidateFinder
{
    /** @var array<int, array{code: string, relation_type: string, direction: string}> */
    public array $edges = [];

    public function __construct()
    {
        // no-op: parent deps unused in the stub
    }

    public function find(string $sourceScheme, string $sourceCode, string $targetScheme): array
    {
        return $this->edges;
    }
}
