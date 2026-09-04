<?php
declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Import\DirectoryReferenceGuard;

/**
 * DEC-FEATYA2C0W-004 (D7) / TASK-Q4B98P — GHTK registration of the VN scheme-swap guard:
 * blocks when the address map still keys on the disappearing directory PKs (region_id,
 * ward_id); silent no-op when the table is absent.
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
        $this->addToAssertionCount(1);
    }

    public function testRegionReferencesAbortWithTableAndColumnNamed(): void
    {
        $this->adapter->method('isTableExists')->willReturn(true);
        $this->adapter->method('fetchOne')->willReturn('4');

        try {
            $this->guard->assertSafe([5], []);
            $this->fail('Expected LocalizedException.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('secomm_ghtk_address_map', $e->getMessage());
            $this->assertStringContainsString('region_id', $e->getMessage());
            $this->assertStringContainsString('4 row(s)', $e->getMessage());
        }
    }

    public function testWardReferencesAbort(): void
    {
        $this->adapter->method('isTableExists')->willReturn(true);
        $this->adapter->method('fetchOne')->willReturnOnConsecutiveCalls('0', '7');

        try {
            $this->guard->assertSafe([5], [77]);
            $this->fail('Expected LocalizedException.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('ward_id', $e->getMessage());
        }
    }

    public function testGuardNameIsStable(): void
    {
        $this->assertSame('Secomm_Ghtk (secomm_ghtk_address_map)', $this->guard->getName());
    }
}
