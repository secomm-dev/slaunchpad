<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Service\Pricing;

use Magento\Bundle\Pricing\Price\FinalPrice as BundleFinalPrice;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\Pricing\Amount\AmountInterface;
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
        $store = $this->storeWithDefaultCurrency('USD', 'EUR');
        $product = $this->productWithPrices(45.0, 59.0);

        $price = $this->publicPrice->resolve($store, $product);

        $this->assertSame(45.0, $price['value']);
        $this->assertSame('USD', $price['currency']);
        $this->assertSame(59.0, $price['regular_value']);
    }

    public function testRegularValueNullWhenNoDiscount(): void
    {
        $store = $this->storeWithDefaultCurrency('USD', 'EUR');
        $product = $this->productWithPrices(45.0, 45.0);

        $price = $this->publicPrice->resolve($store, $product);

        $this->assertNull($price['regular_value']);
    }

    public function testDtoNeverContainsQuantityFields(): void
    {
        $store = $this->storeWithDefaultCurrency('USD', 'EUR');
        $product = $this->productWithPrices(10.0, 10.0);

        $price = $this->publicPrice->resolve($store, $product);

        $this->assertSame(['value', 'currency', 'regular_value'], array_keys($price));
    }

    public function testCurrencyIsPinnedAndRestored(): void
    {
        $calls = [];
        $store = $this->createMock(Store::class);
        $store->method('getDefaultCurrencyCode')->willReturn('USD');
        $store->method('getCurrentCurrencyCode')->willReturn('EUR');
        $store->method('setCurrentCurrencyCode')
            ->willReturnCallback(static function (string $code) use (&$calls): void {
                $calls[] = $code;
            });

        $this->publicPrice->resolve($store, $this->productWithPrices(10.0, 10.0));

        // Pin to default for the read, then restore the previous current
        // currency — no request-global Store mutation is left behind.
        $this->assertSame(['USD', 'EUR'], $calls);
    }

    public function testCurrencyIsRestoredWhenPriceResolutionThrows(): void
    {
        $calls = [];
        $store = $this->createMock(Store::class);
        $store->method('getDefaultCurrencyCode')->willReturn('USD');
        $store->method('getCurrentCurrencyCode')->willReturn('EUR');
        $store->method('setCurrentCurrencyCode')
            ->willReturnCallback(static function (string $code) use (&$calls): void {
                $calls[] = $code;
            });

        $product = $this->createMock(Product::class);
        $product->method('getPriceInfo')->willThrowException(new \RuntimeException('engine down'));

        try {
            $this->publicPrice->resolve($store, $product);
            $this->fail('Expected exception to propagate');
        } catch (\RuntimeException $exception) {
            $this->assertSame('engine down', $exception->getMessage());
        }

        // finally ran: pin + restore both executed, original exception intact.
        $this->assertSame(['USD', 'EUR'], $calls);
    }

    public function testBundleUsesMinimalPriceSemantics(): void
    {
        $store = $this->storeWithDefaultCurrency('USD', 'EUR');

        $minimalAmount = $this->createMock(AmountInterface::class);
        $minimalAmount->method('getValue')->willReturn(14.0);

        $finalPrice = $this->createMock(BundleFinalPrice::class);
        $finalPrice->method('getMinimalPrice')->willReturn($minimalAmount);

        $regularPrice = $this->createMock(PriceInterface::class);
        $regularPrice->method('getValue')->willReturn(14.0);

        $priceInfo = $this->createMock(PriceInfoInterface::class);
        $priceInfo->method('getPrice')->willReturnMap([
            [FinalPrice::PRICE_CODE, $finalPrice],
            [RegularPrice::PRICE_CODE, $regularPrice],
        ]);

        $product = $this->createMock(Product::class);
        $product->method('getPriceInfo')->willReturn($priceInfo);

        $price = $this->publicPrice->resolve($store, $product);

        $this->assertSame(14.0, $price['value']);
        $this->assertNull($price['regular_value']);
    }

    /**
     * Store mock pinned to a default currency with a distinct current currency.
     *
     * @param string $currency default currency code
     * @param string $current current currency code (before/after)
     * @return Store|\PHPUnit\Framework\MockObject\MockObject
     */
    private function storeWithDefaultCurrency(string $currency, string $current)
    {
        $store = $this->createMock(Store::class);
        $store->method('getDefaultCurrencyCode')->willReturn($currency);
        $store->method('getCurrentCurrencyCode')->willReturn($current);
        $store->method('setCurrentCurrencyCode')->willReturn(null);

        return $store;
    }

    /**
     * Product mock with final/regular price values.
     *
     * @param float $final final price value
     * @param float $regular regular price value
     * @return Product|\PHPUnit\Framework\MockObject\MockObject
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
