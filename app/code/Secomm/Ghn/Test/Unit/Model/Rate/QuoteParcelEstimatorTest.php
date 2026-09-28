<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Rate;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Base\Api\Data\ShippingDimensions;
use Secomm\Base\Api\ShippingDimensionsReaderInterface;
use Secomm\Ghn\Model\Rate\EstimatedPackage;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimate;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimator;
use Secomm\ShippingCore\Model\Physical\StoreWeightConverter;

/**
 * TASK-RT50KH — estimator dimension wiring (DEC-TASKRT50KH-001): per-unit product shipping
 * dimensions flow into the estimated packages (complete-and-valid only, ceil to int cm),
 * replicated IDENTICALLY to every unit of the item (dimensions are NEVER multiplied by
 * quantity), and feed the 150cm hard-limit gate downstream. Missing/partial/malformed dims →
 * null dims → never a dimension rejection.
 */
class QuoteParcelEstimatorTest extends TestCase
{
    private ShippingDimensionsReaderInterface&MockObject $dimensionsReader;

    private QuoteParcelEstimator $estimator;

    protected function setUp(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->with('general/locale/weight_unit', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('kgs');
        $this->dimensionsReader = $this->createMock(ShippingDimensionsReaderInterface::class);
        $this->estimator = new QuoteParcelEstimator(
            new StoreWeightConverter($scopeConfig),
            $this->dimensionsReader
        );
    }

    private function request(array $items = []): RateRequest
    {
        return (new RateRequest())
            ->setDestCountryId('VN')
            ->setDestRegionId(12)
            ->setAllItems($items)
            ->setStoreId(1);
    }

    private function item(
        string $sku,
        float $weightKg,
        float $qty,
        ?ShippingDimensions $dimensions = null
    ): Item&MockObject {
        $product = $this->createMock(Product::class);
        $product->method('isVirtual')->willReturn(false);

        $item = $this->getMockBuilder(Item::class)
            ->onlyMethods(['getProduct', 'getSku', 'getTotalQty', 'getItemId', 'getParentItem', 'isShipSeparately'])
            ->addMethods(['getWeight', 'getHasChildren', 'getFreeShipping'])
            ->disableOriginalConstructor()
            ->getMock();
        $item->method('getProduct')->willReturn($product);
        $item->method('getSku')->willReturn($sku);
        $item->method('getWeight')->willReturn($weightKg);
        $item->method('getTotalQty')->willReturn($qty);
        $item->method('getItemId')->willReturn(500);
        $item->method('getParentItem')->willReturn(null);
        $item->method('isShipSeparately')->willReturn(false);
        $this->dimensionsReader->method('read')->willReturn($dimensions);

        return $item;
    }

    private function dims(int $l, int $w, int $h): ShippingDimensions
    {
        return new ShippingDimensions($l, $w, $h);
    }

    public function testCompleteDimensionsFlowToEveryUnitPackage(): void
    {
        $estimate = $this->estimator->estimate($this->request([
            $this->item('SKU-10KG', 10.0, 1.0, $this->dims(41, 20, 30)),
        ]));

        $packages = $estimate->getPackages();
        $this->assertCount(1, $packages);
        $this->assertSame(41, $packages[0]->getLengthCm());
        $this->assertSame(20, $packages[0]->getWidthCm());
        $this->assertSame(30, $packages[0]->getHeightCm());
    }

    public function testQuantityThreeYieldsThreePackagesWithIdenticalDimensions(): void
    {
        // Directive §5: dimension validation is PER sellable unit — dimensions are never
        // multiplied by qty (weight still aggregates through the unit count).
        $estimate = $this->estimator->estimate($this->request([
            $this->item('SKU-10KG', 10.0, 3.0, $this->dims(100, 40, 30)),
        ]));

        $packages = $estimate->getPackages();
        $this->assertCount(3, $packages);
        foreach ($packages as $package) {
            $this->assertSame(100, $package->getLengthCm());
            $this->assertSame(40, $package->getWidthCm());
            $this->assertSame(30, $package->getHeightCm());
            $this->assertSame(10000.0, $package->getWeightGrams());
        }
        $this->assertSame(30000.0, $estimate->getTotalWeightGrams());
    }

    public function testMissingDimensionsStayNullAndNeverReject(): void
    {
        $estimate = $this->estimator->estimate($this->request([
            $this->item('SKU-10KG', 10.0, 1.0, null),
        ]));

        $packages = $estimate->getPackages();
        $this->assertCount(1, $packages);
        $this->assertNull($packages[0]->getLengthCm());
        $this->assertNull($packages[0]->getWidthCm());
        $this->assertNull($packages[0]->getHeightCm());
        $this->assertNull($estimate->findWeightLimitViolation());
        $this->assertNull($estimate->findHardLimitViolation());
    }

    public function testMalformedDimensionsNeverBecomeValid(): void
    {
        // The reader contract guarantees the malformed case never materializes as a
        // ShippingDimensions instance — the estimator only ever sees null.
        $estimate = $this->estimator->estimate($this->request([
            $this->item('SKU-10KG', 10.0, 1.0, null),
        ]));

        $this->assertSame(1, $estimate->getPackageCount());
        $this->assertNull($estimate->findHardLimitViolation());
    }

    public function testTwoItemsEachGetTheirOwnDimensions(): void
    {
        $this->dimensionsReader->method('read')->willReturnOnConsecutiveCalls(
            $this->dims(100, 40, 30),
            $this->dims(50, 50, 50)
        );
        $estimate = $this->estimator->estimate($this->request([
            $this->item('SKU-A', 10.0, 1.0),
            $this->item('SKU-B', 20.0, 1.0),
        ]));

        $packages = $estimate->getPackages();
        $this->assertCount(2, $packages);
        $this->assertSame(100, $packages[0]->getLengthCm());
        $this->assertSame(50, $packages[1]->getLengthCm());
    }

    public function testOverLimitDimensionIsSurfacedByTheHardLimitQuery(): void
    {
        // Integration anchor: a 160cm unit activates the dormant 150cm gate (hard
        // UNAVAILABLE upstream, carrier-owned reason, no API call).
        $estimate = $this->estimator->estimate($this->request([
            $this->item('SKU-10KG', 10.0, 1.0, $this->dims(160, 20, 20)),
        ]));

        $violation = $estimate->findHardLimitViolation();
        $this->assertNotNull($violation);
        $this->assertSame('GHN_PACKAGE_LENGTH_LIMIT_EXCEEDED', $violation[0]);
        $this->assertSame(160, $violation[3]);
        $this->assertSame(150, $violation[4]);
    }

    public function testEstimateInstanceShapeUnchangedForNullDims(): void
    {
        // Parity anchor: a null-dims estimate is byte-equivalent to the pre-TASK-RT50KH
        // behavior (type selection, package shape).
        $estimate = $this->estimator->estimate($this->request([
            $this->item('SKU-2KG', 2.0, 1.0),
        ]));

        $this->assertInstanceOf(QuoteParcelEstimate::class, $estimate);
        $this->assertSame(2, $estimate->getServiceTypeId());
        $this->assertSame(EstimatedPackage::class, $estimate->getPackages()[0]::class);
    }
}
