<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Shipment;

use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Rate\GhnParcel;
use Secomm\Ghn\Model\Shipment\GhnCreateValidationException;
use Secomm\Ghn\Model\Shipment\GhnPhysicalLimit;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\Shipment\GhnPhysicalParcelInterpreter;
use Secomm\ShippingCore\Model\Physical\PhysicalPackage;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalData;

/**
 * TASK-9Q5ZAK r2 (DEC-TASK9Q5ZAK-001) — the GHN interpretation matrix over carrier-neutral
 * physical facts: 1 light package → type 2 root; heavy OR multi-package → type 5 items[]
 * (one GHN heavy item = ONE physical package, exact values, quantity 1, never aggregated);
 * per-package provider limits fail closed before any HTTP call. Grams normalized first —
 * no floating-point boundary ambiguity.
 */
class GhnPhysicalParcelInterpreterTest extends TestCase
{
    private GhnPhysicalParcelInterpreter $interpreter;

    protected function setUp(): void
    {
        $constraints = \Secomm\Ghn\Model\GhnShipmentConstraints::MAX_SIDE_CM;
        $this->interpreter = $this->interpreterWithLimits($constraints, $constraints, $constraints);
    }

    public function testSingleLightPackageMapsToType2Root(): void
    {
        $plan = $this->interpreter->interpret($this->physical([[5000, 30, 20, 10]]));

        $this->assertSame(GhnParcel::SERVICE_TYPE_LIGHT_PARCEL, $plan->getServiceTypeId());
        $this->assertSame(5000, $plan->getRootWeightG());
        $this->assertSame(30, $plan->getRootLengthCm());
        $this->assertSame(20, $plan->getRootWidthCm());
        $this->assertSame(10, $plan->getRootHeightCm());
        $this->assertNull($plan->getItems(), 'a light parcel needs no heavy items');
    }

    public function testBoundaryJustUnder20KgStaysType2(): void
    {
        $plan = $this->interpreter->interpret($this->physical([[19999, 40, 30, 20]]));

        $this->assertSame(GhnParcel::SERVICE_TYPE_LIGHT_PARCEL, $plan->getServiceTypeId());
    }

    public function testSingleHeavyPackageBecomesType5WithOneItem(): void
    {
        $plan = $this->interpreter->interpret($this->physical([[45000, 60, 50, 40]]));

        $this->assertSame(GhnParcel::SERVICE_TYPE_HEAVY_GOODS, $plan->getServiceTypeId());
        $items = $plan->getItems();
        $this->assertCount(1, $items);
        $this->assertSame('Package 1', $items[0]['name']);
        $this->assertSame(1, $items[0]['quantity']);
        $this->assertSame(45000, $items[0]['weight']);
        $this->assertSame(60, $items[0]['length']);
        $this->assertSame(50, $items[0]['width']);
        $this->assertSame(40, $items[0]['height']);
        $this->assertSame(45000, $plan->getRootWeightG(), 'root weight is provider-mandatory even for type 5 (sandbox-verified)');
    }

    public function testMultiplePackagesBecomeType5ItemsOnePerPhysicalPackage(): void
    {
        $plan = $this->interpreter->interpret($this->physical([
            [50000, 200, 200, 200],
            [50000, 150, 100, 80],
            [12000, 40, 30, 20],
            [1000, 10, 10, 10],
        ]));

        $this->assertSame(GhnParcel::SERVICE_TYPE_HEAVY_GOODS, $plan->getServiceTypeId(), 'multi-package is type 5 by contract');
        $items = $plan->getItems();
        $this->assertCount(4, $items, 'one GHN item per physical package — never aggregated');
        $this->assertSame(['Package 1', 'Package 2', 'Package 3', 'Package 4'], array_column($items, 'name'));
        $this->assertSame([50000, 50000, 12000, 1000], array_column($items, 'weight'));
        $this->assertSame([200, 150, 40, 10], array_column($items, 'length'));
        $this->assertSame([1, 1, 1, 1], array_column($items, 'quantity'));
        $this->assertSame(113000, $plan->getRootWeightG(), 'root weight = the factual Σ (provider-mandatory)');
    }

    public function testType5DimsAreOmittedButRootWeightMandatory(): void
    {
        $plan = $this->interpreter->interpret($this->physical([
            [30000, 50, 40, 30],
            [30000, 50, 40, 30],
        ]));

        $this->assertSame(60000, $plan->getRootWeightG(), 'factual Σ — sandbox-verified accepted >50,000g with items[]');
        $this->assertNull($plan->getRootLengthCm(), 'r3: root dims omitted — items[] carries the physical truth');
        $this->assertNull($plan->getRootWidthCm());
        $this->assertNull($plan->getRootHeightCm());
    }

    public function testPackageAboveWeightLimitFailsClosed(): void
    {
        $this->expectException(GhnCreateValidationException::class);
        $this->expectExceptionMessage('above the 50000 g per-package limit');

        $this->interpreter->interpret($this->physical([[51000, 100, 100, 100]]));
    }

    public function testPackageAboveSideLimitFailsClosed(): void
    {
        try {
            $this->interpreter->interpret($this->physical([[1000, 201, 50, 50]]));
            $this->fail('Expected GhnCreateValidationException');
        } catch (GhnCreateValidationException $exception) {
            $this->assertSame(GhnCreateValidationException::REASON_INVALID_PARCEL, $exception->getReasonToken());
        }
    }

    public function testSecondPackageViolatingLimitsFailsTheWholeShipment(): void
    {
        $this->expectException(GhnCreateValidationException::class);

        $this->interpreter->interpret($this->physical([[1000, 10, 10, 10], [1000, 300, 10, 10]]));
    }

    /**
     * TASK-WNQCRW §15 — RATE ≠ CREATE truth: the SAME 15kg order quotes as RATE type 2
     * (total-weight-only rule) while CREATE derives type 5 INDEPENDENTLY from the 2 actual
     * physical parcels the warehouse packed (physical package count + per-package weight).
     */
    public function testCreateDerivesIndependentlyFromTheRateClassification(): void
    {
        // RATE view (transient, never persisted): 3 items, 15kg total → type 2.
        $rateEstimate = new \Secomm\Ghn\Model\Rate\QuoteParcelEstimate([
            new \Secomm\Ghn\Model\Rate\EstimatedPackage(11, 'SKU-A', 5000.0, 'quote_item_weight'),
            new \Secomm\Ghn\Model\Rate\EstimatedPackage(12, 'SKU-B', 5000.0, 'quote_item_weight'),
            new \Secomm\Ghn\Model\Rate\EstimatedPackage(13, 'SKU-C', 5000.0, 'quote_item_weight'),
        ]);
        $this->assertSame(2, $rateEstimate->getServiceTypeId(), 'RATE: 15kg total is type 2');

        // CREATE view (fulfillment truth): the same order packed into 2 light physical
        // parcels → type 5 with items[] — NOT the RATE type.
        $plan = $this->interpreter->interpret($this->physical([
            [7500, 30, 20, 10],
            [7500, 30, 20, 10],
        ]));

        $this->assertSame(GhnParcel::SERVICE_TYPE_HEAVY_GOODS, $plan->getServiceTypeId(), 'CREATE re-derives from physical parcels');
        $this->assertCount(2, $plan->getItems());
        $this->assertSame(15000, $plan->getRootWeightG());
    }

    /**
     * TASK-ZS2B41 bug fix regression — width compares against ITS OWN limit: 190cm width
     * with a lowered width limit of 150 must fail closed even though length (100) and
     * height (100) are well under the 200 defaults. (Pre-fix this passed: width was checked
     * against the length limit.)
     */
    public function testWidthAboveItsOwnLimitFailsClosedEvenWhenLengthIsUnder(): void
    {
        $interpreter = $this->interpreterWithLimits(200, 150, 200);

        try {
            $interpreter->interpret($this->physical([[1000, 100, 190, 100]]));
            $this->fail('Expected GhnCreateValidationException');
        } catch (GhnCreateValidationException $exception) {
            $this->assertSame(GhnCreateValidationException::REASON_INVALID_PARCEL, $exception->getReasonToken());
            $this->assertStringContainsString('width', $exception->getMessage());
            $this->assertStringContainsString('150', $exception->getMessage());
        }
    }

    /** TASK-ZS2B41 bug fix regression — same own-limit rule for height. */
    public function testHeightAboveItsOwnLimitFailsClosedEvenWhenLengthIsUnder(): void
    {
        $interpreter = $this->interpreterWithLimits(200, 200, 150);

        $this->expectException(GhnCreateValidationException::class);
        $this->expectExceptionMessage('height');

        $interpreter->interpret($this->physical([[1000, 100, 100, 190]]));
    }

    /** TASK-ZS2B41 bug fix regression — dimensions under their OWN (lowered) limits still interpret. */
    public function testDimensionsUnderTheirOwnLoweredLimitsStillInterpret(): void
    {
        $interpreter = $this->interpreterWithLimits(200, 150, 150);

        $plan = $interpreter->interpret($this->physical([[1000, 140, 140, 140]]));

        $this->assertSame(GhnParcel::SERVICE_TYPE_LIGHT_PARCEL, $plan->getServiceTypeId());
    }

    // ---------- helpers ----------

    /**
     * @param array<int, array{0: int, 1: int, 2: int, 3: int}> $packageTuples [weightG, l, w, h]
     */
    private function physical(array $packageTuples): ShipmentPhysicalData
    {
        $packages = [];
        foreach ($packageTuples as [$weightG, $l, $w, $h]) {
            $packages[] = new PhysicalPackage($weightG, $l, $w, $h);
        }

        return ShipmentPhysicalData::fromPackages($packages);
    }

    /**
     * TASK-ZS2B41 (rev. 3-path) — builder pinning the merchant-tunable shared limits
     * (length, width, height) so tests can lower individual dimensions.
     */
    private function interpreterWithLimits(int $lengthCm, int $widthCm, int $heightCm): GhnPhysicalParcelInterpreter
    {
        $config = $this->createMock(Config::class);
        $config->method('getMaxLengthCm')->willReturn($lengthCm);
        $config->method('getMaxWidthCm')->willReturn($widthCm);
        $config->method('getMaxHeightCm')->willReturn($heightCm);

        return new GhnPhysicalParcelInterpreter(new GhnPhysicalLimit($config));
    }
}