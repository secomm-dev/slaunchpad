<?php
/**
 * Copyright © Secomm All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\Base\Test\Unit\Model\Shipping;

use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Base\Model\Shipping\ProductShippingDimensionsReader;

/**
 * TASK-RT50KH — P1 product shipping-dimension read contract (DEC-TASKRT50KH-001):
 * authoritative ONLY when all three values are present, numeric and > 0, CEILed to whole
 * centimeters; anything else → null ("missing" — never a rejection, never a default).
 * Composite resolution: configurable ship-together → selected child; bundle ship-together →
 * parent product.
 */
class ProductShippingDimensionsReaderTest extends TestCase
{
    private ProductShippingDimensionsReader $reader;

    protected function setUp(): void
    {
        $this->reader = new ProductShippingDimensionsReader();
    }

    /**
     * @param array<string, string|int|null> $dimensionData
     */
    private function item(
        string $type = 'simple',
        ?Product $product = null,
        array $dimensionData = [],
        ?array $children = null
    ): Item&MockObject {
        $product = $product ?? $this->product($dimensionData, $type);
        $item = $this->getMockBuilder(Item::class)
            ->onlyMethods(['getProduct', 'getProductType', 'getChildren'])
            ->disableOriginalConstructor()
            ->getMock();
        $item->method('getProduct')->willReturn($product);
        $item->method('getProductType')->willReturn($type);
        $item->method('getChildren')->willReturn($children ?? []);

        return $item;
    }

    private function product(array $dimensionData, string $type = 'simple'): Product&MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getData')->willReturnCallback(
            static function (string $key) use ($dimensionData) {
                return $dimensionData[$key] ?? null;
            }
        );
        if ($type !== 'simple') {
            $product->method('getTypeId')->willReturn($type);
        }

        return $product;
    }

    public function testCompleteDecimalDimensionsAreCeiledToWholeCentimeters(): void
    {
        $dimensions = $this->reader->read($this->item(dimensionData: [
            'length' => '40.5',
            'width' => '20',
            'height' => '30',
        ]));

        $this->assertNotNull($dimensions);
        // 40.5 ceil → 41 (conservative at carrier boundaries).
        $this->assertSame(41, $dimensions->getLengthCm());
        $this->assertSame(20, $dimensions->getWidthCm());
        $this->assertSame(30, $dimensions->getHeightCm());
    }

    public function testBoundaryValuesStayOnTheSafeSideOfTheCeil(): void
    {
        $atLimit = $this->reader->read($this->item(dimensionData: ['length' => '150', 'width' => '149.2', 'height' => '20']));
        $this->assertNotNull($atLimit);
        $this->assertSame(150, $atLimit->getLengthCm());
        // 149.2 → 150: the ceiling never lets a fractional size slip under a limit.
        $this->assertSame(150, $atLimit->getWidthCm());

        $justOver = $this->reader->read($this->item(dimensionData: ['length' => '150.1', 'width' => '20', 'height' => '20']));
        $this->assertNotNull($justOver);
        $this->assertSame(151, $justOver->getLengthCm());
    }

    public function testPartialDimensionsAreMissing(): void
    {
        $this->assertNull($this->reader->read($this->item(dimensionData: [
            'length' => '40.5',
            'width' => '20',
            // height missing
        ])));
    }

    public function testZeroDimensionIsMissing(): void
    {
        $this->assertNull($this->reader->read($this->item(dimensionData: [
            'length' => '0',
            'width' => '20',
            'height' => '30',
        ])));
    }

    public function testNegativeDimensionIsMissing(): void
    {
        $this->assertNull($this->reader->read($this->item(dimensionData: [
            'length' => '-5',
            'width' => '20',
            'height' => '30',
        ])));
    }

    public function testNonNumericDimensionIsMissing(): void
    {
        $this->assertNull($this->reader->read($this->item(dimensionData: [
            'length' => 'abc',
            'width' => '20',
            'height' => '30',
        ])));
    }

    public function testBlankAndNullDimensionsAreMissing(): void
    {
        $this->assertNull($this->reader->read($this->item(dimensionData: [
            'length' => '',
            'width' => null,
            'height' => '30',
        ])));
    }

    public function testConfigurableResolvesSelectedChildDimensions(): void
    {
        $childProduct = $this->product(['length' => '25', 'width' => '15', 'height' => '10']);
        $child = $this->createMock(Item::class);
        $child->method('getProduct')->willReturn($childProduct);

        $parentProduct = $this->product(['length' => '999', 'width' => '999', 'height' => '999'], 'configurable');
        $dimensions = $this->reader->read($this->item(
            type: 'configurable',
            product: $parentProduct,
            children: [$child]
        ));

        $this->assertNotNull($dimensions);
        // The PARENT's placeholder dims must never be used — the selected child is the unit.
        $this->assertSame(25, $dimensions->getLengthCm());
        $this->assertSame(15, $dimensions->getWidthCm());
        $this->assertSame(10, $dimensions->getHeightCm());
    }

    public function testConfigurableWithoutChildrenIsMissing(): void
    {
        $parentProduct = $this->product(['length' => '999', 'width' => '999', 'height' => '999'], 'configurable');

        $this->assertNull($this->reader->read($this->item(
            type: 'configurable',
            product: $parentProduct,
            children: []
        )));
    }

    public function testBundleShipTogetherUsesParentDimensions(): void
    {
        $parentProduct = $this->product(['length' => '80', 'width' => '60', 'height' => '40'], 'bundle');

        $dimensions = $this->reader->read($this->item(type: 'bundle', product: $parentProduct));

        $this->assertNotNull($dimensions);
        $this->assertSame(80, $dimensions->getLengthCm());
        $this->assertSame(60, $dimensions->getWidthCm());
        $this->assertSame(40, $dimensions->getHeightCm());
    }

    public function testNullProductIsMissing(): void
    {
        $this->assertNull($this->reader->read($this->item(product: null)));
    }
}
