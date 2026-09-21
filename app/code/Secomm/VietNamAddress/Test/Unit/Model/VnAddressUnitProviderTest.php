<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Secomm\VietNamAddress\Model\VnAddressUnitProvider;

/**
 * TASK-9394A9 — unit provider hydration: getUnit / getChildren / countByScheme over the
 * historical reference table.
 */
class VnAddressUnitProviderTest extends TestCase
{
    private Mysql&MockObject $adapter;

    /** @var array<int, mixed> queued fetchRow results */
    private array $fetchRowQueue = [];
    /** @var array<int, mixed> queued fetchAll results */
    private array $fetchAllQueue = [];
    /** @var array<int, mixed> queued fetchOne results */
    private array $fetchOneQueue = [];

    private VnAddressUnitProvider $provider;

    protected function setUp(): void
    {
        $this->fetchRowQueue = [];
        $this->fetchAllQueue = [];
        $this->fetchOneQueue = [];

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();

        $this->adapter = $this->createMock(Mysql::class);
        $this->adapter->method('select')->willReturn($select);
        $this->adapter->method('fetchRow')->willReturnCallback(function () {
            return array_shift($this->fetchRowQueue) ?? false;
        });
        $this->adapter->method('fetchAll')->willReturnCallback(function () {
            return array_shift($this->fetchAllQueue) ?? [];
        });
        $this->adapter->method('fetchOne')->willReturnCallback(function () {
            return array_shift($this->fetchOneQueue) ?? false;
        });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => $name);

        $this->provider = new VnAddressUnitProvider($resource);
    }

    public function testGetUnitHydratesRow(): void
    {
        $this->fetchRowQueue = [[
            'scheme_code' => VnSchemes::VN_ADMIN_PRE_2025,
            'code' => 'VNAP25-A',
            'parent_code' => 'VNAP25-D1',
            'region_code' => '01',
            'level' => 3,
            'name_vi' => 'Yên Viên (Xã)',
            'name_en' => 'Yen Vien (Commune)',
        ]];

        $unit = $this->provider->getUnit(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-A');

        $this->assertNotNull($unit);
        $this->assertSame('VNAP25-A', $unit->getCode());
        $this->assertSame('VNAP25-D1', $unit->getParentCode());
        $this->assertSame(3, $unit->getLevel());
        $this->assertSame('Yên Viên (Xã)', $unit->getNameVi());
    }

    public function testGetUnitReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->provider->getUnit(VnSchemes::VN_ADMIN_2025, 'VNA25-GHOST'));
    }

    public function testGetChildrenHydratesList(): void
    {
        $this->fetchAllQueue = [[
            ['scheme_code' => 's', 'code' => 'VNA25-1', 'parent_code' => null, 'region_code' => '01', 'level' => 2, 'name_vi' => 'An Biên', 'name_en' => 'An Bien'],
            ['scheme_code' => 's', 'code' => 'VNA25-2', 'parent_code' => null, 'region_code' => '01', 'level' => 2, 'name_vi' => 'An Châu', 'name_en' => 'An Chau'],
        ]];

        $children = $this->provider->getChildren(VnSchemes::VN_ADMIN_2025, 'VNA25-PARENT');

        $this->assertCount(2, $children);
        $this->assertSame('An Biên', $children[0]->getNameVi());
    }

    public function testCountByScheme(): void
    {
        $this->fetchOneQueue = ['3355'];
        $this->assertSame(3355, $this->provider->countByScheme(VnSchemes::VN_ADMIN_2025));
    }

    /**
     * BUG-ZTGGYZ (U1) — lock the hierarchy CONTRACT: children are addressed by the
     * canonical `parent_code` column (portable unit_code), never by runtime ids.
     */
    public function testGetChildrenQueriesByParentCodeColumn(): void
    {
        $capturedWhere = [];
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function (string $column, mixed $value = null) use (&$capturedWhere, $select) {
            $capturedWhere[] = [$column, $value];

            return $select;
        });
        $select->method('order')->willReturnSelf();

        // Fresh adapter: stub registrations from setUp() would win over per-test overrides.
        $adapter = $this->createMock(Mysql::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('fetchAll')->willReturn([]);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => $name);
        $provider = new VnAddressUnitProvider($resource);

        $provider->getChildren(VnSchemes::VN_ADMIN_2025, 'VN-34');

        $this->assertContains(['scheme_code = ?', 'VN_ADMIN_2025'], $capturedWhere);
        $this->assertContains(['parent_code = ?', 'VN-34'], $capturedWhere);
        $this->assertNotContains(['region_code = ?', 'VN-34'], $capturedWhere, 'hierarchy edge is parent_code, not the region attribution');
    }
}
