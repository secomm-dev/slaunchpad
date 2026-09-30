<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\CarrierCoverage;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierZoneIndex;
use Secomm\ShippingCore\Model\CarrierCoverage\ZoneReferenceGuard;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 (final TL verification) — the guard surfaces persisted
 * per-scope reference metadata in admin-safe messages: "GHN (… ) (Default)",
 * "GHN (…) (Website: vietnam_store)". Empty description = unreferenced everywhere.
 */
class ZoneReferenceGuardTest extends TestCase
{
    private function guard(callable $references): ZoneReferenceGuard
    {
        $index = $this->createMock(CarrierZoneIndex::class);
        $index->method('findReferences')->willReturnCallback($references);

        // Labels arrive from the index rows — the guard itself has no registry dependency
        // (TASK-WY6WP5 removed the unused CarrierRegistry constructor argument).
        return new ZoneReferenceGuard($index);
    }

    public function testDescribeReferencesNamesCarrierAndScope(): void
    {
        $guard = $this->guard(static fn (string $code): array => $code === 'DN_INNER' ? [
            ['carrier' => 'secomm_ghn', 'carrier_label' => 'GHN (Giao Hàng Nhanh)', 'scope' => 'websites', 'scope_id' => 1, 'scope_label' => 'Website: Vietnam Store'],
        ] : []);

        $this->assertSame(
            'GHN (Giao Hàng Nhanh) (Website: Vietnam Store)',
            $guard->describeReferences('DN_INNER')
        );
    }

    public function testDescribeReferencesJoinsLayeredEntries(): void
    {
        $guard = $this->guard(static fn (string $code): array => [
            ['carrier' => 'secomm_ghn', 'carrier_label' => 'GHN (Giao Hàng Nhanh)', 'scope' => 'default', 'scope_id' => 0, 'scope_label' => 'Default'],
            ['carrier' => 'secomm_ghn', 'carrier_label' => 'GHN (Giao Hàng Nhanh)', 'scope' => 'stores', 'scope_id' => 2, 'scope_label' => 'Store View: English'],
        ]);

        $this->assertSame(
            'GHN (Giao Hàng Nhanh) (Default), GHN (Giao Hàng Nhanh) (Store View: English)',
            $guard->describeReferences('HCM_INNER')
        );
    }

    public function testUnreferencedDescribesEmpty(): void
    {
        $guard = $this->guard(static fn (string $code): array => []);

        $this->assertSame('', $guard->describeReferences('NOWHERE'));
        $this->assertNull($guard->describeZoneReference('NOWHERE'));
        $this->assertSame([], $guard->referencingCarriers('NOWHERE'));
    }

    public function testDescribeZoneReferenceUppercasesCode(): void
    {
        $guard = $this->guard(static fn (string $code): array => [
            ['carrier' => 'secomm_ghn', 'carrier_label' => 'GHN', 'scope' => 'default', 'scope_id' => 0, 'scope_label' => 'Default'],
        ]);

        $this->assertSame('HCM_INNER (GHN (Default))', $guard->describeZoneReference('hcm_inner'));
    }

    public function testFindReferencesPassesThroughIndex(): void
    {
        $reference = ['carrier' => 'secomm_ghn', 'carrier_label' => 'GHN', 'scope' => 'default', 'scope_id' => 0, 'scope_label' => 'Default'];
        $guard = $this->guard(static fn (string $code): array => [$reference]);

        $this->assertSame([$reference], $guard->findReferences('HCM_INNER'));
        $this->assertSame(['secomm_ghn'], $guard->referencingCarriers('HCM_INNER'));
    }
}
