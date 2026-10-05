<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Plugin\Cart;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Shipping;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Plugin\Cart\ValidateVietNamWard;

/**
 * TASK-Z6SK3T — single fetchOne probe + per-request memo
 * (was CityLocaleCollection + getSize() COUNT per rate collection).
 *
 * RateRequest is a plain DataObject: use a real instance (magic get/set), not a mock.
 */
class ValidateVietNamWardTest extends TestCase
{
    private AdapterInterface&MockObject $adapter;

    private ResourceConnection&MockObject $resource;

    private ValidateVietNamWard $plugin;

    protected function setUp(): void
    {
        $this->adapter = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $this->adapter->method('select')->willReturn($select);

        $this->resource = $this->createMock(ResourceConnection::class);
        $this->resource->method('getConnection')->willReturn($this->adapter);
        $this->resource->method('getTableName')->willReturnArgument(0);

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->plugin = new ValidateVietNamWard($this->resource, $logger);
    }

    private function request(array $data): RateRequest
    {
        $request = new RateRequest();
        $request->setDestCountryId($data['country'] ?? 'VN');
        $request->setDestRegionId($data['region'] ?? '');
        $request->setDestCity($data['ward'] ?? '');
        return $request;
    }

    public function testSkipsNonVnCountry(): void
    {
        $this->adapter->expects($this->never())->method('fetchOne');

        $req = $this->request(['country' => 'US']);
        [$result] = $this->plugin->beforeCollectRates($this->createMock(Shipping::class), $req);
        $this->assertSame($req, $result);
    }

    public function testIncompleteAddressSkipsValidation(): void
    {
        $this->adapter->expects($this->never())->method('fetchOne');

        $this->plugin->beforeCollectRates($this->createMock(Shipping::class), $this->request([]));
    }

    public function testValidWardKeepsDestCity(): void
    {
        $req = new RateRequest();
        $req->setDestCountryId('VN');
        $req->setDestRegionId('1205');
        $req->setDestCity('Phuong Ben Nghe');
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn('1');

        [$result] = $this->plugin->beforeCollectRates($this->createMock(Shipping::class), $req);
        $this->assertSame('Phuong Ben Nghe', $result->getDestCity());
    }

    public function testInvalidWardNeutralisesDestCity(): void
    {
        $req = new RateRequest();
        $req->setDestCountryId('VN');
        $req->setDestRegionId('1205');
        $req->setDestCity('Invalid Ward');
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn(false);

        [$result] = $this->plugin->beforeCollectRates($this->createMock(Shipping::class), $req);
        $this->assertSame('', $result->getDestCity());
    }

    public function testMemoHitDoesNotRequery(): void
    {
        $req = new RateRequest();
        $req->setDestCountryId('VN');
        $req->setDestRegionId('1205');
        $req->setDestCity('Some Ward');
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn('1');

        $this->plugin->beforeCollectRates($this->createMock(Shipping::class), $req);
        $this->plugin->beforeCollectRates($this->createMock(Shipping::class), $req);
    }
}
