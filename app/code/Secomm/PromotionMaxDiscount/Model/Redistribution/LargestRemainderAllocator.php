<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Model\Redistribution;

/**
 * Largest-remainder allocation (SPEC-FEAT-JKZM68 §8 / DEC-FEATJKZM68-001 §4).
 *
 * Pure functions only — no Magento dependencies, no application state: currency
 * precision is injected by the caller so the allocator is unit-testable in
 * isolation and runs identically for the base and the display chain.
 *
 * Invariant: whenever Σ targets == cap and cap is representable at the given
 * precision, Σ results == cap exactly, and each result stays on the currency
 * grid. Ties between equal fractional parts are broken by ascending key, so the
 * allocation is deterministic regardless of iteration order.
 */
final class LargestRemainderAllocator
{
    private const EPSILON = 1e-9;

    /**
     * Allocate $cap across $targets.
     *
     * Targets are expected to be currency-grid-aligned amounts produced by the
     * native SalesRule pipeline (deltaRoundingFix rounds both chains to the
     * currency precision). For factor < 1 the result can therefore never exceed
     * the input — floor(target) < target < native, and one remainder unit tops
     * it back to at most the native amount (scale-down-only guard, AC-7).
     *
     * @param array<int, float> $targets item key => positive target amount
     * @param float $cap total to allocate across the targets
     * @param int $precision currency decimal places (0 for VND, 2 for USD)
     * @return array<int, float> item key => allocated amount, keyed like $targets
     */
    public function allocate(array $targets, float $cap, int $precision): array
    {
        if ($targets === []) {
            return [];
        }

        $scale = 10 ** $precision;
        $capUnits = (int) round($cap * $scale);

        $floors = [];
        $fractions = [];
        $floorSum = 0;
        foreach ($targets as $key => $target) {
            $scaled = $target * $scale;
            $floor = (int) floor($scaled);
            $floors[$key] = $floor;
            $fractions[$key] = $scaled - $floor;
            $floorSum += $floor;
        }

        // Σ floor loses less than one unit per item, so at most one unit per item
        // is redistributed: hand whole units to the largest fractional parts
        // first, ties broken by ascending key.
        $remaining = $capUnits - $floorSum;
        if ($remaining > 0) {
            $keys = array_keys($floors);
            usort($keys, function ($a, $b) use ($fractions) {
                return $this->compareByFractionThenKey($a, $b, $fractions);
            });
            $count = min($remaining, count($keys));
            for ($i = 0; $i < $count; $i++) {
                $floors[$keys[$i]]++;
            }
        }

        $result = [];
        foreach ($floors as $key => $units) {
            $result[$key] = $units / $scale;
        }
        return $result;
    }

    /**
     * @param int $a
     * @param int $b
     * @param array<int, float> $fractions
     * @return int
     */
    private function compareByFractionThenKey(int $a, int $b, array $fractions): int
    {
        $diff = $fractions[$b] - $fractions[$a];
        if (abs($diff) > self::EPSILON) {
            return $diff > 0 ? 1 : -1;
        }
        return $a <=> $b;
    }
}
