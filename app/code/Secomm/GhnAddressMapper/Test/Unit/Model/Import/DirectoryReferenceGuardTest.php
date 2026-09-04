<?php
declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Test\Unit\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\GhnAddressMapper\Model\Import\DirectoryReferenceGuard;

/**
 * DEC-FEATYA2C0W-004 (D7) / TASK-Q4B98P — GHN registration of the VN scheme-swap guard:
 * blocks when the mapping table still keys on the disappearing directory PKs; silent no-op
 * when the table is absent (GHN mapping module not installed data-wise).
 */
class DirectoryReferenceGuardTest extends TestCase
{
    private Mysql&MockObject $adapter;
    private ResourceConnection&MockObject $resource;
    private DirectoryReferenceGuard $guard;

    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $this->adapter = $this->createMock(Mysql::class);
        $this->adapter->method('select')->willReturn($select);

        $this->resource = $this->createMock(ResourceConnection::class);
        $this->resource->method('getConnection')->willReturn($this->adapter);
        $this->resource->method('getTableName')->willReturnCallback(static fn (string $n): string => $n);

        $this->guard = new DirectoryReferenceGuard($this->resource);
    }

    public function testEmptyIdSetsPassWithoutQuery(): void
    {
        $this->adapter->expects($this->never())->method('fetchOne');

        $this->guard->assertSafe([], []);
    }

    public function testMissingTableIsSilentlySafe(): void
    {
        $this->adapter->method('isTableExists')->willReturn(false);
        $this->adapter->expects($this->never())->method('fetchOne');

        $this->guard->assertSafe([5], [77]);
    }

    public function testNoReferencesPasses(): void
    {
        $this->adapter->method('isTableExists')->willReturn(true);
        $this->adapter->method('fetchOne')->willReturn('0');

        $this->guard->assertSafe([5], [77]);
        $this->addToAssertionCount(1); // no exception
    }

    public function testRegionReferencesAbortWithTableAndColumnNamed(): void
    {
        $this->adapter->method('isTableExists')->willReturn(true);
        $this->adapter->method('fetchOne')->willReturn('3');

        try {
            $this->guard->assertSafe([5], []);
            $this->fail('Expected LocalizedException.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('secomm_ghn_address_mapping_location', $e->getMessage());
            $this->assertStringContainsString('region_id', $e->getMessage());
            $this->assertStringContainsString('3 row(s)', $e->getMessage());
        }
    }

    public function testCityReferencesAbort(): void
    {
        $this->adapter->method('isTableExists')->willReturn(true);
        // first fetchOne (region_id) = 0, second (city_id) = 9
        $this->adapter->method('fetchOne')->willReturnOnConsecutiveCalls('0', '9');

        try {
            $this->guard->assertSafe([5], [77]);
            $this->fail('Expected LocalizedException.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('city_id', $e->getMessage());
        }
    }

    public function testGuardNameIsStable(): void
    {
        $this->assertSame('Secomm_GhnAddressMapper (secomm_ghn_address_mapping_location)', $this->guard->getName());
    }
}
