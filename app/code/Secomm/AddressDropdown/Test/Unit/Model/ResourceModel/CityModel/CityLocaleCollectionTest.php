<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model\ResourceModel\CityModel;

use Magento\Framework\App\State;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Select\SelectRenderer;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Model\DataStorage;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollection;
use Secomm\AddressDropdown\Model\ResourceModel\CitySort;

/**
 * TASK-7HVGAB — canonical generic sort contract of the locale city listing:
 * ORDER BY the canonical vi-aware key (TASK-Z6SK3T): REPLACE-normalized
 * COALESCE(localized name, default_name) ASC (Đ/đ → D/d), city_id ASC — deterministic.
 */
class CityLocaleCollectionTest extends TestCase
{
    private CityLocaleCollection $collection;

    private Select $select;

    protected function setUp(): void
    {
        $this->select = new Select(
            $this->createAdapterMock(),
            $this->getMockBuilder(SelectRenderer::class)
                ->disableOriginalConstructor()
                ->getMock()
        );

        $adapter = $this->createAdapterMock();
        $adapter->method('select')->willReturn($this->select);

        $resource = $this->createMock(AbstractDb::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getMainTable')->willReturn('directory_region_city');
        $resource->method('getTable')->willReturnArgument(0);

        $fetchStrategy = $this->createMock(FetchStrategyInterface::class);
        $fetchStrategy->method('fetchAll')->willReturn([
            ['city_id' => 2, 'default_name' => 'Ward B', 'name' => null],
            ['city_id' => 1, 'default_name' => 'Ward A', 'name' => 'Phường A'],
        ]);

        $localeResolver = $this->createMock(LocaleResolver::class);
        $localeResolver->method('getLocale')->willReturn('vi_VN');

        $entityFactory = $this->createMock(EntityFactoryInterface::class);
        // AbstractModel requires constructor dependencies via DI; collection rows arrive
        // through addData() afterwards, so a constructor-less instance is sufficient here.
        $entityFactory->method('create')->willReturnCallback(
            fn (string $class): object => (new \ReflectionClass($class))->newInstanceWithoutConstructor()
        );

        $this->collection = new CityLocaleCollection(
            $entityFactory,
            $this->createMock(\Psr\Log\LoggerInterface::class),
            $fetchStrategy,
            $this->createMock(ManagerInterface::class),
            $localeResolver,
            $this->createMock(DataStorage::class),
            $this->createMock(State::class),
            null,
            $resource
        );
    }

    private function createAdapterMock(): Mysql&MockObject
    {
        return $this->getMockBuilder(Mysql::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * The ORDER BY parts rendered from the canonical generic sort rule.
     * Zend stores the ORDER part as a flat list of expression objects.
     */
    private function orderPartsAsString(): string
    {
        $orders = $this->select->getPart(Select::ORDER);

        return trim(implode(' ', array_map('strval', $orders)));
    }

    public function testAppliesCanonicalOrderWithDefaultNameFallback(): void
    {
        $this->collection->load();

        $orders = $this->orderPartsAsString();
        // TASK-Z6SK3T: the canonical key uses the real Vietnamese collation — Đ is its
        // own letter after the full D block (…D, Đ, E…), tone marks folded.
        $this->assertStringContainsString(
            'CONVERT(COALESCE(rname.name, main_table.default_name) USING utf8mb4)',
            $orders,
            'Sort key must be the effective displayed label (localized name, fallback default_name).'
        );
        $this->assertStringContainsString(
            CitySort::COLLATION,
            $orders,
            'Sort must use the vi collation (d8bad508 regression guard).'
        );
        $this->assertStringContainsString(
            'main_table.city_id ASC',
            $orders,
            'Duplicate/equivalent names must stay deterministic via the city_id tie-breaker.'
        );
    }

    public function testJoinsLocalizedNamesByStoreLocale(): void
    {
        $this->collection->load();

        $from = $this->select->getPart(Select::FROM);
        $this->assertArrayHasKey('rname', $from, 'Localized names table must be joined.');
        $this->assertSame('directory_region_city_name', $from['rname']['tableName']);
        $this->assertStringContainsString(':region_locale', (string)$from['rname']['joinCondition']);
    }

    public function testOrderExpressionUsesVietnameseAlphabetCollation(): void
    {
        // TASK-Z6SK3T — supersedes the d8bad508 "language-agnostic" guard AND the first
        // REPLACE-based restoration: the canonical key must collate as Vietnamese (Đ = own
        // letter after D), not by the bare utf8mb4_general_ci column collation.
        $this->collection->load();

        $orders = $this->orderPartsAsString();
        $this->assertStringContainsString(
            'COLLATE ' . CitySort::COLLATION,
            $orders,
            'Sort key must use the Vietnamese collation.'
        );
        $this->assertStringNotContainsString('utf8mb4_general_ci', $orders);
    }

    public function testLoadHydratesItemsFromRows(): void
    {
        $this->collection->load();

        $this->assertCount(2, $this->collection);
        $this->assertSame('Ward B', $this->collection->getFirstItem()->getDefaultName());
    }
}
