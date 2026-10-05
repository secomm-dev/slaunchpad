<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Select\SelectRenderer;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Model\LocationHierarchyProvider;
use Secomm\AddressDropdown\Model\ResourceModel\CitySort;

/**
 * TASK-7HVGAB / TASK-Z6SK3T — hierarchy listing must order by the canonical vi-aware
 * sort rule: REPLACE-normalized COALESCE(localized name, default_name) ASC, city_id ASC.
 */
class LocationHierarchyProviderTest extends TestCase
{
    private ?Select $childrenSelect = null;

    private LocationHierarchyProvider $provider;

    protected function setUp(): void
    {
        $childrenSelect = &$this->childrenSelect;

        $adapter = $this->createAdapterMock();
        $adapter->method('select')->willReturnCallback(
            fn (): Select => new Select(
                $this->createAdapterMock(),
                $this->getMockBuilder(SelectRenderer::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            )
        );
        // The listing statement is the one handed to fetchAll — capture it there.
        $adapter->method('fetchAll')->willReturnCallback(
            function (Select $select) use (&$childrenSelect): array {
                $childrenSelect = $select;

                return [];
            }
        );
        $adapter->method('fetchOne')->willReturn(null);
        // fetchLight/fetchNode lookups: a fixed parent row so getChildLocations proceeds.
        $adapter->method('fetchRow')->willReturnCallback(
            fn (Select $select): array => [
                'city_id' => 42,
                'region_id' => 5,
                'parent_city_id' => null,
                'default_name' => 'Ward A',
                'name' => 'Phường A',
            ]
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $localeResolver = $this->createMock(LocaleResolver::class);
        $localeResolver->method('getLocale')->willReturn('vi_VN');

        $this->provider = new LocationHierarchyProvider($resource, $localeResolver);
    }

    private function createAdapterMock(): Mysql&MockObject
    {
        return $this->getMockBuilder(Mysql::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function childrenOrdersAsString(): string
    {
        // Zend stores the ORDER part as a flat list of expression objects.
        $orders = $this->childrenSelect === null
            ? []
            : $this->childrenSelect->getPart(Select::ORDER);

        return trim(implode(' ', array_map('strval', $orders)));
    }

    public function testRootLocationsUseCanonicalOrderWithDefaultNameFallback(): void
    {
        $this->provider->getRootLocations(1185, 'vn_admin_2025');

        $orders = $this->childrenOrdersAsString();
        // TASK-Z6SK3T: canonical key uses the real Vietnamese collation (…D, Đ, E…).
        $this->assertStringContainsString(
            'CONVERT(COALESCE(n.name, c.default_name) USING utf8mb4)',
            $orders,
            'Sort key must be the effective displayed label (localized name, fallback default_name).'
        );
        $this->assertStringContainsString(
            CitySort::COLLATION,
            $orders,
            'Sort must use the vi collation (d8bad508 regression guard).'
        );
        $this->assertStringContainsString(
            'c.city_id ASC',
            $orders,
            'Duplicate/equivalent names must stay deterministic via the city_id tie-breaker.'
        );
    }

    public function testChildLocationsUseVietnameseAlphabetOrder(): void
    {
        // TASK-Z6SK3T — supersedes the d8bad508 "language-agnostic" guard AND the first
        // REPLACE-based restoration: the canonical key must collate as Vietnamese
        // (Đ = own letter after the full D block), not the bare column collation.
        $this->provider->getChildLocations(42, 'vn_admin_2025');

        $orders = $this->childrenOrdersAsString();
        $this->assertStringContainsString('COLLATE ' . CitySort::COLLATION, $orders);
        $this->assertStringNotContainsString('utf8mb4_general_ci', $orders);
    }
}
