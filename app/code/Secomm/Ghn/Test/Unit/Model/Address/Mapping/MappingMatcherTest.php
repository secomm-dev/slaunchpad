<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address\Mapping;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Address\Mapping\AliasRepository;
use Secomm\Ghn\Model\Address\Mapping\MappingMatcher;
use Secomm\Ghn\Model\Address\Mapping\NameNormalizer;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * TASK-MZ2TCB / AC-B5 — deterministic matching: exact approval, curated alias, ambiguity never
 * auto-picked, unmapped (incl. cascade when parent unmapped), alias invalid outside scope.
 */
class MappingMatcherTest extends TestCase
{
    private VnAddressUnitProviderInterface&MockObject $unitProvider;

    private AliasRepository&MockObject $aliasRepository;

    /** Aliases served by the AliasRepository mock (mutated per test). */
    private array $aliases = [];

    private MappingMatcher $matcher;

    protected function setUp(): void
    {
        $this->unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $this->aliasRepository = $this->createMock(AliasRepository::class);
        $this->aliasRepository->method('getAliases')->willReturnCallback(fn (): array => $this->aliases);
        $this->matcher = new MappingMatcher($this->unitProvider, new NameNormalizer(), $this->aliasRepository);
    }

    public function testExactAmbiguousUnmappedAndCascade(): void
    {
        $this->unitProvider->method('getChildren')->willReturnCallback(
            fn (string $scheme, string $parentCode): array => match ($parentCode) {
                '' => [$this->unit('VN-01', null, 1, 'TP. Hồ Chí Minh'), $this->unit('VN-02', null, 1, 'Tỉnh Không Tồn Tại')],
                'VN-01' => [$this->unit('VNA25-A000000001', 'VN-01', 2, 'Phường Bến Nghé')],
                'VN-02' => [$this->unit('VNA25-B000000001', 'VN-02', 2, 'Phường Không Có')],
                default => [],
            }
        );

        $ghnUnits = [
            ['entity_id' => 1, 'provider_key' => '1', 'parent_id' => null, 'depth' => 1, 'name' => 'TP. Hồ Chí Minh', 'status' => 'ACTIVE'],
            ['entity_id' => 2, 'provider_key' => '11', 'parent_id' => 1, 'depth' => 2, 'name' => 'Phường Bến Nghé', 'status' => 'ACTIVE'],
            ['entity_id' => 3, 'provider_key' => '12', 'parent_id' => 1, 'depth' => 2, 'name' => 'Phường Bến Nghé Đông', 'status' => 'ACTIVE'],
            ['entity_id' => 4, 'provider_key' => '2', 'parent_id' => null, 'depth' => 1, 'name' => 'Bình Thuận (mới)', 'status' => 'DISABLED'],
        ];

        $decisions = $this->matcher->match('VN_ADMIN_2025', $ghnUnits);

        $this->assertSame(MappingMatcher::STATUS_APPROVED, $decisions['VN-01']['status']);
        $this->assertSame('1', $decisions['VN-01']['chosen_provider_key']);
        $this->assertSame('EXACT_NAME', $decisions['VN-01']['method']);

        $this->assertSame(MappingMatcher::STATUS_APPROVED, $decisions['VNA25-A000000001']['status']);
        $this->assertSame('11', $decisions['VNA25-A000000001']['chosen_provider_key']);

        $this->assertSame(MappingMatcher::STATUS_UNMAPPED, $decisions['VN-02']['status']);
        // cascade: parent unmapped → ward has zero candidates → unmapped
        $this->assertSame(MappingMatcher::STATUS_UNMAPPED, $decisions['VNA25-B000000001']['status']);
    }

    public function testAmbiguityIsNeverAutoPicked(): void
    {
        $this->unitProvider->method('getChildren')->willReturnCallback(
            fn (string $scheme, string $parentCode): array => match ($parentCode) {
                '' => [$this->unit('VN-01', null, 1, 'TP. Hồ Chí Minh')],
                'VN-01' => [$this->unit('VNA25-A000000001', 'VN-01', 2, 'Phường Bến Nghé')],
                default => [],
            }
        );

        $ghnUnits = [
            ['entity_id' => 1, 'provider_key' => '1', 'parent_id' => null, 'depth' => 1, 'name' => 'TP. Hồ Chí Minh', 'status' => 'ACTIVE'],
            ['entity_id' => 2, 'provider_key' => '11', 'parent_id' => 1, 'depth' => 2, 'name' => 'Phường Bến Nghé', 'status' => 'ACTIVE'],
            ['entity_id' => 3, 'provider_key' => '12', 'parent_id' => 1, 'depth' => 2, 'name' => 'Phường  Bến Nghé', 'status' => 'ACTIVE'],
        ];

        $decisions = $this->matcher->match('VN_ADMIN_2025', $ghnUnits);

        $this->assertSame(MappingMatcher::STATUS_AMBIGUOUS, $decisions['VNA25-A000000001']['status']);
        $this->assertSame(['11', '12'], $decisions['VNA25-A000000001']['candidates']);
        $this->assertNull($decisions['VNA25-A000000001']['chosen_provider_key']);
    }

    public function testCuratedAliasApprovesWithinScopeAndRejectsOutside(): void
    {
        $unit = $this->unit('VNA25-A000000001', 'VN-01', 2, 'Phường Không Khớp Tên');
        $this->unitProvider->method('getChildren')->willReturnCallback(
            fn (string $scheme, string $parentCode): array => match ($parentCode) {
                '' => [$this->unit('VN-01', null, 1, 'TP. Hồ Chí Minh')],
                'VN-01' => [$unit],
                default => [],
            }
        );

        $ghnUnits = [
            ['entity_id' => 1, 'provider_key' => '1', 'parent_id' => null, 'depth' => 1, 'name' => 'TP. Hồ Chí Minh', 'status' => 'ACTIVE'],
            ['entity_id' => 2, 'provider_key' => '11', 'parent_id' => 1, 'depth' => 2, 'name' => 'Phường Tên GHN', 'status' => 'ACTIVE'],
            ['entity_id' => 5, 'provider_key' => '99', 'parent_id' => 4, 'depth' => 2, 'name' => 'Phường Tỉnh Khác', 'status' => 'ACTIVE'],
        ];

        // Alias INSIDE the mapped parent scope → approved; alias OUTSIDE → alias_invalid.
        $this->aliases = ['VNA25-A000000001' => '99'];
        $decisions = $this->matcher->match('VN_ADMIN_2025', $ghnUnits);
        $this->assertSame(MappingMatcher::STATUS_ALIAS_INVALID, $decisions['VNA25-A000000001']['status']);
        $this->assertNull($decisions['VNA25-A000000001']['ghn_entity_id']);

        $this->aliases = ['VNA25-A000000001' => '11'];
        $decisions = $this->matcher->match('VN_ADMIN_2025', $ghnUnits);
        $this->assertSame(MappingMatcher::STATUS_APPROVED, $decisions['VNA25-A000000001']['status']);
        $this->assertSame('CURATED_ALIAS', $decisions['VNA25-A000000001']['method']);
        $this->assertSame('2', $decisions['VNA25-A000000001']['ghn_entity_id']);
    }

    public function testDisabledProviderUnitsAreNeverCandidates(): void
    {
        $this->unitProvider->method('getChildren')->willReturnCallback(
            fn (string $scheme, string $parentCode): array => $parentCode === ''
                ? [$this->unit('VN-01', null, 1, 'Tỉnh Duy Nhất')]
                : []
        );

        $ghnUnits = [
            ['entity_id' => 1, 'provider_key' => '1', 'parent_id' => null, 'depth' => 1, 'name' => 'Tỉnh Duy Nhất', 'status' => 'DISABLED'],
        ];

        $decisions = $this->matcher->match('VN_ADMIN_2025', $ghnUnits);

        $this->assertSame(MappingMatcher::STATUS_UNMAPPED, $decisions['VN-01']['status']);
    }

    /**
     * Minimal canonical unit fake (VnAddressUnitInterface).
     */
    private function unit(string $code, ?string $parentCode, int $level, string $nameVi): VnAddressUnitInterface
    {
        return new class ($code, $parentCode, $level, $nameVi) implements VnAddressUnitInterface {
            public function __construct(
                private readonly string $code,
                private readonly ?string $parentCode,
                private readonly int $level,
                private readonly string $nameVi
            ) {
            }

            public function getSchemeCode(): string
            {
                return 'VN_ADMIN_2025';
            }

            public function getCode(): string
            {
                return $this->code;
            }

            public function getParentCode(): ?string
            {
                return $this->parentCode;
            }

            public function getRegionCode(): string
            {
                return 'VN-01';
            }

            public function getLevel(): int
            {
                return $this->level;
            }

            public function getNameVi(): string
            {
                return $this->nameVi;
            }

            public function getNameEn(): string
            {
                return '';
            }
        };
    }
}
