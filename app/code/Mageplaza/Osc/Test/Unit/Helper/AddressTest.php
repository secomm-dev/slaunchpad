<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category  Mageplaza
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Osc\Test\Unit\Helper;

use Exception;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Customer\Helper\Address as CustomerAddressHelper;
use Magento\Customer\Model\AttributeMetadataDataProvider;
use Magento\Directory\Model\Region;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Osc\Helper\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AddressTest extends TestCase
{
    /**
     * @var DirectoryList
     */
    private $directoryListMock;

    /**
     * @var Resolver
     */
    private $localeResolverMock;

    /**
     * @var Region
     */
    private $regionModelMock;

    /**
     * @var CustomerAddressHelper
     */
    private $addressHelperMock;

    /**
     * @var AttributeMetadataDataProvider
     */
    private $attributeMetadataDataProviderMock;

    /**
     * @var Config
     */
    private $resourceConfigMock;

    /**
     * @var ReinitableConfigInterface
     */
    private $appConfigMock;

    /**
     * @var Json|MockObject
     */
    private $jsonMock;

    /**
     * @var ObjectManagerInterface|MockObject
     */
    private $objectManagerMock;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $scopeConfigMock;

    /**
     * @var Manager|MockObject
     */
    private $moduleManagerMock;

    /**
     * @var LoggerInterface|MockObject
     */
    protected $loggerMock;

    /**
     * @var Address
     */
    private $helperAddress;

    protected function setUp(): void
    {
        /**
         * @var Context|MockObject $contextMock
         */
        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->objectManagerMock = $this->getMockForAbstractClass(ObjectManagerInterface::class);
        /**
         * @var StoreManagerInterface $storeManagerMock
         */
        $storeManagerMock = $this->getMockForAbstractClass(StoreManagerInterface::class);
        /**
         * @var EncryptorInterface $encryptorMock
         */
        $encryptorMock = $this->getMockForAbstractClass(EncryptorInterface::class);

        $this->scopeConfigMock = $this->getMockForAbstractClass(ScopeConfigInterface::class);
        $this->loggerMock = $this->getMockForAbstractClass(LoggerInterface::class);
        $this->moduleManagerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $contextMock->method('getScopeConfig')->willReturn($this->scopeConfigMock);
        $contextMock->method('getModuleManager')->willReturn($this->moduleManagerMock);
        $contextMock->method('getLogger')->willReturn($this->loggerMock);

        $this->directoryListMock = $this->getMockBuilder(DirectoryList::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->localeResolverMock = $this->getMockBuilder(Resolver::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->regionModelMock = $this->getMockBuilder(Region::class)
            ->disableOriginalConstructor()->getMock();
        $this->jsonMock = $this->getMockBuilder(Json::class)->disableOriginalConstructor()->getMock();

        $this->addressHelperMock = $this->getMockBuilder(CustomerAddressHelper::class)
            ->disableOriginalConstructor()->getMock();
        $this->attributeMetadataDataProviderMock = $this->getMockBuilder(AttributeMetadataDataProvider::class)
            ->disableOriginalConstructor()->getMock();

        $this->resourceConfigMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()->getMock();

        $this->appConfigMock = $this->getMockBuilder(ReinitableConfigInterface::class)
            ->disableOriginalConstructor()->getMock();

        $this->helperAddress = new Address(
            $contextMock,
            $this->objectManagerMock,
            $storeManagerMock,
            $encryptorMock,
            $this->jsonMock,
            $this->directoryListMock,
            $this->localeResolverMock,
            $this->regionModelMock,
            $this->addressHelperMock,
            $this->attributeMetadataDataProviderMock,
            $this->resourceConfigMock,
            $this->appConfigMock
        );
    }

    public function testSaveOscConfig()
    {
        $value = 1;
        $path = 'osc/general/test';
        $this->resourceConfigMock->expects($this->once())
            ->method('saveConfig')
            ->with($path, $value, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0)
            ->willReturnSelf();

        $this->appConfigMock->expects($this->once())->method('reinit')->willReturnSelf();

        $this->helperAddress->saveOscConfig($value, $path);
    }

    public function testGetGeoIPDataWithDisableConfig()
    {
        $this->mockFieldConfig(false);

        $this->assertEquals([], $this->helperAddress->getGeoIpData());
    }

    public function testGetGeoIPDataWithDisableModuleGeoIp()
    {
        $this->mockFieldConfig(true);
        $this->mockModuleGeoIP(false);

        $this->assertEquals([], $this->helperAddress->getGeoIpData());
    }

    /**
     * @param boolean $result
     */
    public function mockModuleGeoIP($result)
    {
        $moduleName = 'Mageplaza_GeoIP';
        $this->moduleManagerMock
            ->expects($this->once())
            ->method('isOutputEnabled')
            ->with($moduleName)
            ->willReturn($result);
    }

    public function testGetGeoIPDataWithException()
    {
        $this->mockFieldConfig(true);
        $this->mockModuleGeoIp(true);

        $helperGeoIP = $this->getMockBuilder(\Mageplaza\GeoIP\Helper\Address::class)
            ->setMethods(['checkHasLibrary', 'getGeoIpData', 'isEnabled'])
            ->disableOriginalConstructor()
            ->getMock();
        $exception = new Exception('test');
        $helperGeoIP->expects($this->once())->method('checkHasLibrary')->willThrowException($exception);
        $this->loggerMock->expects($this->once())->method('critical')->with('test');
        $helperGeoIP->expects($this->once())->method('isEnabled')->with(null)->willReturn(true);

        $this->objectManagerMock->expects($this->at(1))
            ->method('get')
            ->with(\Mageplaza\GeoIP\Helper\Address::class)
            ->willReturn($helperGeoIP);

        $this->assertEquals([], $this->helperAddress->getGeoIpData());
    }

    /**
     * @return array
     */
    public function providerTestGetGeoIPData()
    {
        return [
            [
                ['country_id' => 'US'],
                'US, UK',
                ['country_id' => 'US'],
            ],
            [
                [],
                'UK',
                ['country_id' => 'US'],
            ]
        ];
    }

    /**
     * @param array  $result
     * @param string $allowedCountries
     * @param array  $geoIpData
     *
     * @dataProvider providerTestGetGeoIPData
     */
    public function testGetGeoIPData($result, $allowedCountries, $geoIpData)
    {
        $this->mockFieldConfig(true);
        $this->mockModuleGeoIp(true);

        $helperGeoIP = $this->getMockBuilder(\Mageplaza\GeoIP\Helper\Address::class)
            ->setMethods(['checkHasLibrary', 'getGeoIpData', 'isEnabled'])
            ->disableOriginalConstructor()
            ->getMock();
        $helperGeoIP->expects($this->once())->method('checkHasLibrary')->willReturn(true);
        $helperGeoIP->expects($this->once())->method('isEnabled')->with(null)->willReturn(true);

        $this->objectManagerMock->expects($this->at(1))
            ->method('get')
            ->with(\Mageplaza\GeoIP\Helper\Address::class)
            ->willReturn($helperGeoIP);
        $this->objectManagerMock->expects($this->at(2))
            ->method('get')
            ->with(\Mageplaza\GeoIP\Helper\Address::class)
            ->willReturn($helperGeoIP);
        $helperGeoIP->expects($this->once())->method('getGeoIpData')->willReturn($geoIpData);

        $this->scopeConfigMock->expects($this->at(1))
            ->method('getValue')
            ->with('general/country/allow', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($allowedCountries);

        $this->assertEquals($result, $this->helperAddress->getGeoIpData());
    }

    /**
     * @param $result
     * @param string $field
     * @param int    $at
     * @param int    $valueAt
     */
    public function mockFieldConfig($result, $field = 'osc/general/geoip', $at = 0, $valueAt = 0)
    {
        $stateMock = $this->getMockBuilder(State::class)
            ->disableOriginalConstructor()->getMock();
        $this->objectManagerMock->expects($this->at($at))
            ->method('get')
            ->with('Magento\Framework\App\State')
            ->willReturn($stateMock);
        $stateMock->expects($this->once())
            ->method('getAreaCode')
            ->willReturn(Area::AREA_FRONTEND);
        $this->scopeConfigMock->expects($this->at($valueAt))
            ->method('getValue')
            ->with($field, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($result);
    }
}
