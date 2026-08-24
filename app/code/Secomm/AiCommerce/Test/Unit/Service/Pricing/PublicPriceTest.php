<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Service\Pricing;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Service\Pricing\PublicPrice;

class PublicPriceTest extends TestCase
{
    /**
     * @var PublicPrice
     */
    private $publicPrice;

    protected function setUp(): void
    {
        $this->publicPrice = new PublicPrice();
    }

    public function testFinalAndRegularPriceInDefaultCurrency(): void
    {
        $store = $this->storeWithDefaultCurrency('USD');
        $product = $this->productWithPrices(45.0, 59.0);

        $price = $this->publicPrice->resolve($store, $product);

        $this->assertSame(45.0, $price['value']);
        $this->assertSame('USD', $price['currency']);
        $this->assertSame(59.0, $price['regular_value']);
    }

    public function testRegularValueNullWhenNoDiscount(): void
    {
        $store = $this->storeWithDefaultCurrency('USD');
        $product = $this->productWithPrices(45.0, 45.0);

        $price = $this->publicPrice->resolve($store, $product);

        $this->assertNull($price['regular_value']);
    }

    public function testDtoNeverContainsQuantityFields(): void
    {
        $store = $this->storeWithDefaultCurrency('USD');
        $product = $this->productWithPrices(10.0, 10.0);

        $price = $this->publicPrice->resolve($store, $product);

        $this->assertSame(['value', 'currency', 'regular_value'], array_keys($price));
    }

    /**
     * Store mock pinned to a default currency.
     *
     * @param string $currency default currency code
     * @return Store|\PHPUnit\Framework\MockObject\MockObject
     */
    private function storeWithDefaultCurrency(string $currency)
    {
        $store = $this->createMock(Store::class);
        $store->method('getDefaultCurrencyCode')->willReturn($currency);
        $store->expects($this->once())->method('setCurrentCurrencyCode')->with($currency);

        return $store;
    }

    /**
     * Product mock with final/regular price values.
     *
     * @param float $final final price value
     * @param float $regular regular price value
     * @return ProductInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function productWithPrices(float $final, float $regular)
    {
        $finalPrice = $this->createMock(PriceInterface::class);
        $finalPrice->method('getValue')->willReturn($final);
        $regularPrice = $this->createMock(PriceInterface::class);
        $regularPrice->method('getValue')->willReturn($regular);

        $priceInfo = $this->createMock(PriceInfoInterface::class);
        $priceInfo->method('getPrice')->willReturnMap([
            [FinalPrice::PRICE_CODE, $finalPrice],
            [RegularPrice::PRICE_CODE, $regularPrice],
        ]);

        $product = $this->createMock(Product::class);
        $product->method('getPriceInfo')->willReturn($priceInfo);

        return $product;
    }
}
