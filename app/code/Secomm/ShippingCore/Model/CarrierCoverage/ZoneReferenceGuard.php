<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\CarrierCoverage;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 (TL conditional approval + final verification) — admin-surface
 * guard for zone mutations, over the PERSISTED per-scope reference index:
 * a zone referenced by a registered coverage target's config in ANY supported scope
 * (DEFAULT / WEBSITE / STORE) cannot be deleted (BLOCK), and disabling one surfaces an
 * explicit impact warning naming target + scope. Reads the persisted rows via
 * CarrierZoneIndex — never ScopeConfig effective resolution, never runtime diagnostics.
 */
final class ZoneReferenceGuard
{
    private CarrierZoneIndex $zoneIndex;

    public function __construct(CarrierZoneIndex $zoneIndex)
    {
        $this->zoneIndex = $zoneIndex;
    }

    /**
     * Every persisted reference to the zone code across all supported scopes.
     *
     * @return array<int, array{carrier: string, carrier_label: string, scope: string, scope_id: int, scope_label: string}>
     */
    public function findReferences(string $zoneCode): array
    {
        return $this->zoneIndex->findReferences($zoneCode);
    }

    /**
     * Distinct carrier codes referencing the zone in ANY supported scope (derived from
     * findReferences — the guard's single index primitive).
     *
     * @return string[]
     */
    public function referencingCarriers(string $zoneCode): array
    {
        $carriers = [];
        foreach ($this->findReferences($zoneCode) as $reference) {
            if (!in_array($reference['carrier'], $carriers, true)) {
                $carriers[] = $reference['carrier'];
            }
        }

        return $carriers;
    }

    /**
     * Admin-safe one-line description: "GHN (Giao Hàng Nhanh) (Default), GHN (Giao Hàng
     * Nhanh) (Website: vietnam_store)". '' when unreferenced.
     */
    public function describeReferences(string $zoneCode): string
    {
        $parts = [];
        foreach ($this->findReferences($zoneCode) as $reference) {
            $parts[] = sprintf('%s (%s)', $reference['carrier_label'], $reference['scope_label']);
        }

        return implode(', ', $parts);
    }

    /**
     * "CODE (references)" description for mass-action messages; null when unreferenced.
     */
    public function describeZoneReference(string $zoneCode): ?string
    {
        $references = $this->describeReferences($zoneCode);

        return $references === '' ? null : sprintf('%s (%s)', strtoupper(trim($zoneCode)), $references);
    }
}
