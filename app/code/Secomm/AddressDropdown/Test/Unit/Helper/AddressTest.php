<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Customer\Model\AddressFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Secomm\AddressDropdown\Helper\Address;
use Secomm\AddressDropdown\Model\CityModelFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * TASK-Z6SK3T — getCityNameByDefaultName executes ONE query per unique key (was two),
 * falls back explicitly on false/null misses and memoizes per request.
 */
class AddressTest extends TestCase
{
    private AdapterInterface&MockObject $adapter;

    private ResourceConnection&MockObject $resource;

    private Address $helper;

    protected function setUp(): void
    {
        $this->adapter = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $this->adapter->method('select')->willReturn($select);

        $this->resource = $this->createMock(ResourceConnection::class);
        $this->resource->method('getConnection')->willReturn($this->adapter);
        $this->resource->method('getTableName')->willReturnArgument(0);

        $this->helper = new Address(
            $this->createMock(Context::class),
            $this->resource,
            $this->createMock(AddressFactory::class),
            $this->createMock(CityModelFactory::class),
            $this->createMock(\Magento\Framework\Locale\ResolverInterface::class),
            $this->createMock(\Magento\Framework\Locale\Resolver::class),
            $this->createMock(\Magento\Framework\App\State::class)
        );
    }

    public function testExecutesSingleQueryAndReturnsMappedName(): void
    {
        $this->adapter->expects($this->once())
            ->method('fetchOne')
            ->willReturn('Phường Bến Nghé');

        $this->assertSame(
            'Phường Bến Nghé',
            $this->helper->getCityNameByDefaultName('Phuong Ben Nghe', 1205, 'vi_VN')
        );
    }

    public function testMissFallsBackToDefaultName(): void
    {
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn(false);

        $this->assertSame(
            'Unknown Ward',
            $this->helper->getCityNameByDefaultName('Unknown Ward', 1205, 'vi_VN')
        );
    }

    public function testNullResultIsTreatedAsMiss(): void
    {
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn(null);

        $this->assertSame(
            'Null Ward',
            $this->helper->getCityNameByDefaultName('Null Ward', 1205, 'vi_VN')
        );
    }

    public function testMemoizesPerRequestPerKey(): void
    {
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn('Phường Bến Nghé');

        $first = $this->helper->getCityNameByDefaultName('Phuong Ben Nghe', 1205, 'vi_VN');
        $second = $this->helper->getCityNameByDefaultName('Phuong Ben Nghe', 1205, 'vi_VN');

        $this->assertSame($first, $second);
    }

    public function testDifferentRegionBypassesMemo(): void
    {
        $this->adapter->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('Phường Bến Nghé', 'Phường Thảo Điền');

        $first = $this->helper->getCityNameByDefaultName('Same Name', 1205, 'vi_VN');
        $second = $this->helper->getCityNameByDefaultName('Same Name', 1222, 'vi_VN');

        $this->assertSame('Phường Bến Nghé', $first);
        $this->assertSame('Phường Thảo Điền', $second);
    }
}
