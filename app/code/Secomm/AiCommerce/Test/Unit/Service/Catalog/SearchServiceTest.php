<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Service\Catalog;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Customer\Model\Group;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Service\Catalog\SearchService;
use Secomm\AiCommerce\Service\Input\SearchQueryParser;
use Secomm\AiCommerce\Service\Inventory\Availability;
use Secomm\AiCommerce\Service\InvalidParameterException;
use Secomm\AiCommerce\Service\Response\ProductDto;
use Secomm\AiCommerce\Service\Response\SearchResultDto;
use Secomm\AiCommerce\Service\Url\PublicUrlResolver;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Unit tests for the storefront collection usage in SearchService.
 *
 * Regression coverage for the live-proven defects: price sorting 500s
 * (missing guest price context on the Elasticsuite collection), price_min
 * silently dropped when combined with price_max (addFieldToFilter stores
 * filters keyed by mapped field name, so a second call on 'price'
 * overwrites the first), and the category filter never reaching the engine
 * when issued through the SQL-oriented addCategoriesFilter API.
 */
class SearchServiceTest extends TestCase
{
    private const int STORE_ID = 1;
    private const int WEBSITE_ID = 2;

    /**
     * @var CollectionFactory|MockObject
     */
    private $collectionFactory;

    /**
     * @var Collection|MockObject
     */
    private $collection;

    /**
     * @var CategoryRepositoryInterface|MockObject
     */
    private $categoryRepository;

    /**
     * @var Availability|MockObject
     */
    private $availability;

    /**
     * @var StoreInterface|MockObject
     */
    private $store;

    /**
     * Service under test.
     */
    private SearchService $service;

    /**
     * Items served by the collection mock (mutable per test).
     *
     * @var mixed[]
     */
    private array $collectionItems = [];

    /**
     * Wire a real parser (allowlist contract) around mocked collaborators.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->collectionItems = [];
        $this->collection = $this->createMock(Collection::class);
        $this->collection->method('getItems')->willReturnCallback(fn (): array => $this->collectionItems);
        $this->collection->method('getSize')->willReturnCallback(fn (): int => count($this->collectionItems));
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->collectionFactory->method('create')->willReturn($this->collection);

        $config = $this->createMock(Config::class);
        $config->method('getMaxPageSize')->willReturn(50);
        $config->method('getFilterAllowlist')->willReturn(['color', 'size']);

        $this->availability = $this->createMock(Availability::class);
        $this->availability->method('getStatuses')->willReturn([]);

        $productDto = $this->createMock(ProductDto::class);
        $productDto->method('toSummaryArray')->willReturnCallback(
            static fn (
                ProductInterface $product,
                StoreInterface $store,
                string $status,
                array $urls
            ): array => [
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'availability' => ['status' => $status],
            ]
        );

        $searchResultDto = $this->createMock(SearchResultDto::class);
        $searchResultDto->method('toArray')->willReturnCallback(
            static fn (array $items, int $totalCount, int $page, int $pageSize): array => [
                'total_count' => $totalCount,
                'page' => $page,
                'page_size' => $pageSize,
                'items' => $items,
            ]
        );

        $urlResolver = $this->createMock(PublicUrlResolver::class);
        $urlResolver->method('resolveMany')->willReturn([]);

        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->store = $this->createMock(StoreInterface::class);
        $this->store->method('getId')->willReturn(self::STORE_ID);
        $this->store->method('getWebsiteId')->willReturn(self::WEBSITE_ID);

        $this->service = new SearchService(
            $this->collectionFactory,
            new SearchQueryParser($config),
            $this->availability,
            $productDto,
            $searchResultDto,
            $urlResolver,
            $this->categoryRepository
        );
    }

    /**
     * Assert the engine request carried a filter for a field.
     *
     * @param array<int, array{0: string, 1: mixed}> $calls captured addFieldToFilter calls
     * @param string $field field name
     * @param mixed $condition expected condition
     * @return void
     */
    private static function assertFilterCall(array $calls, string $field, mixed $condition): void
    {
        foreach ($calls as [$calledField, $calledCondition]) {
            if ($calledField === $field && $calledCondition == $condition) {
                return;
            }
        }

        self::fail(sprintf(
            'Expected addFieldToFilter(%s, %s); captured: %s',
            $field,
            json_encode($condition),
            json_encode($calls)
        ));
    }

    /**
     * BUG 1 regression: price sorting requires the guest price context.
     *
     * The Smile Elasticsuite collection reads _productLimitationFilters
     * unguarded for the nested price sort (customer group nested filter);
     * the anonymous read layer must pin the guest group + store website
     * through the storefront addPriceData API.
     */
    public function testPriceSortAppliesGuestPriceContext(): void
    {
        $this->collection->expects($this->once())
            ->method('addPriceData')
            ->with(Group::NOT_LOGGED_IN_ID, self::WEBSITE_ID);

        $this->collection->expects($this->once())
            ->method('setOrder')
            ->with('price', 'asc');

        $result = $this->service->search($this->store, ['sort' => 'price_asc']);

        $this->assertSame([], $result['items']);
        $this->assertSame(0, $result['total_count']);
    }

    public function testPriceSortDescendingSetsOrder(): void
    {
        $this->collection->expects($this->once())
            ->method('setOrder')
            ->with('price', 'desc');

        $this->service->search($this->store, ['sort' => 'price_desc']);
    }

    public function testNameSortAscendingSetsOrder(): void
    {
        $this->collection->expects($this->once())
            ->method('setOrder')
            ->with('name', 'asc');

        $this->service->search($this->store, ['sort' => 'name_asc']);
    }

    public function testNameSortDescendingSetsOrder(): void
    {
        $this->collection->expects($this->once())
            ->method('setOrder')
            ->with('name', 'desc');

        $this->service->search($this->store, ['sort' => 'name_desc']);
    }

    public function testRelevanceIssuesNoExplicitOrder(): void
    {
        $this->collection->expects($this->never())
            ->method('setOrder');

        $this->service->search($this->store, []);
    }

    /**
     * BUG 2 regression: price_min + price_max MUST be issued as ONE
     * addFieldToFilter call. The collection stores filters keyed by mapped
     * field name, so a second call on 'price' overwrites the first bound.
     */
    public function testPriceBoundsIssuedAsSingleCombinedFilter(): void
    {
        $calls = [];
        $this->collection->expects($this->once())
            ->method('addFieldToFilter')
            ->willReturnCallback(function ($field, $condition) use (&$calls) {
                $calls[] = [$field, $condition];
                return $this->collection;
            });

        $this->service->search($this->store, [
            'price_min' => '4875000',
            'price_max' => '1375000',
        ]);

        self::assertFilterCall($calls, 'price', ['gteq' => 4875000.0, 'lteq' => 1375000.0]);
        self::assertCount(
            1,
            array_filter($calls, static fn (array $c): bool => $c[0] === 'price'),
            'price filter must be issued exactly once even when both bounds are set'
        );
    }

    public function testPriceMinOnlyIssuesGteqOnly(): void
    {
        $calls = [];
        $this->collection->expects($this->once())
            ->method('addFieldToFilter')
            ->willReturnCallback(function ($field, $condition) use (&$calls) {
                $calls[] = [$field, $condition];
                return $this->collection;
            });

        $this->service->search($this->store, ['price_min' => '4875000']);

        self::assertFilterCall($calls, 'price', ['gteq' => 4875000.0]);
    }

    public function testPriceMaxOnlyIssuesLteqOnly(): void
    {
        $calls = [];
        $this->collection->expects($this->once())
            ->method('addFieldToFilter')
            ->willReturnCallback(function ($field, $condition) use (&$calls) {
                $calls[] = [$field, $condition];
                return $this->collection;
            });

        $this->service->search($this->store, ['price_max' => '1375000']);

        self::assertFilterCall($calls, 'price', ['lteq' => 1375000.0]);
    }

    /**
     * BUG 3 regression: category filtering must go through the engine-level
     * addCategoryFilter API with a resolved category model — never through
     * the SQL-oriented addCategoriesFilter, which the Elasticsuite
     * collection silently ignores (live-proven full-catalog leak).
     */
    public function testCategoryFilterResolvesAndAppliesCategory(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(41);
        $this->categoryRepository->expects($this->once())
            ->method('get')
            ->with(41, self::STORE_ID)
            ->willReturn($category);

        $this->collection->expects($this->once())
            ->method('addCategoryFilter')
            ->with($this->identicalTo($category));

        $this->service->search($this->store, ['category' => '41']);
    }

    public function testUnknownCategoryIsRejectedAsInvalidParameter(): void
    {
        $this->categoryRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Category does not exist.')));

        $this->collection->expects($this->never())
            ->method('addCategoryFilter');

        $this->expectException(InvalidParameterException::class);

        $this->service->search($this->store, ['category' => '999999']);
    }

    public function testKeywordSearchAddsSearchFilter(): void
    {
        $this->collection->expects($this->once())
            ->method('addSearchFilter')
            ->with('terra');

        $this->service->search($this->store, ['q' => 'terra']);
    }

    public function testBrowseOmitsSearchFilter(): void
    {
        $this->collection->expects($this->never())
            ->method('addSearchFilter');

        $this->service->search($this->store, []);
    }

    public function testPaginationApplied(): void
    {
        $this->collection->expects($this->once())->method('setCurPage')->with(2);
        $this->collection->expects($this->once())->method('setPageSize')->with(10);

        $this->service->search($this->store, ['page' => '2', 'page_size' => '10']);
    }

    public function testDefaultPaginationApplied(): void
    {
        $this->collection->expects($this->once())->method('setCurPage')->with(1);
        $this->collection->expects($this->once())->method('setPageSize')->with(20);

        $this->service->search($this->store, []);
    }

    public function testAllowlistedAttributeFilterApplied(): void
    {
        $calls = [];
        $this->collection->expects($this->once())
            ->method('addFieldToFilter')
            ->willReturnCallback(function ($field, $condition) use (&$calls) {
                $calls[] = [$field, $condition];
                return $this->collection;
            });

        $this->service->search($this->store, ['filter' => ['color' => '222']]);

        self::assertFilterCall($calls, 'color', ['eq' => '222']);
    }

    public function testNonAllowlistedAttributeIsRejectedBeforeEngine(): void
    {
        $this->collectionFactory->expects($this->never())
            ->method('create');

        $this->expectException(InvalidParameterException::class);

        $this->service->search($this->store, ['filter' => ['material' => 'wood']]);
    }

    public function testCombinedQueryAppliesAllDimensions(): void
    {
        $calls = [];
        $this->collection->expects($this->exactly(2))
            ->method('addFieldToFilter')
            ->willReturnCallback(function ($field, $condition) use (&$calls) {
                $calls[] = [$field, $condition];
                return $this->collection;
            });
        $this->collection->expects($this->once())
            ->method('addSearchFilter')
            ->with('terra');
        $this->collection->expects($this->once())
            ->method('setOrder')
            ->with('name', 'asc');
        $this->collection->expects($this->once())
            ->method('setCurPage')
            ->with(1);
        $this->collection->expects($this->once())
            ->method('setPageSize')
            ->with(10);

        $result = $this->service->search($this->store, [
            'q' => 'terra',
            'price_min' => '100000',
            'price_max' => '1000000',
            'sort' => 'name_asc',
            'page' => '1',
            'page_size' => '10',
            'filter' => ['color' => '222'],
        ]);

        self::assertFilterCall($calls, 'price', ['gteq' => 100000.0, 'lteq' => 1000000.0]);
        self::assertFilterCall($calls, 'color', ['eq' => '222']);

        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['page_size']);
    }

    public function testSearchResultsAreMappedThroughDtos(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('dinnerware-terra-collection');
        $product->method('getId')->willReturn(10);
        $this->collectionItems = [$product];

        $result = $this->service->search($this->store, ['q' => 'terra']);

        $this->assertSame(1, $result['total_count']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('dinnerware-terra-collection', $result['items'][0]['sku']);
    }

    public function testNonProductItemsAreDropped(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('x');
        $product->method('getId')->willReturn(11);
        $this->collectionItems = [null, $product];

        $result = $this->service->search($this->store, []);

        $this->assertCount(1, $result['items']);
    }
}
