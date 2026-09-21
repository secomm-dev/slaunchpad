<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Rate;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Exception\GhnRateEstimationException;
use Secomm\Ghn\Model\Rate\GhnRateRequestMapper;
use Secomm\Ghn\Model\Rate\QuoteParcelEstimator;
use Secomm\ShippingCore\Model\Physical\StoreWeightConverter;

/**
 * TASK-FMBBSD slice 2 / TASK-WAWNDS — the RateRequest → normalized GHN rate input contract:
 * unit-aware gram conversion (kgs|LBS, fail-closed), dimensions NEVER read at RATE, the
 * transient quote-time parcel estimate (PRODUCT_UNIT_AS_PACKAGE), and the failure taxonomy
 * (estimation-unavailable vs invalid-parcel-data vs store misconfiguration).
 */
class GhnRateRequestMapperTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    private StoreManagerInterface&MockObject $storeManager;

    private GhnRateRequestMapper $mapper;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->mapper = new GhnRateRequestMapper(
            new QuoteParcelEstimator(new StoreWeightConverter($this->scopeConfig))
        );
    }

    public function testKilogramStoreWeightIsConvertedToGrams(): void
    {
        $this->givenWeightUnit('kgs');

        $query = $this->mapper->map($this->request([$this->item('SKU-2KG', 2.0, 1.0)]));

        $this->assertSame(2000.0, $query->getEstimate()->getTotalWeightGrams());
        $this->assertSame(1, $query->getEstimate()->getPackageCount());
        $this->assertSame(2, $query->getEstimate()->getServiceTypeId());
    }

    public function testPoundStoreWeightIsConvertedToGrams(): void
    {
        $this->givenWeightUnit('LBS ');

        $query = $this->mapper->map($this->request([$this->item('SKU-1LB', 1.0, 1.0)]));

        $this->assertEqualsWithDelta(453.59237, $query->getEstimate()->getTotalWeightGrams(), 0.00001);
    }

    public function testMissingWeightUnitRefusesToQuoteInsteadOfGuessing(): void
    {
        $this->givenWeightUnit('');

        $this->expectException(LocalizedException::class);
        $this->mapper->map($this->request([$this->item('SKU', 2.0, 1.0)]));
    }

    public function testUnrecognizedWeightUnitRefusesToQuoteInsteadOfGuessing(): void
    {
        $this->givenWeightUnit('stones');

        $this->expectException(LocalizedException::class);
        $this->mapper->map($this->request([$this->item('SKU', 2.0, 1.0)]));
    }

    public function testLocalityNameIsTrimmedAndBlankBecomesNull(): void
    {
        $this->givenWeightUnit('kgs');

        $query = $this->mapper->map($this->request([$this->item('SKU', 1.0, 1.0)], destCity: '  Phường Bến Nghé  '));

        $this->assertSame('Phường Bến Nghé', $query->getLocalityName());
        $this->assertNull($this->mapper->map($this->request([$this->item('SKU', 1.0, 1.0)], destCity: '   '))->getLocalityName());
    }

    public function testNoCollectionAmountIsEverInferred(): void
    {
        $this->givenWeightUnit('kgs');

        $query = $this->mapper->map($this->request([$this->item('SKU', 3.0, 1.0)]));

        $this->assertNull($query->getCollectionAmount());
        $this->assertNull($query->getCityId());
        $this->assertSame('VN', $query->getCountryId());
        $this->assertSame(12, $query->getRegionId());
    }

    // ---------- TASK-WAWNDS estimator contracts (through the mapper boundary) ----------

    public function testQuantityTwoExpandsTwoEstimatedPackages(): void
    {
        $this->givenWeightUnit('kgs');

        $query = $this->mapper->map($this->request([$this->item('SKU-25KG', 25.0, 2.0)]));

        // PRODUCT_UNIT_AS_PACKAGE: qty 2 → 2 estimated packages → type 5 even at 2kg each? no —
        // 2 packages ⇒ multi-parcel ⇒ type 5 regardless of the 50kg aggregate (docs: multi-parcel).
        $this->assertSame(2, $query->getEstimate()->getPackageCount());
        $this->assertSame(50000.0, $query->getEstimate()->getTotalWeightGrams());
        $this->assertSame(5, $query->getEstimate()->getServiceTypeId());
    }

    public function testVirtualItemsNeverBecomePackages(): void
    {
        $this->givenWeightUnit('kgs');
        $virtual = $this->item('SKU-VIRTUAL', 0.5, 1.0, virtual: true);
        $physical = $this->item('SKU-PHYSICAL', 2.0, 1.0);

        $query = $this->mapper->map($this->request([$virtual, $physical]));

        $this->assertSame(1, $query->getEstimate()->getPackageCount());
        $this->assertSame('SKU-PHYSICAL', $query->getEstimate()->getPackages()[0]->getSourceSku());
    }

    public function testDecimalQuantityIsEstimationUnavailableNotASplit(): void
    {
        $this->givenWeightUnit('kgs');

        try {
            $this->mapper->map($this->request([$this->item('SKU-FABRIC', 3.0, 0.5)]));
            $this->fail('decimal quantity must not produce fractional packages');
        } catch (GhnRateEstimationException $e) {
            $this->assertSame(QuoteParcelEstimator::REASON_ESTIMATION_UNAVAILABLE, $e->getReasonCode());
        }
    }

    public function testMissingWeightIsInvalidParcelDataNeverZero(): void
    {
        $this->givenWeightUnit('kgs');

        try {
            $this->mapper->map($this->request([$this->item('SKU-NOWEIGHT', 0.0, 1.0)]));
            $this->fail('zero weight must be invalid parcel data');
        } catch (GhnRateEstimationException $e) {
            $this->assertSame(QuoteParcelEstimator::REASON_INVALID_PARCEL_DATA, $e->getReasonCode());
        }
    }

    public function testEmptyQuoteIsEstimationUnavailable(): void
    {
        $this->givenWeightUnit('kgs');

        try {
            $this->mapper->map($this->request());
            $this->fail('a quote with no shippable units cannot be estimated');
        } catch (GhnRateEstimationException $e) {
            $this->assertSame(QuoteParcelEstimator::REASON_ESTIMATION_UNAVAILABLE, $e->getReasonCode());
        }
    }

    public function testConfigurableChildCountedThroughParentWeightNotDuplicated(): void
    {
        // Magento parent-parity expansion: the child line carries parentItem → skipped; the
        // parent line (weight resolved by Magento to the selected child's value) is the unit.
        $this->givenWeightUnit('kgs');
        $child = $this->item('SKU-CHILD', 3.0, 1.0, parentItem: $this->createMock(Item::class));
        $parent = $this->item('SKU-CONFIG-PARENT', 3.0, 1.0);
        $parent->method('getHasChildren')->willReturn(true);

        $query = $this->mapper->map($this->request([$parent, $child]));

        $this->assertSame(1, $query->getEstimate()->getPackageCount());
        $this->assertSame(3000.0, $query->getEstimate()->getTotalWeightGrams());
    }

    // ---------- helpers ----------

    private function givenWeightUnit(string $unit): void
    {
        $this->scopeConfig->method('getValue')->with(
            'general/locale/weight_unit',
            ScopeInterface::SCOPE_STORE,
            1
        )->willReturn($unit);
    }

    /**
     * @param Item&MockObject ...$items
     */
    /**
     * @param list<Item&MockObject> $items
     */
    private function request(array $items = [], string $destCity = 'Phường Bến Nghé'): RateRequest
    {
        return (new RateRequest())
            ->setDestCountryId('VN')
            ->setDestRegionId(12)
            ->setDestCity($destCity)
            ->setAllItems($items)
            ->setStoreId(1);
    }

    private function item(
        string $sku,
        float $weightKg,
        float $qty,
        bool $virtual = false,
        ?Item $parentItem = null
    ): Item&MockObject {
        $product = $this->createMock(Product::class);
        $product->method('isVirtual')->willReturn($virtual);

        $item = $this->getMockBuilder(Item::class)
            ->onlyMethods(['getProduct', 'getSku', 'getTotalQty', 'getItemId', 'getParentItem', 'isShipSeparately'])
            ->addMethods(['getWeight', 'getHasChildren', 'getFreeShipping'])
            ->disableOriginalConstructor()
            ->getMock();
        $item->method('getProduct')->willReturn($product);
        $item->method('getSku')->willReturn($sku);
        $item->method('getWeight')->willReturn($weightKg);
        $item->method('getTotalQty')->willReturn($qty);
        $item->method('getItemId')->willReturn(random_int(1, 999));
        $item->method('getParentItem')->willReturn($parentItem);
        $item->method('getHasChildren')->willReturn(false);
        $item->method('getFreeShipping')->willReturn(false);

        return $item;
    }
}
