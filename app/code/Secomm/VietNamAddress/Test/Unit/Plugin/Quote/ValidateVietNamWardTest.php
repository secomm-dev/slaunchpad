<?php
/*
 * TASK-9EX975 Slice A: unit tests for the VN quote-save ward validator (admin order
 * create/edit). Covers the decision branches: invalid ward neutralised on shipping +
 * billing, valid ward kept, non-VN skip, incomplete address skip, non-Quote cart skip,
 * and non-fatal exception handling.
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Plugin\Quote;

use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollection;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory;
use Secomm\VietNamAddress\Plugin\Quote\ValidateVietNamWard;

class ValidateVietNamWardTest extends TestCase
{
    /**
     * TASK-ADT94K: the validator matches default_name OR the locale rname.name — the
     * mock wires that surface too.
     */
    private function baseCollection(): CityLocaleCollection
    {
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where'])
            ->getMock();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(Mysql::class);
        $connection->method('quoteInto')->willReturnCallback(
            static fn (string $text, mixed $value): string => 'q(' . $text . ')'
        );

        $collection = $this->getMockBuilder(CityLocaleCollection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addFieldToFilter', 'setPageSize', 'setCurPage', 'getSize', 'getSelect', 'getConnection'])
            ->getMock();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('setCurPage')->willReturnSelf();
        $collection->method('getSelect')->willReturn($select);
        $collection->method('getConnection')->willReturn($connection);

        return $collection;
    }

    private function collection(int $size): CityLocaleCollection
    {
        $collection = $this->baseCollection();
        $collection->method('getSize')->willReturn($size);

        return $collection;
    }

    private function factory(?CityLocaleCollection $collection): CityLocaleCollectionFactory
    {
        $factory = $this->getMockBuilder(CityLocaleCollectionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        if ($collection === null) {
            $factory->expects(self::never())->method('create');
        } else {
            $factory->method('create')->willReturn($collection);
        }

        return $factory;
    }

    private function logger(): LoggerInterface
    {
        return $this->getMockBuilder(LoggerInterface::class)->getMock();
    }

    private function address(string $countryId, string $regionId, string $city): Address
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCountryId', 'getRegionId', 'getCity', 'setCity'])
            ->getMock();
        $address->method('getCountryId')->willReturn($countryId);
        $address->method('getRegionId')->willReturn($regionId);
        $address->method('getCity')->willReturn($city);

        return $address;
    }

    private function cart(Address $billing, Address $shipping): Quote
    {
        $cart = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBillingAddress', 'getShippingAddress'])
            ->getMock();
        $cart->method('getBillingAddress')->willReturn($billing);
        $cart->method('getShippingAddress')->willReturn($shipping);

        return $cart;
    }

    public function testNeutralisesInvalidWardOnShippingAddress(): void
    {
        $billing = $this->address('VN', '1190', 'An Phu');
        $shipping = $this->address('VN', '1190', 'Bogus Ward');
        $shipping->expects(self::once())->method('setCity')->with('');

        $plugin = new ValidateVietNamWard($this->factory($this->collection(0)), $this->logger());
        $plugin->beforeSave($this->createStub(CartRepositoryInterface::class), $this->cart($billing, $shipping));
    }

    public function testNeutralisesInvalidWardOnBillingAddress(): void
    {
        $billing = $this->address('VN', '1190', 'Bogus Ward');
        $billing->expects(self::once())->method('setCity')->with('');
        $shipping = $this->address('VN', '1190', 'An Phu');

        $plugin = new ValidateVietNamWard($this->factory($this->collection(0)), $this->logger());
        $plugin->beforeSave($this->createStub(CartRepositoryInterface::class), $this->cart($billing, $shipping));
    }

    public function testKeepsValidWardsOnBothAddresses(): void
    {
        $billing = $this->address('VN', '1190', 'An Phu');
        $billing->expects(self::never())->method('setCity');
        $shipping = $this->address('VN', '1190', 'An Phu');
        $shipping->expects(self::never())->method('setCity');

        $plugin = new ValidateVietNamWard($this->factory($this->collection(1)), $this->logger());
        $plugin->beforeSave($this->createStub(CartRepositoryInterface::class), $this->cart($billing, $shipping));
    }

    public function testIgnoresNonVnCountry(): void
    {
        $billing = $this->address('US', '12', 'Los Angeles');
        $shipping = $this->address('US', '12', 'Los Angeles');

        // create() must never be called for non-VN -> factory expects never.
        $plugin = new ValidateVietNamWard($this->factory(null), $this->logger());
        $plugin->beforeSave($this->createStub(CartRepositoryInterface::class), $this->cart($billing, $shipping));
    }

    public function testIgnoresIncompleteAddress(): void
    {
        $billing = $this->address('VN', '', 'An Phu');
        $shipping = $this->address('VN', '1190', '');
        $shipping->expects(self::never())->method('setCity');

        $plugin = new ValidateVietNamWard($this->factory(null), $this->logger());
        $plugin->beforeSave($this->createStub(CartRepositoryInterface::class), $this->cart($billing, $shipping));
    }

    public function testSkipsNonQuoteCartWithoutTouchingCollection(): void
    {
        // A bare CartInterface (e.g. a future non-Quote implementation) is skipped.
        $plugin = new ValidateVietNamWard($this->factory(null), $this->logger());
        $plugin->beforeSave($this->createStub(CartRepositoryInterface::class), $this->createMock(CartInterface::class));
    }

    public function testDoesNotBreakSaveWhenCollectionThrows(): void
    {
        $collection = $this->baseCollection();
        $collection->method('getSize')->willThrowException(new \RuntimeException('db down'));

        $logger = $this->logger();
        $logger->expects(self::once())->method('error');

        $billing = $this->address('VN', '1190', 'An Phu');
        $shipping = $this->address('VN', '1190', 'An Phu');

        $plugin = new ValidateVietNamWard($this->factory($collection), $logger);

        // Must not rethrow: a validation fault never blocks the quote save.
        $plugin->beforeSave(
            $this->createStub(CartRepositoryInterface::class),
            $this->cart($billing, $shipping)
        );
    }
}
