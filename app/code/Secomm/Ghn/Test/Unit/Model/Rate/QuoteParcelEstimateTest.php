<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Rate;

use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\GhnShipmentConstraints;
use Secomm\Ghn\Model\Rate\EstimatedPackage;
use Secomm\Ghn\Model\Rate\GhnWeightConstraintViolation;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimate;

/**
 * TASK-MQ2DRG — boundary grid for the deterministic weight pre-validation query
 * (DEC-TASKMQ2DRG-001). Pure VO tests: limits are STRICTLY greater-than; HARD (single unit)
 * is detected before AGGREGATE; missing dimensions never produce a violation.
 */
class QuoteParcelEstimateTest extends TestCase
{
    private function unit(float $weightGrams, int $id = 10): EstimatedPackage
    {
        return new EstimatedPackage($id, 'UNIT-SKU', $weightGrams, 'quote_item_weight');
    }

    public function testSingleUnitUnderLimitsHasNoViolation(): void
    {
        $estimate = new QuoteParcelEstimate([$this->unit(19999.0)]);

        $this->assertNull($estimate->findWeightLimitViolation());
        $this->assertSame(2, $estimate->getServiceTypeId());
    }

    public function testSingleUnitExactlyAtHeavyBoundaryHasNoWeightViolation(): void
    {
        // The 20000g type boundary (<) is untouched by the weight pre-validation: a 20000g
        // unit selects type 5 and is representable — no violation.
        $estimate = new QuoteParcelEstimate([$this->unit(20000.0)]);

        $this->assertNull($estimate->findWeightLimitViolation());
        $this->assertSame(5, $estimate->getServiceTypeId());
    }

    public function testSingleUnitExactlyAtFiftyKgIsRepresentable(): void
    {
        $estimate = new QuoteParcelEstimate([$this->unit(50000.0)]);

        $this->assertNull($estimate->findWeightLimitViolation());
    }

    public function testSingleUnitOverFiftyKgIsHardViolation(): void
    {
        $estimate = new QuoteParcelEstimate([$this->unit(50001.0)]);

        $violation = $estimate->findWeightLimitViolation();

        $this->assertNotNull($violation);
        $this->assertSame(GhnWeightConstraintViolation::KIND_HARD_UNIT_OVER_WEIGHT, $violation->getKind());
        $this->assertSame(0, $violation->getPackageIndex());
        $this->assertSame(50001.0, $violation->getWeightGrams());
        $this->assertSame(GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G, $violation->getLimitGrams());
    }

    public function testAggregateExactlyAtFiftyKgIsRepresentable(): void
    {
        $estimate = new QuoteParcelEstimate([$this->unit(25000.0, 11), $this->unit(25000.0, 12)]);

        $this->assertNull($estimate->findWeightLimitViolation());
    }

    public function testAggregateOverFiftyKgWithValidUnitsIsUnrepresentable(): void
    {
        $estimate = new QuoteParcelEstimate([$this->unit(35000.0, 11), $this->unit(35000.0, 12)]);

        $violation = $estimate->findWeightLimitViolation();

        $this->assertNotNull($violation);
        $this->assertSame(GhnWeightConstraintViolation::KIND_AGGREGATE_UNREPRESENTABLE, $violation->getKind());
        $this->assertSame(-1, $violation->getPackageIndex());
        $this->assertSame(70000.0, $violation->getWeightGrams());
        $this->assertSame(GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G, $violation->getLimitGrams());
    }

    public function testHardUnitViolationWinsOverAggregate(): void
    {
        // Directive order: A before E — the offending unit is reported with its index even
        // when the aggregate is also over the cap.
        $estimate = new QuoteParcelEstimate([$this->unit(20000.0, 11), $this->unit(60000.0, 12)]);

        $violation = $estimate->findWeightLimitViolation();

        $this->assertNotNull($violation);
        $this->assertSame(GhnWeightConstraintViolation::KIND_HARD_UNIT_OVER_WEIGHT, $violation->getKind());
        $this->assertSame(1, $violation->getPackageIndex());
        $this->assertSame(60000.0, $violation->getWeightGrams());
    }

    public function testMissingDimensionsNeverProduceViolations(): void
    {
        // Directive case 7/10: no dimension source at RATE — a null-dims estimate must pass
        // BOTH the weight and the dimension pre-validation queries untouched.
        $estimate = new QuoteParcelEstimate([$this->unit(30000.0)]);

        $this->assertNull($estimate->findWeightLimitViolation());
        $this->assertNull($estimate->findHardLimitViolation());
    }
}
