<?php
/*
 * SL-013 / FEAT-007 (DEC-019/025): unit tests for the VN config-save ward validator.
 * Covers the decision branches: invalid ward neutralised, valid ward kept, non-VN skip,
 * wrong section skip, incomplete address skip, and non-fatal exception handling.
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Plugin\Adminhtml\Config;

use Magento\Config\Model\Config;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollection;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory;
use Secomm\VietNamAddress\Plugin\Adminhtml\Config\ValidateVietNamWard;

class ValidateVietNamWardTest extends TestCase
{
    /**
     * Build a real (unconstructed) Config subject so the DataObject magic getters/setters
     * (getSection/getGroups/setGroups) work without PHPUnit stubbing __call. A generated
     * PHPUnit mock would stub __call and break the magic; reflection preserves it.
     */
    private function subject(string $section, array $groups): Config
    {
        $subject = (new \ReflectionClass(Config::class))->newInstanceWithoutConstructor();
        $subject->setData('section', $section);
        $subject->setData('groups', $groups);

        return $subject;
    }

    /**
     * TASK-ADT94K: the validator now also matches the locale name via
     * getSelect()->where(default_name OR rname.name) — the mock wires that surface too.
     */
    private function collection(int $size): CityLocaleCollection
    {
        $collection = $this->baseCollection();
        $collection->method('getSize')->willReturn($size);

        return $collection;
    }

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

    private function vnGroups(string $city, string $regionId = '1190'): array
    {
        return [
            'store_information' => [
                'fields' => [
                    'country_id' => ['value' => 'VN'],
                    'region_id' => ['value' => $regionId],
                    'city' => ['value' => $city],
                ],
            ],
        ];
    }

    public function testNeutralisesInvalidWardOnStoreInformation(): void
    {
        $subject = $this->subject('general', $this->vnGroups('Bogus Ward'));
        $plugin = new ValidateVietNamWard($this->factory($this->collection(0)), $this->logger());

        $plugin->beforeSave($subject);

        self::assertSame(
            '',
            $subject->getGroups()['store_information']['fields']['city']['value']
        );
    }

    public function testKeepsValidWardOnStoreInformation(): void
    {
        $groups = $this->vnGroups('An Phu');
        $subject = $this->subject('general', $groups);
        $plugin = new ValidateVietNamWard($this->factory($this->collection(1)), $this->logger());

        $plugin->beforeSave($subject);

        self::assertSame('An Phu', $subject->getGroups()['store_information']['fields']['city']['value']);
    }

    public function testValidatesShippingOriginGroup(): void
    {
        $groups = [
            'origin' => [
                'fields' => [
                    'country_id' => ['value' => 'VN'],
                    'region_id' => ['value' => '1190'],
                    'city' => ['value' => 'Bad'],
                ],
            ],
        ];
        $subject = $this->subject('shipping', $groups);
        $plugin = new ValidateVietNamWard($this->factory($this->collection(0)), $this->logger());

        $plugin->beforeSave($subject);

        self::assertSame('', $subject->getGroups()['origin']['fields']['city']['value']);
    }

    public function testIgnoresNonVnCountry(): void
    {
        $groups = [
            'store_information' => [
                'fields' => [
                    'country_id' => ['value' => 'US'],
                    'region_id' => ['value' => '12'],
                    'city' => ['value' => 'Los Angeles'],
                ],
            ],
        ];
        $subject = $this->subject('general', $groups);
        // create() must never be called for non-VN -> factory expects never.
        $plugin = new ValidateVietNamWard($this->factory(null), $this->logger());

        $plugin->beforeSave($subject);

        self::assertSame('Los Angeles', $subject->getGroups()['store_information']['fields']['city']['value']);
    }

    public function testIgnoresUnrelatedSection(): void
    {
        $subject = $this->subject('catalog', $this->vnGroups('An Phu'));
        $plugin = new ValidateVietNamWard($this->factory(null), $this->logger());

        $plugin->beforeSave($subject);

        // Untouched: section is not one of the two origin surfaces.
        self::assertSame('An Phu', $subject->getGroups()['store_information']['fields']['city']['value']);
    }

    public function testIgnoresIncompleteAddress(): void
    {
        // Ward present, region empty -> incomplete, skip validation.
        $groups = [
            'store_information' => [
                'fields' => [
                    'country_id' => ['value' => 'VN'],
                    'region_id' => ['value' => ''],
                    'city' => ['value' => 'An Phu'],
                ],
            ],
        ];
        $subject = $this->subject('general', $groups);
        $plugin = new ValidateVietNamWard($this->factory(null), $this->logger());

        $plugin->beforeSave($subject);

        self::assertSame('An Phu', $subject->getGroups()['store_information']['fields']['city']['value']);
    }

    public function testDoesNotBreakSaveWhenCollectionThrows(): void
    {
        $collection = $this->baseCollection();
        $collection->method('getSize')->willThrowException(new \RuntimeException('db down'));

        $logger = $this->logger();
        $logger->expects(self::once())->method('error');

        $subject = $this->subject('general', $this->vnGroups('An Phu'));
        $plugin = new ValidateVietNamWard($this->factory($collection), $logger);

        // Must not rethrow: a validation fault never blocks the config save.
        $plugin->beforeSave($subject);

        // City unchanged: neutralisation never reached because the fault was caught first.
        self::assertSame('An Phu', $subject->getGroups()['store_information']['fields']['city']['value']);
    }
}
