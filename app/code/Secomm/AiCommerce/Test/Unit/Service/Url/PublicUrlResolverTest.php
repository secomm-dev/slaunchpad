<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Service\Url;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Service\Url\PublicUrlResolver;

class PublicUrlResolverTest extends TestCase
{
    /**
     * @var PublicUrlResolver
     */
    private $resolver;

    /**
     * @var AdapterInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $connection;

    /**
     * @var array fetched rows the fake connection returns
     */
    private array $rows = [];

    /**
     * @var array captured select WHERE fragments
     */
    private array $wheres = [];

    protected function setUp(): void
    {
        $this->rows = [];
        $this->wheres = [];

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')
            ->willReturnCallback(function ($condition, $value = null): Select {
                $this->wheres[] = [$condition, $value];

                return $this->createMock(Select::class);
            });

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchAll')->willReturnCallback(function (): array {
            return $this->rows;
        });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturn('url_rewrite');

        $this->resolver = new PublicUrlResolver($resource);
    }

    public function testBatchResolvesProducts(): void
    {
        $this->rows = [
            ['url_rewrite_id' => '11', 'entity_id' => '1', 'request_path' => 'a.html'],
            ['url_rewrite_id' => '12', 'entity_id' => '2', 'request_path' => 'b.html'],
        ];

        $result = $this->resolver->resolveMany('product', [1, 2], $this->store());

        $this->assertSame('https://shop.example/a.html', $result[1]['public_url']);
        $this->assertSame('https://shop.example/b.html', $result[2]['canonical_url']);
    }

    public function testMissingRewriteYieldNulls(): void
    {
        $result = $this->resolver->resolveMany('product', [7], $this->store());

        $this->assertSame(['public_url' => null, 'canonical_url' => null], $result[7]);
    }

    public function testOldestNonRedirectRowWins(): void
    {
        $this->rows = [
            ['url_rewrite_id' => '30', 'entity_id' => '5', 'request_path' => 'newer.html'],
            ['url_rewrite_id' => '20', 'entity_id' => '5', 'request_path' => 'winner.html'],
            ['url_rewrite_id' => '40', 'entity_id' => '5', 'request_path' => 'newest.html'],
        ];

        $result = $this->resolver->resolveMany('category', [5], $this->store());

        // Lowest url_rewrite_id wins ("oldest rewrite wins", LC-30 policy).
        $this->assertSame('https://shop.example/winner.html', $result[5]['public_url']);
    }

    public function testRedirectRowsExcludedByQuery(): void
    {
        $this->resolver->resolveMany('product', [9], $this->store());

        $redirectFilter = array_filter(
            $this->wheres,
            static fn (array $w): bool => str_contains((string) $w[0], 'redirect_type')
        );

        $this->assertCount(1, $redirectFilter);
        $this->assertSame(0, current($redirectFilter)[1]);
    }

    public function testStoreScopedQuery(): void
    {
        $this->resolver->resolveMany('product', [1], $this->store(3));

        $storeFilter = array_filter(
            $this->wheres,
            static fn (array $w): bool => str_contains((string) $w[0], 'store_id')
        );

        $this->assertSame(3, current($storeFilter)[1]);
    }

    public function testEntityIdListBoundedInSingleQuery(): void
    {
        $this->resolver->resolveMany('product', [1, 2, 3], $this->store());

        $inFilter = array_filter(
            $this->wheres,
            static fn (array $w): bool => str_contains((string) $w[0], 'entity_id IN')
        );

        $this->assertSame([[1, 2, 3]], array_column(array_values($inFilter), 1));
    }

    public function testBatchMatchesSingleResolverResult(): void
    {
        $this->rows = [
            ['url_rewrite_id' => '10', 'entity_id' => '4', 'request_path' => 'same.html'],
            ['url_rewrite_id' => '11', 'entity_id' => '6', 'request_path' => 'other.html'],
        ];

        $batch = $this->resolver->resolveMany('category', [4, 6], $this->store());
        $single4 = $this->resolver->getCategoryUrls(4, $this->store());

        $this->assertSame($batch[4], $single4);
        $this->assertSame(
            $this->resolver->resolveMany('category', [6], $this->store())[6],
            $this->resolver->getCategoryUrls(6, $this->store())
        );
    }

    public function testEmptyIdListPerformsNoQuery(): void
    {
        $this->connection->expects($this->never())->method('fetchAll');

        $this->assertSame([], $this->resolver->resolveMany('product', [], $this->store()));
    }

    /**
     * Store mock.
     *
     * @param int $id store id
     * @return Store|\PHPUnit\Framework\MockObject\MockObject
     */
    private function store(int $id = 1)
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getBaseUrl')->willReturn('https://shop.example/');

        return $store;
    }
}
