<?php
/*
 * TASK-9EX975 Slice A: unit tests for the VN MSI source-save ward validator.
 * Covers the decision branches: invalid ward neutralised, valid ward kept, non-VN skip,
 * incomplete address skip, and non-fatal exception handling.
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Plugin\Inventory;

use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollection;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory;
use Secomm\VietNamAddress\Plugin\Inventory\ValidateVietNamWard;

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

    private function source(string $countryId, ?int $regionId, string $city): SourceInterface
    {
        $source = $this->createMock(SourceInterface::class);
        $source->method('getCountryId')->willReturn($countryId);
        $source->method('getRegionId')->willReturn($regionId);
        $source->method('getCity')->willReturn($city);

        return $source;
    }

    public function testNeutralisesInvalidWard(): void
    {
        $source = $this->source('VN', 1190, 'Bogus Ward');
        $source->expects(self::once())->method('setCity')->with('');

        $plugin = new ValidateVietNamWard($this->factory($this->collection(0)), $this->logger());
        $plugin->beforeSave($this->createStub(SourceRepositoryInterface::class), $source);
    }

    public function testKeepsValidWard(): void
    {
        $source = $this->source('VN', 1190, 'An Phu');
        $source->expects(self::never())->method('setCity');

        $plugin = new ValidateVietNamWard($this->factory($this->collection(1)), $this->logger());
        $plugin->beforeSave($this->createStub(SourceRepositoryInterface::class), $source);
    }

    public function testIgnoresNonVnCountry(): void
    {
        $source = $this->source('US', 12, 'Los Angeles');
        $source->expects(self::never())->method('setCity');

        // create() must never be called for non-VN -> factory expects never.
        $plugin = new ValidateVietNamWard($this->factory(null), $this->logger());
        $plugin->beforeSave($this->createStub(SourceRepositoryInterface::class), $source);
    }

    public function testIgnoresIncompleteAddress(): void
    {
        $source = $this->source('VN', null, 'An Phu');
        $source->expects(self::never())->method('setCity');

        $plugin = new ValidateVietNamWard($this->factory(null), $this->logger());
        $plugin->beforeSave($this->createStub(SourceRepositoryInterface::class), $source);
    }

    public function testDoesNotBreakSaveWhenCollectionThrows(): void
    {
        $collection = $this->baseCollection();
        $collection->method('getSize')->willThrowException(new \RuntimeException('db down'));

        $logger = $this->logger();
        $logger->expects(self::once())->method('error');

        $source = $this->source('VN', 1190, 'An Phu');
        $source->expects(self::never())->method('setCity');

        $plugin = new ValidateVietNamWard($this->factory($collection), $logger);

        // Must not rethrow: a validation fault never blocks the source save.
        $plugin->beforeSave($this->createStub(SourceRepositoryInterface::class), $source);
    }
}
