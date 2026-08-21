<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
declare(strict_types=1);

namespace Mageplaza\Smtp\Test\Unit\Model\Resolver\Bestsellers;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Reports\Helper\Data as ReportsData;
use Magento\Reports\Model\Item;
use Magento\Sales\Model\ResourceModel\Report\Bestsellers\Collection;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Model\Resolver\Bestsellers\Bestsellers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bestsellers::class)]
class BestsellersTest extends TestCase
{
    private DateTime&MockObject $dateTime;
    private ReportsData&MockObject $reportData;
    private Data&MockObject $helperData;
    private Collection&MockObject $bestsellersCollection;
    private StoreManagerInterface&MockObject $storeManager;
    private ProductRepositoryInterface&MockObject $productRepository;
    private EmailMarketing&MockObject $helperEmailMarketing;

    protected function setUp(): void
    {
        $this->dateTime = $this->createMock(DateTime::class);
        $this->dateTime->method('date')->willReturnCallback(
            static fn (string $format, $input): string => date($format, $input)
        );

        $this->reportData = $this->createMock(ReportsData::class);
        $this->helperData = $this->createMock(Data::class);

        $this->bestsellersCollection = $this->createMock(Collection::class);
        $this->bestsellersCollection->method('setPeriod')->willReturnSelf();
        $this->bestsellersCollection->method('setDateRange')->willReturnSelf();
        $this->bestsellersCollection->method('addStoreRestrictions')->willReturnSelf();
        $this->bestsellersCollection->method('load')->willReturnSelf();
        // getItems() is intentionally NOT stubbed here — PHPUnit's InvocationHandler
        // returns the FIRST matching stub, not the last, so a default registered in
        // setUp() would silently shadow any per-test ->method('getItems')->willReturn()
        // override on this same mock instance. Each test configures it explicitly.

        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);

        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret-key');
        $this->helperEmailMarketing->method('getParentId')->willReturn(0);
    }

    private function createSut(): Bestsellers
    {
        return new Bestsellers(
            $this->dateTime,
            $this->reportData,
            $this->helperData,
            $this->bestsellersCollection,
            $this->storeManager,
            $this->productRepository,
            $this->helperEmailMarketing
        );
    }

    private function callResolve(?array $args): array
    {
        return $this->createSut()->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            $args
        );
    }

    private function validFilters(array $overrides = []): array
    {
        return array_merge([
            'from' => 1700000000,
            'to'   => 1700086400,
        ], $overrides);
    }

    private function validArgs(array $filterOverrides = [], array $overrides = []): array
    {
        return array_merge([
            'app_id'     => 'app-id',
            'secret_key' => 'secret-key',
            'filters'    => $this->validFilters($filterOverrides),
        ], $overrides);
    }

    private function makeItem(array $data): Item
    {
        return new Item($data);
    }

    private function makeProduct(
        string $sku,
        string $productUrl,
        ?string $image = null,
        int $storeId = 1
    ): Product&MockObject {
        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn($sku);
        $product->method('getProductUrl')->willReturn($productUrl);
        $product->method('getImage')->willReturn($image);
        $product->method('getStoreId')->willReturn($storeId);

        return $product;
    }

    private function makeStore(string $baseMediaUrl, string $currencyCode): Store&MockObject
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn($baseMediaUrl);
        $store->method('getBaseCurrencyCode')->willReturn($currencyCode);

        return $store;
    }


    public function testResolveThrowsWhenEmailMarketingDisabled(): void
    {
        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(false);

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('Marketing automation is disabled.');

        $this->callResolve(null);
    }

    public function testResolveThrowsWhenAppIdOrSecretKeyInvalid(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('Invalid app id or secret key.');

        $this->callResolve($this->validArgs([], ['app_id' => 'wrong-app-id']));
    }

    public function testResolveThrowsWhenFromOrToMissing(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('From and To fields are required.');

        $this->callResolve($this->validArgs(['from' => null, 'to' => null]));
    }

    public function testResolveThrowsWhenStoreIdInvalid(): void
    {
        $this->storeManager->method('getStore')
            ->willThrowException(new NoSuchEntityException(__('Requested store is not found')));

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage("The store with store ID is 99 doesn't exist.");

        $this->callResolve($this->validArgs(['store_id' => 99]));
    }


    public function testResolveFallsBackToDayPeriodWhenPeriodTypeInvalid(): void
    {
        $this->bestsellersCollection = $this->createMock(Collection::class);
        $this->bestsellersCollection->expects($this->once())->method('setPeriod')->with('day')->willReturnSelf();
        $this->bestsellersCollection->method('setDateRange')->willReturnSelf();
        $this->bestsellersCollection->method('addStoreRestrictions')->willReturnSelf();
        $this->bestsellersCollection->method('load')->willReturnSelf();
        $this->bestsellersCollection->method('getItems')->willReturn([]);

        $result = $this->callResolve($this->validArgs(['period_type' => 'bogus']));

        $this->assertSame(['mpBestsellers' => []], $result);
    }


    public function testResolveReturnsSortedBestsellersForItemsWithProducts(): void
    {
        $product10 = $this->makeProduct('SKU10', 'http://example.com/product-ten.html', '/products/a.jpg', 1);
        $product20 = $this->makeProduct('SKU20', 'http://example.com/product-twenty.html', null, 1);

        $this->productRepository->method('getById')->willReturnCallback(
            static fn (int $id) => match ($id) {
                10 => $product10,
                20 => $product20,
            }
        );

        $storeDefault = $this->makeStore('', 'USD');
        $store1 = $this->makeStore('http://example.com/media/', 'USD');

        $this->storeManager->method('getStore')->willReturnCallback(
            static fn ($storeId = null) => $storeId === Store::DEFAULT_STORE_ID ? $storeDefault : $store1
        );

        $this->bestsellersCollection->method('getItems')->willReturn([
            $this->makeItem([
                'period'        => '2024-01-15',
                'product_id'    => 10,
                'product_name'  => 'Product Ten',
                'product_price' => '19.99',
                'qty_ordered'   => 3,
            ]),
            $this->makeItem([
                'period'        => '2024-01-10',
                'product_id'    => 20,
                'product_name'  => 'Product Twenty',
                'product_price' => '29.50',
                'qty_ordered'   => 1,
            ]),
        ]);

        $result = $this->callResolve($this->validArgs());

        $this->assertSame([
            'mpBestsellers' => [
                [
                    'period'            => '2024-01-10',
                    'product_id'        => 20,
                    'product_sku'       => 'SKU20',
                    'product_url'       => 'http://example.com/product-twenty.html',
                    'product_image_url' => '',
                    'product_name'      => 'Product Twenty',
                    'product_price'     => '29.50',
                    'qty_ordered'       => 1,
                    'currency'          => 'USD',
                ],
                [
                    'period'            => '2024-01-15',
                    'product_id'        => 10,
                    'product_sku'       => 'SKU10',
                    'product_url'       => 'http://example.com/product-ten.html',
                    'product_image_url' => 'http://example.com/media/catalog/product/products/a.jpg',
                    'product_name'      => 'Product Ten',
                    'product_price'     => '19.99',
                    'qty_ordered'       => 3,
                    'currency'          => 'USD',
                ],
            ],
        ], $result);
    }

    public function testResolveAddsPlaceholderRowForFirstEmptyProductPeriod(): void
    {
        $this->storeManager->method('getStore')->willReturn($this->makeStore('', 'USD'));

        $this->bestsellersCollection->method('getItems')->willReturn([
            $this->makeItem([
                'period'     => '2024-02-01',
                'product_id' => null,
            ]),
        ]);

        $result = $this->callResolve($this->validArgs());

        $this->assertSame([
            'mpBestsellers' => [
                [
                    'period'            => '2024-02-01',
                    'product_id'        => null,
                    'product_sku'       => null,
                    'product_url'       => null,
                    'product_image_url' => null,
                    'product_name'      => null,
                    'product_price'     => null,
                    'qty_ordered'       => null,
                    'currency'          => null,
                ],
            ],
        ], $result);
    }

    public function testResolveSkipsDuplicatePlaceholderForSamePeriod(): void
    {
        $product = $this->makeProduct('SKU1', 'http://example.com/p1.html', null, 1);
        $this->productRepository->method('getById')->willReturnCallback(
            static fn ($id) => $id === 1 ? $product : null
        );
        $this->storeManager->method('getStore')->willReturn($this->makeStore('', 'USD'));

        $this->bestsellersCollection->method('getItems')->willReturn([
            $this->makeItem([
                'period'        => '2024-03-01',
                'product_id'    => 1,
                'product_name'  => 'Product One',
                'product_price' => '10.00',
                'qty_ordered'   => 2,
            ]),
            $this->makeItem([
                'period'     => '2024-03-01',
                'product_id' => null,
            ]),
        ]);

        $result = $this->callResolve($this->validArgs());

        $this->assertCount(1, $result['mpBestsellers']);
        $this->assertSame(1, $result['mpBestsellers'][0]['product_id']);
    }


    public function testResolveCallsPrepareIntervalsCollectionWhenShowEmptyRowsTrue(): void
    {
        $this->bestsellersCollection->method('getItems')->willReturn([]);
        $this->reportData->expects($this->once())
            ->method('prepareIntervalsCollection')
            ->with($this->bestsellersCollection, $this->anything(), $this->anything(), 'day');

        $this->callResolve($this->validArgs(['show_empty_rows' => true]));
    }

    public function testResolveDoesNotCallPrepareIntervalsCollectionByDefault(): void
    {
        $this->bestsellersCollection->method('getItems')->willReturn([]);
        $this->reportData->expects($this->never())->method('prepareIntervalsCollection');

        $this->callResolve($this->validArgs());
    }


    public function testResolveResolvesConfigurableChildToParentProduct(): void
    {
        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret-key');
        $this->helperEmailMarketing->method('getParentId')->willReturnCallback(
            static fn (int $childId) => $childId === 99 ? 50 : 0
        );

        $childProduct = $this->makeProduct('CHILD-SKU', 'http://example.com/child.html', null, 1);
        $parentProduct = $this->makeProduct('PARENT-SKU', 'http://example.com/parent.html', null, 1);

        $this->productRepository->method('getById')->willReturnCallback(
            static fn (int $id) => match ($id) {
                99 => $childProduct,
                50 => $parentProduct,
            }
        );

        $this->storeManager->method('getStore')->willReturn($this->makeStore('', 'USD'));

        $this->bestsellersCollection->method('getItems')->willReturn([
            $this->makeItem([
                'period'        => '2024-04-01',
                'product_id'    => 99,
                'product_name'  => 'Child Item',
                'product_price' => '5.00',
                'qty_ordered'   => 1,
            ]),
        ]);

        $result = $this->callResolve($this->validArgs());

        $row = $result['mpBestsellers'][0];
        // oriProduct is resolved via the item's own (child) id — sku comes from the child.
        $this->assertSame('CHILD-SKU', $row['product_sku']);
        // product is resolved via the parent id returned by getParentId() — url comes from the parent.
        $this->assertSame('http://example.com/parent.html', $row['product_url']);
    }

    public function testResolveReturnsEmptySkuAndUrlWhenProductRepositoryThrows(): void
    {
        $this->productRepository->method('getById')
            ->willThrowException(new NoSuchEntityException(__('not found')));
        $this->storeManager->method('getStore')->willReturn($this->makeStore('', 'USD'));

        $this->bestsellersCollection->method('getItems')->willReturn([
            $this->makeItem([
                'period'        => '2024-05-01',
                'product_id'    => 77,
                'product_name'  => 'Deleted Product',
                'product_price' => '1.00',
                'qty_ordered'   => 1,
            ]),
        ]);

        $result = $this->callResolve($this->validArgs());

        $this->assertSame([
            'period'            => '2024-05-01',
            'product_id'        => 77,
            'product_sku'       => '',
            'product_url'       => '',
            'product_image_url' => '',
            'product_name'      => 'Deleted Product',
            'product_price'     => '1.00',
            'qty_ordered'       => 1,
            'currency'          => 'USD',
        ], $result['mpBestsellers'][0]);
    }
}
