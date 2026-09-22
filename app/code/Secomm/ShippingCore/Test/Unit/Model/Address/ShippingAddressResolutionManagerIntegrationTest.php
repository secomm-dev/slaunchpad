<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Model\Address\ShippingAddressResolutionContext;
use Secomm\ShippingCore\Model\Address\ShippingAddressResolutionManager;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Data\VnAddressUnitData;
use Secomm\VietNamAddress\Model\MappingCandidateFinder;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Secomm\VietNamAddress\Model\VnAdminAddressResolver;

/**
 * TASK-5XDG1P — contract-fit integration (unit-level, no DB): the ShippingCore manager wired
 * to the REAL Secomm_VietNamAddress resolver (only the reference layer is mocked) proves the
 * orchestration consumes the actual VnAdminAddressResolver semantics — same-scheme EXACT,
 * cross-scheme MAPPED/AMBIGUOUS(sorted)/UNMAPPED — without reimplementing any of them.
 */
class ShippingAddressResolutionManagerIntegrationTest extends TestCase
{
    private const SOURCE_UNIT = 'VNAP25-OLD111111';
    private const PRE = VnSchemes::VN_ADMIN_PRE_2025;
    private const CURRENT = VnSchemes::VN_ADMIN_2025;

    public function testSameSchemeResolvesExactThroughTheRealResolver(): void
    {
        // Same-scheme EXACT needs NO mapping edge — the resolver's unit-existence check suffices.
        $manager = $this->manager(self::PRE, self::SOURCE_UNIT, []);

        $result = $manager->resolve(
            $this->context(self::PRE, self::SOURCE_UNIT, self::PRE),
            $this->capability(self::PRE)
        );

        self::assertSame(VnAddressResolutionInterface::STATUS_EXACT, $result->getStatus());
        self::assertSame(self::PRE, $result->getSchemeCode());
        self::assertSame(self::SOURCE_UNIT, $result->getUnitCode());
        self::assertSame([], $result->getCandidateCodes());
        self::assertTrue($result->isResolved());
    }

    public function testCrossSchemeSingleEdgeResolvesMappedThroughTheRealResolver(): void
    {
        $manager = $this->manager(self::PRE, self::SOURCE_UNIT, [
            ['code' => 'VNA25-NEW1111111', 'relation_type' => 'MERGED_INTO', 'direction' => 'outgoing'],
        ]);

        $result = $manager->resolve(
            $this->context(self::PRE, self::SOURCE_UNIT, self::CURRENT),
            $this->capability(self::CURRENT)
        );

        self::assertSame(VnAddressResolutionInterface::STATUS_MAPPED, $result->getStatus());
        self::assertSame(self::CURRENT, $result->getSchemeCode());
        self::assertSame('VNA25-NEW1111111', $result->getUnitCode());
        self::assertSame([], $result->getCandidateCodes());
        self::assertTrue($result->isResolved());
    }

    public function testCrossSchemeReverseMergeResolvesAmbiguousSortedAndNeverPicks(): void
    {
        // Reverse of a merge (A→C, B→C; resolving C backwards): BOTH candidates surface.
        $manager = $this->manager(self::CURRENT, 'VNA25-MERGED1', [
            ['code' => 'VNAP25-B2B2B2B2B2', 'relation_type' => 'MERGED_INTO', 'direction' => 'incoming'],
            ['code' => 'VNAP25-A1A1A1A1A1', 'relation_type' => 'MERGED_INTO', 'direction' => 'incoming'],
        ]);

        $result = $manager->resolve(
            $this->context(self::CURRENT, 'VNA25-MERGED1', self::PRE),
            $this->capability(self::PRE)
        );

        self::assertSame(VnAddressResolutionInterface::STATUS_AMBIGUOUS, $result->getStatus());
        self::assertNull($result->getUnitCode());
        // Deterministic resolver sort preserved 1-1 into the shipping result — never re-ordered,
        // never picked from.
        self::assertSame(['VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2'], $result->getCandidateCodes());
        self::assertFalse($result->isResolved());
    }

    public function testCrossSchemeWithoutEdgesResolvesUnmappedThroughTheRealResolver(): void
    {
        $manager = $this->manager(self::PRE, self::SOURCE_UNIT, []);

        $result = $manager->resolve(
            $this->context(self::PRE, self::SOURCE_UNIT, self::CURRENT),
            $this->capability(self::CURRENT)
        );

        self::assertSame(VnAddressResolutionInterface::STATUS_UNMAPPED, $result->getStatus());
        self::assertNull($result->getUnitCode());
        self::assertSame([], $result->getCandidateCodes());
        self::assertFalse($result->isResolved());
    }

    public function testSameSchemeUnknownUnitResolvesUnmappedNotThrowing(): void
    {
        // Ghost unit code: the reference layer holds NO such unit (getUnit returns null for it).
        $manager = $this->manager(self::CURRENT, self::SOURCE_UNIT, []);

        $result = $manager->resolve(
            $this->context(self::CURRENT, 'VNA25-GHOST111', self::CURRENT),
            $this->capability(self::CURRENT)
        );

        self::assertSame(VnAddressResolutionInterface::STATUS_UNMAPPED, $result->getStatus());
        self::assertNull($result->getUnitCode());
        self::assertFalse($result->isResolved());
    }

    /**
     * Manager over the REAL VnAdminAddressResolver; only the reference layer is mocked.
     *
     * @param string $unitScheme scheme of the single EXISTING unit in the reference layer
     * @param string $existingUnitCode the code that exists; any other lookup returns null
     * @param array<int, array{code: string, relation_type: string, direction: string}> $edges
     */
    private function manager(
        string $unitScheme,
        string $existingUnitCode,
        array $edges
    ): ShippingAddressResolutionManager {
        $unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $unitProvider->method('getUnit')
            ->willReturnCallback(
                fn (string $schemeCode, string $code): ?VnAddressUnitData =>
                    $schemeCode === $unitScheme && $code === $existingUnitCode
                        ? $this->unit($unitScheme, $existingUnitCode)
                        : null
            );

        $candidateFinder = $this->createMock(MappingCandidateFinder::class);
        $candidateFinder->method('find')->willReturn($edges);

        return new ShippingAddressResolutionManager(new VnAdminAddressResolver($unitProvider, $candidateFinder));
    }

    private function unit(string $scheme, string $code): VnAddressUnitData
    {
        return new VnAddressUnitData($scheme, $code, 'VNAP25-DISTRICT0', 'VN-01', 3, 'Phường Test', 'Test Ward');
    }

    private function context(string $sourceScheme, string $sourceUnitCode, string $targetScheme): ShippingAddressResolutionContext
    {
        return new ShippingAddressResolutionContext(
            countryId: 'VN',
            sourceScheme: $sourceScheme,
            sourceUnitCode: $sourceUnitCode,
            targetScheme: $targetScheme
        );
    }

    private function capability(string $requiredScheme): CarrierAddressCapabilityInterface
    {
        return new readonly class ($requiredScheme) implements CarrierAddressCapabilityInterface {
            public function __construct(private string $scheme)
            {
            }

            public function getRequiredScheme(): string
            {
                return $this->scheme;
            }

            public function supportsTextualFallback(): bool
            {
                throw new \LogicException('Local resolution must not consult supportsTextualFallback().');
            }
        };
    }
}
