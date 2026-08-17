<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Plugin\Adminhtml;

use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use Magento\Sales\Block\Adminhtml\Order\Create\Form\Address;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Plugin\Adminhtml\OrderAddressFormPlugin;

class OrderAddressFormPluginTest extends TestCase
{
    public function testAfterGetFormValuesHydratesRegionIdFromRegionName(): void
    {
        $region = $this->getMockBuilder(Region::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadByCode', 'loadByName', 'getId'])
            ->getMock();
        $region->method('loadByCode')->willReturnSelf();
        $region->method('loadByName')->willReturnSelf();
        $region->method('getId')->willReturn(12);

        $regionFactory = $this->getMockBuilder(RegionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $regionFactory->method('create')->willReturn($region);

        $plugin = new OrderAddressFormPlugin($regionFactory);

        $subject = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFormValues'])
            ->getMock();

        $result = $plugin->afterGetFormValues($subject, [
            'country_id' => 'US',
            'region' => 'California',
        ]);

        self::assertSame(12, $result['region_id']);
    }

    public function testAfterGetFormValuesKeepsExistingRegionId(): void
    {
        $regionFactory = $this->getMockBuilder(RegionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $regionFactory->expects(self::never())->method('create');

        $plugin = new OrderAddressFormPlugin($regionFactory);

        $subject = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFormValues'])
            ->getMock();

        $result = $plugin->afterGetFormValues($subject, [
            'country_id' => 'US',
            'region' => 'California',
            'region_id' => 12,
        ]);

        self::assertSame(12, $result['region_id']);
    }
}
