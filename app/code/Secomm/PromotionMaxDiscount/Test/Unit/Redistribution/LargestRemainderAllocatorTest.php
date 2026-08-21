<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Test\Unit\Redistribution;

use PHPUnit\Framework\TestCase;
use Secomm\PromotionMaxDiscount\Model\Redistribution\LargestRemainderAllocator;

/**
 * AC-4 — LRM invariants (SPEC-FEAT-JKZM68 §8): Σ allocated == cap for generated
 * case sets at precision 0 and 2; deterministic tie-break by ascending key.
 */
class LargestRemainderAllocatorTest extends TestCase
{
    private const EPSILON = 1e-9;

    /**
     * @var LargestRemainderAllocator
     */
    private LargestRemainderAllocator $allocator;

    protected function setUp(): void
    {
        $this->allocator = new LargestRemainderAllocator();
    }

    public function testEmptyTargetsYieldEmptyResult(): void
    {
        $this->assertSame([], $this->allocator->allocate([], 100.0, 0));
    }

    public function testSingleTargetGetsWholeCap(): void
    {
        $result = $this->allocator->allocate([7 => 50000.0], 50000.0, 0);
        $this->assertEqualsWithDelta(50000.0, $result[7], self::EPSILON);
    }

    public function testExactSplitNeedsNoRemainder(): void
    {
        // already-scaled targets (collector computes natives x cap/N):
        // contributions 40k/60k scaled by 0.5 -> 20k/30k, floors sum to cap
        $result = $this->allocator->allocate([1 => 20000.0, 2 => 30000.0], 50000.0, 0);
        $this->assertEqualsWithDelta(20000.0, $result[1], self::EPSILON);
        $this->assertEqualsWithDelta(30000.0, $result[2], self::EPSILON);
    }

    public function testRemainderGoesToLargestFraction(): void
    {
        // targets 10.33 x3, cap 31 (precision 0): floors 10x3 = 30, remainder 1
        // unit -> all fractions equal (0.33) -> tie-break key asc -> item 1.
        $result = $this->allocator->allocate([1 => 10.33, 2 => 10.33, 3 => 10.33], 31.0, 0);
        $this->assertEqualsWithDelta(11.0, $result[1], self::EPSILON);
        $this->assertEqualsWithDelta(10.0, $result[2], self::EPSILON);
        $this->assertEqualsWithDelta(10.0, $result[3], self::EPSILON);
        $this->assertEqualsWithDelta(31.0, array_sum($result), self::EPSILON);
    }

    public function testDecimalPrecisionDistributesCents(): void
    {
        // precision 2: 0.33/0.33/0.33 floors, cap 1.00 -> remainder 0.01 to the
        // item whose target fraction is largest (0.34-scaled target).
        $result = $this->allocator->allocate([5 => 0.333, 6 => 0.333, 7 => 0.334], 1.0, 2);
        $this->assertEqualsWithDelta(0.33, $result[5], self::EPSILON);
        $this->assertEqualsWithDelta(0.33, $result[6], self::EPSILON);
        $this->assertEqualsWithDelta(0.34, $result[7], self::EPSILON);
        $this->assertEqualsWithDelta(1.0, array_sum($result), self::EPSILON);
    }

    public function testTieBreakIsDeterministicAcrossCalls(): void
    {
        $targets = [9 => 3.3, 4 => 3.3, 2 => 3.3];
        $first = $this->allocator->allocate($targets, 10.0, 0);
        $second = $this->allocator->allocate($targets, 10.0, 0);
        // floors 3x3 = 9, cap 10 -> one remainder unit; equal fractions:
        // the unit goes to key 2 (ascending), identical every run
        $this->assertEqualsWithDelta(4.0, $first[2], self::EPSILON);
        $this->assertEqualsWithDelta(3.0, $first[4], self::EPSILON);
        $this->assertEqualsWithDelta(3.0, $first[9], self::EPSILON);
        $this->assertEqualsWithDelta(10.0, array_sum($first), self::EPSILON);
        $this->assertSame($first, $second);
    }

    /**
     * Generated case set (AC-4): n items with grid-aligned native contributions,
     * targets scaled by cap/N exactly like the collector does — invariants:
     * Σ allocated == cap, no result above the native amount, results on-grid.
     *
     * @dataProvider generatedCaseProvider
     * @param array<int, float> $targets
     * @param array<int, float> $natives
     * @param float $cap
     * @param int $precision
     */
    public function testSumEqualsCapForGeneratedCases(
        array $targets,
        array $natives,
        float $cap,
        int $precision
    ): void {
        $result = $this->allocator->allocate($targets, $cap, $precision);

        $this->assertEqualsWithDelta($cap, array_sum($result), self::EPSILON);
        foreach ($result as $key => $amount) {
            $this->assertGreaterThanOrEqual(0.0, $amount);
            // scale-down-only (AC-7): a result never exceeds the native amount
            $this->assertLessThanOrEqual($natives[$key] + self::EPSILON, $amount);
            // results sit on the currency grid
            $this->assertEqualsWithDelta($amount, round($amount, $precision), self::EPSILON);
        }
    }

    /**
     * @return array<string, array<int, array<int, float>|float|int>>
     */
    public function generatedCaseProvider(): array
    {
        $cases = [];
        foreach ([0, 2] as $precision) {
            for ($n = 2; $n <= 12; $n++) {
                $natives = [];
                for ($i = 1; $i <= $n; $i++) {
                    $natives[$i] = ($i * 7 + $n * 3) * ($precision === 0 ? 1000.0 : 0.01);
                }
                $total = array_sum($natives);
                $cap = round($total * ($n % 3 === 0 ? 0.5 : 0.73), $precision);
                $factor = $cap / $total;
                $targets = array_map(static fn(float $v): float => $v * $factor, $natives);
                $cases[sprintf('p%d-n%d', $precision, $n)] = [$targets, $natives, $cap, $precision];
            }
        }
        return $cases;
    }
}
