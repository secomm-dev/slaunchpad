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

/**
 * TASK-7HVGAB — hierarchy listing must order by the canonical generic sort rule:
 * COALESCE(localized name, default_name) ASC, city_id ASC — language-agnostic.
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
        $this->assertStringContainsString(
            'COALESCE(n.name, c.default_name) ASC',
            $orders,
            'Sort key must be the effective displayed label (localized name, fallback default_name).'
        );
        $this->assertStringContainsString(
            'c.city_id ASC',
            $orders,
            'Duplicate/equivalent names must stay deterministic via the city_id tie-breaker.'
        );
    }

    public function testChildLocationsUseCanonicalOrderAndStayLanguageAgnostic(): void
    {
        $this->provider->getChildLocations(42, 'vn_admin_2025');

        $orders = $this->childrenOrdersAsString();
        $this->assertStringContainsString('COALESCE(n.name, c.default_name) ASC', $orders);
        $this->assertDoesNotMatchRegularExpression(
            '/[^\x20-\x7E]/',
            $orders,
            'No locale-specific characters (e.g. Vietnamese letters) may appear in the sort rule.'
        );
        $this->assertStringNotContainsString('REPLACE(', $orders);
        $this->assertStringNotContainsString('CONVERT(', $orders);
        $this->assertStringNotContainsString('COLLATE', $orders);
    }
}
