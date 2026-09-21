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
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\Ghn\Model\Address\Mapping\MappingAuditor;
use Secomm\Ghn\Model\Address\Mapping\MappingMatcher;
use Secomm\Ghn\Model\ResourceModel\AddressMapping;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;

/**
 * TASK-MZ2TCB / AC-B3 — audit counts: mapped/unmapped/ambiguous/invalid/stale/disabled +
 * coverage % per level, computed live from DB rows × deterministic re-match.
 */
class MappingAuditorTest extends TestCase
{
    private MappingMatcher&MockObject $matcher;

    private AddressUnit&MockObject $unitResource;

    private AddressMapping&MockObject $mappingResource;

    private MappingAuditor $auditor;

    /** GHN unit rows served by the AddressUnit mock ('[]' JSON = empty → fail loud). */
    private array $ghnUnits = [
        ['entity_id' => 1, 'provider_key' => '1', 'status' => 'ACTIVE'],
        ['entity_id' => 2, 'provider_key' => '11', 'status' => 'ACTIVE'],
        ['entity_id' => 3, 'provider_key' => '12', 'status' => 'DISABLED'],
    ];

    protected function setUp(): void
    {
        $this->matcher = $this->createMock(MappingMatcher::class);
        $this->unitResource = $this->createMock(AddressUnit::class);
        $this->mappingResource = $this->createMock(AddressMapping::class);
        $canonical = $this->createMock(VnAddressUnitProviderInterface::class);
        // Canonical units VN-01..VN-03 exist; anything else dangles.
        $canonical->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => preg_match('/^VN-(01|02|03)$/', $code)
                ? $this->createMock(VnAddressUnitInterface::class)
                : null
        );
        $this->auditor = new MappingAuditor($this->matcher, $this->unitResource, $this->mappingResource, $canonical);

        $this->unitResource->method('fetchByScheme')->willReturnCallback(fn (): array => $this->ghnUnits);
    }

    public function testFullAuditCountsAndCoverage(): void
    {
        // Decisions: VN-01 approved (would map), VN-02 ambiguous, VN-03 unmapped.
        $this->matcher->method('match')->willReturn([
            'VN-01' => ['level' => 1, 'name' => 'A', 'status' => MappingMatcher::STATUS_APPROVED, 'candidates' => ['1'], 'chosen_provider_key' => '1', 'ghn_entity_id' => 1, 'method' => 'EXACT_NAME'],
            'VN-02' => ['level' => 2, 'name' => 'B', 'status' => MappingMatcher::STATUS_AMBIGUOUS, 'candidates' => ['11', '12'], 'chosen_provider_key' => null, 'ghn_entity_id' => null, 'method' => null],
            'VN-03' => ['level' => 2, 'name' => 'C', 'status' => MappingMatcher::STATUS_UNMAPPED, 'candidates' => [], 'chosen_provider_key' => null, 'ghn_entity_id' => null, 'method' => null],
        ]);

        // Stored: VN-01 approved → unit 1 (ACTIVE); VN-04 stale (unknown canonical) → unit 3 (DISABLED);
        // VN-05 stored → unit missing (defensive invalid).
        $this->mappingResource->method('fetchByScheme')->willReturn([
            'VN-01' => ['secomm_unit_code' => 'VN-01', 'ghn_address_unit_id' => 1, 'mapping_status' => 'APPROVED'],
            'VN-04' => ['secomm_unit_code' => 'VN-04', 'ghn_address_unit_id' => 3, 'mapping_status' => 'APPROVED'],
            'VN-05' => ['secomm_unit_code' => 'VN-05', 'ghn_address_unit_id' => 999, 'mapping_status' => 'APPROVED'],
        ]);

        $report = $this->auditor->audit('VN_ADMIN_PRE_2025');

        $this->assertSame(3, $report['total_canonical']);
        $this->assertSame(1, $report['mapped']);
        $this->assertSame(1, $report['unmapped']);
        $this->assertSame(1, $report['ambiguous']);
        $this->assertSame(1, $report['invalid']);
        $this->assertSame(2, $report['stale']); // VN-04 + VN-05 unknown to the matcher
        $this->assertSame(1, $report['dangling']); // VN-04's canonical unit no longer exists
        $this->assertSame(1, $report['disabled_provider_unit']);
        $this->assertSame(33.33, $report['coverage_percent']);
        $this->assertFalse($report['production_ready']);
        $this->assertSame(['VN-03'], $report['unmapped_codes']);
        $this->assertArrayHasKey('VN-02', $report['ambiguous_codes']);
        $this->assertSame(['VN-04'], $report['disabled_provider_codes']);
        $this->assertSame(['VN-05'], $report['invalid_codes']);
        $this->assertSame(['VN-04'], $report['dangling_codes']);
    }

    public function testFullyMappedDatasetIsProductionReady(): void
    {
        $this->matcher->method('match')->willReturn([
            'VN-01' => ['level' => 1, 'status' => MappingMatcher::STATUS_APPROVED, 'candidates' => ['1'], 'chosen_provider_key' => '1', 'ghn_entity_id' => 1, 'method' => 'EXACT_NAME'],
            'VN-02' => ['level' => 2, 'status' => MappingMatcher::STATUS_APPROVED, 'candidates' => ['11'], 'chosen_provider_key' => '11', 'ghn_entity_id' => 2, 'method' => 'EXACT_NAME'],
        ]);
        $this->mappingResource->method('fetchByScheme')->willReturn([
            'VN-01' => ['secomm_unit_code' => 'VN-01', 'ghn_address_unit_id' => 1, 'mapping_status' => 'APPROVED'],
            'VN-02' => ['secomm_unit_code' => 'VN-02', 'ghn_address_unit_id' => 2, 'mapping_status' => 'APPROVED'],
        ]);

        $report = $this->auditor->audit('VN_ADMIN_PRE_2025');

        $this->assertSame(100.0, $report['coverage_percent']);
        $this->assertTrue($report['production_ready']);
        $this->assertSame(0, $report['unmapped']);
        $this->assertSame(0, $report['dangling']);
        $this->assertSame(0, $report['invalid']);
    }

    /**
     * Architecture v3 §38: stale > 0 must NEVER coexist with production_ready = true — the
     * gate exists precisely to catch stored mappings the authoritative dataset no longer knows.
     */
    public function testStaleRowsBlockProductionReadyEvenWhenCoverageIsComplete(): void
    {
        $this->matcher->method('match')->willReturn([
            'VN-01' => ['level' => 1, 'status' => MappingMatcher::STATUS_APPROVED, 'candidates' => ['1'], 'chosen_provider_key' => '1', 'ghn_entity_id' => 1, 'method' => 'EXACT_NAME'],
            'VN-02' => ['level' => 2, 'status' => MappingMatcher::STATUS_APPROVED, 'candidates' => ['11'], 'chosen_provider_key' => '11', 'ghn_entity_id' => 2, 'method' => 'EXACT_NAME'],
        ]);
        $this->mappingResource->method('fetchByScheme')->willReturn([
            'VN-01' => ['secomm_unit_code' => 'VN-01', 'ghn_address_unit_id' => 1, 'mapping_status' => 'APPROVED'],
            'VN-02' => ['secomm_unit_code' => 'VN-02', 'ghn_address_unit_id' => 2, 'mapping_status' => 'APPROVED'],
            // Stale: stored mapping for a canonical unit the matcher no longer knows (VN-03
            // still EXISTS canonically — dangling must stay 0; only staleness fails the gate).
            'VN-03' => ['secomm_unit_code' => 'VN-03', 'ghn_address_unit_id' => 1, 'mapping_status' => 'APPROVED'],
        ]);

        $report = $this->auditor->audit('VN_ADMIN_PRE_2025');

        $this->assertSame(2, $report['mapped']);
        $this->assertSame(100.0, $report['coverage_percent']);
        $this->assertSame(1, $report['stale']);
        $this->assertSame(0, $report['unmapped']);
        $this->assertSame(0, $report['dangling']);
        $this->assertSame(0, $report['invalid']);
        $this->assertFalse($report['production_ready'], 'stale > 0 must block production_ready');
    }

    public function testEmptyGhnMasterDataFailsLoud(): void
    {
        $this->ghnUnits = [];

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->auditor->audit('VN_ADMIN_2025');
    }
}
