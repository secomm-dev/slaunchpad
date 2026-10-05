<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Rate;

use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Rate\EstimatedPackage;
use Secomm\Ghn\Model\Rate\GhnPackageLimits;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimate;

/**
 * TASK-FXFMJ0 (DEC-TASKFXFMJ0-001) + TASK-WNQCRW (DEC-TASKWNQCRW-001, 2026-10-01) —
 * service-type selection grid at the VO level: RATE type depends ONLY on TOTAL quote weight
 * (<20000g → 2, >=20000g → 5; package/item counts NEVER consulted). The per-package weight
 * gate is a separate, merchant-tunable display filter (default 50000g — strictly `>`).
 * Dimension gating unchanged.
 */
class QuoteParcelEstimateTest extends TestCase
{
    private function unit(float $weightGrams, int $id = 10): EstimatedPackage
    {
        return new EstimatedPackage($id, 'UNIT-SKU', $weightGrams, 'quote_item_weight');
    }

    // ---------- service-type selection grid (TASK-WNQCRW §3/§7 — total weight ONLY;
    // package/item counts never influence the type) ----------

    public function testSinglePackageUnderTwentyKgSelectsTypeTwo(): void
    {
        $estimate = new QuoteParcelEstimate([$this->unit(10000.0)]);

        $this->assertSame(2, $estimate->getServiceTypeId());
    }

    public function testSinglePackageJustUnderTheBoundarySelectsTypeTwo(): void
    {
        $estimate = new QuoteParcelEstimate([$this->unit(19999.0)]);

        $this->assertSame(2, $estimate->getServiceTypeId());
    }

    public function testSinglePackageExactlyAtTwentyKgSelectsTypeFive(): void
    {
        // DOCUMENTED boundary: 2 = "under 20 kg" — exactly 20000g is "20 kg or more" → type 5.
        $estimate = new QuoteParcelEstimate([$this->unit(20000.0)]);

        $this->assertSame(5, $estimate->getServiceTypeId());
    }

    public function testHeavySinglePackagesUpToTheDefaultWeightCapSelectTypeFive(): void
    {
        // TASK-WNQCRW: 35kg / 50kg select type 5 AND pass the default 50000g weight gate
        // (strictly `>` — a unit AT the cap still quotes; boundary pinned in
        // GhnRateCalculatorTest::testSingleUnitExactlyFiftyKgStillQuotes).
        foreach ([35000.0, 50000.0] as $weight) {
            $estimate = new QuoteParcelEstimate([$this->unit($weight)]);

            $this->assertSame(5, $estimate->getServiceTypeId(), "weight {$weight}g must select type 5");
            $this->assertNull($estimate->findHardLimitViolation(), "weight {$weight}g must not be rejected");
        }
    }

    public function testSingleUnitOverDefaultWeightCapIsRejectedWithWeightReason(): void
    {
        // TASK-WNQCRW §4: one unit over the default gate hides GHN before any fee call
        // (weight reason; the service-type classification itself is orthogonal).
        foreach ([50001.0, 60000.0] as $weight) {
            $estimate = new QuoteParcelEstimate([$this->unit($weight)]);

            $this->assertSame(5, $estimate->getServiceTypeId(), 'type selection is orthogonal to the gate');
            $violation = $estimate->findHardLimitViolation();
            $this->assertNotNull($violation, "weight {$weight}g must violate the default cap");
            $this->assertSame(GhnPackageLimits::REASON_PACKAGE_WEIGHT_LIMIT_EXCEEDED, $violation[0]);
            $this->assertSame('weight', $violation[2]);
            $this->assertSame((int) $weight, $violation[3]);
            $this->assertSame(GhnPackageLimits::MAX_WEIGHT_G, $violation[4]);
        }
    }

    public function testWeightViolationTakesPrecedenceOverDimensionViolation(): void
    {
        // Weight is checked FIRST inside the package loop — deterministic first-violation.
        $estimate = new QuoteParcelEstimate([
            new EstimatedPackage(10, 'UNIT-SKU', 50001.0, 'quote_item_weight', 999, 999, 999),
        ]);

        $violation = $estimate->findHardLimitViolation();
        $this->assertSame(GhnPackageLimits::REASON_PACKAGE_WEIGHT_LIMIT_EXCEEDED, $violation[0]);
        $this->assertSame('weight', $violation[2]);
    }

    public function testRaisedWeightLimitLetsHeavySinglePackagesQuoteAgain(): void
    {
        // TASK-WNQCRW — the merchant-tunable path preserves the FXFMJ0 sandbox evidence
        // (the fee API has no 50kg bound: single 60kg quotes HTTP 200): a merchant raising
        // max_package_weight_g re-enables >50kg quoting.
        $raised = new QuoteParcelEstimate([$this->unit(60000.0)], 200, 200, 200, 100000);
        $this->assertSame(5, $raised->getServiceTypeId());
        $this->assertNull($raised->findHardLimitViolation());

        // Strictly `>`: a unit exactly AT the raised limit still passes.
        $boundary = new QuoteParcelEstimate([$this->unit(100000.0)], 200, 200, 200, 100000);
        $this->assertNull($boundary->findHardLimitViolation());
    }

    public function testMultiPackageTypeFollowsTotalWeightNotPackageCount(): void
    {
        // TASK-WNQCRW §3: the former "multi-parcel → type 5" dependency is FORBIDDEN —
        // type follows the total only.
        $lightMulti = new QuoteParcelEstimate([$this->unit(5000.0, 11), $this->unit(5000.0, 12)]);
        $heavyMulti = new QuoteParcelEstimate([$this->unit(30000.0, 11), $this->unit(30000.0, 12)]);
        $overSeventy = new QuoteParcelEstimate([$this->unit(35000.0, 11), $this->unit(35000.0, 12)]);

        $this->assertSame(2, $lightMulti->getServiceTypeId(), '10kg total is type 2 regardless of 2 packages');
        $this->assertSame(5, $heavyMulti->getServiceTypeId(), '60kg total is type 5');
        $this->assertSame(5, $overSeventy->getServiceTypeId(), '70kg total is type 5');
        $this->assertNull($lightMulti->findHardLimitViolation());
        $this->assertNull($overSeventy->findHardLimitViolation(), 'aggregate weight is never capped when every unit passes');
    }

    /**
     * TASK-WNQCRW §14 — item-count independence: same total weight + different item count
     * → same service_type_id.
     */
    public function testItemCountDoesNotInfluenceServiceType(): void
    {
        $lightCarts = [
            [$this->unit(15000.0, 11)],
            [$this->unit(5000.0, 11), $this->unit(5000.0, 12), $this->unit(5000.0, 13)],
            array_map(fn (int $i): EstimatedPackage => $this->unit(1500.0, 20 + $i), range(1, 10)),
        ];
        foreach ($lightCarts as $index => $packages) {
            $estimate = new QuoteParcelEstimate($packages);
            $this->assertSame(2, $estimate->getServiceTypeId(), "light cart #{$index} must be type 2");
        }

        $heavyCarts = [
            [$this->unit(30000.0, 11)],
            [$this->unit(10000.0, 11), $this->unit(10000.0, 12), $this->unit(10000.0, 13)],
        ];
        foreach ($heavyCarts as $index => $packages) {
            $estimate = new QuoteParcelEstimate($packages);
            $this->assertSame(5, $estimate->getServiceTypeId(), "heavy cart #{$index} must be type 5");
        }
    }

    public function testMissingDimensionsNeverProduceViolations(): void
    {
        // brief §13: a null-dims estimate must pass the dimension gate untouched.
        $estimate = new QuoteParcelEstimate([$this->unit(30000.0)]);

        $this->assertNull($estimate->findHardLimitViolation());
    }

    /**
     * TASK-ZS2B41 (rev. per-dimension) — each dimension carries its OWN configured limit: a
     * 120×120×120 unit violates ONLY the width when width is the tight one (100 vs 150).
     */
    public function testWidthOnlyTightLimitViolatesWidthNotLength(): void
    {
        $estimate = new QuoteParcelEstimate(
            [new EstimatedPackage(10, 'UNIT-SKU', 1000.0, 'quote_item_weight', 120, 120, 120)],
            150,
            100,
            150
        );

        $violation = $estimate->findHardLimitViolation();
        $this->assertNotNull($violation);
        $this->assertSame('GHN_PACKAGE_WIDTH_LIMIT_EXCEEDED', $violation[0]);
        $this->assertSame('width', $violation[2]);
        $this->assertSame(120, $violation[3]);
        $this->assertSame(100, $violation[4]);
        $this->assertSame(150, $estimate->getMaxLengthCm());
        $this->assertSame(100, $estimate->getMaxWidthCm());
    }

    /** TASK-ZS2B41 (rev. 3-path) + TASK-WNQCRW — default construction keeps the authoritative shared limits (200cm / 50000g). */
    public function testDefaultDimensionLimitsRemainTheConstraint(): void
    {
        $estimate = new QuoteParcelEstimate([new EstimatedPackage(10, 'UNIT-SKU', 1000.0, 'quote_item_weight', 130, 20, 20)]);

        $this->assertSame(GhnPackageLimits::MAX_DIMENSION_CM, $estimate->getMaxLengthCm());
        $this->assertSame(GhnPackageLimits::MAX_DIMENSION_CM, $estimate->getMaxWidthCm());
        $this->assertSame(GhnPackageLimits::MAX_DIMENSION_CM, $estimate->getMaxHeightCm());
        $this->assertSame(GhnPackageLimits::MAX_WEIGHT_G, $estimate->getMaxWeightG());
        $this->assertNull($estimate->findHardLimitViolation());
    }
}
