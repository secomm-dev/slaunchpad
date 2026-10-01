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
 * @package   Mageplaza_RMA
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Test\Unit\Helper;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\Currency\DefaultLocator;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Escaper;
use Magento\Framework\File\Size;
use Magento\Framework\Filesystem;
use Magento\Framework\Locale\CurrencyInterface;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Stdlib\DateTime\DateTime as StdlibDateTime;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\System\Store as SystemStore;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image as HelperImage;
use Mageplaza\RMA\Mail\Template\TransportBuilder;
use Mageplaza\RMA\Model\ResourceModel\Rule\CollectionFactory;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel\CollectionFactory as ShippingLabelColFact;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * Class DataTest
 *
 * @package Mageplaza\BetterProductReviews\Test\Unit\Helper
 */
class DataTest extends TestCase
{
    /**
     * @var StdlibDateTime|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_dateTimeMock;

    /**
     * @var ProductRepositoryInterface|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_productRepositoryMock;

    /**
     * @var SystemStore|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_systemStoreMock;

    /**
     * @var Escaper|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_escapeMock;

    /**
     * @var CustomerSession|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_customerSessionMock;

    /**
     * @var HttpContext|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_httpContextMock;

    /**
     * @var DefaultLocator|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_currencyLocatorMock;

    /**
     * @var CurrencyFactory|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_currencyFactoryMock;

    /**
     * @var CurrencyInterface|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_localeCurrencyMock;

    /**
     * @var Size|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_fileSizeMock;

    /**
     * @var ImageFactory|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_imageHelperFactoryMock;

    /**
     * @var FormatInterface|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_priceFormatMock;

    /**
     * @var TransportBuilder|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_transportBuilderMock;

    /**
     * @var Filesystem|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_filesystemMock;

    /**
     * @var HelperImage|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_helperImageMock;

    /**
     * @var CollectionFactory|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_ruleColFactMock;

    /**
     * @var ShippingLabelColFact|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_shippingLabelColFactMock;

    /**
     * @var HelperData
     */
    protected $model;

    protected function setUp()
    {
        $this->_dateTimeMock = $this->getMockBuilder(StdlibDateTime::class)
            ->disableOriginalConstructor()
            ->setMethods(['date'])
            ->getMock();

        $this->_productRepositoryMock = $this->getMockBuilder(ProductRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_systemStoreMock = $this->getMockBuilder(SystemStore::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_escapeMock = $this->getMockBuilder(Escaper::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_customerSessionMock = $this->getMockBuilder(CustomerSession::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_httpContextMock = $this->getMockBuilder(HttpContext::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_currencyLocatorMock = $this->getMockBuilder(DefaultLocator::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_currencyFactoryMock = $this->getMockBuilder(CurrencyFactory::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $this->_localeCurrencyMock = $this->getMockBuilder(CurrencyInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getDefaultCurrency', 'getCurrency'])
            ->getMock();

        $this->_fileSizeMock = $this->getMockBuilder(Size::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_imageHelperFactoryMock = $this->getMockBuilder(ImageFactory::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_priceFormatMock = $this->getMockBuilder(FormatInterface::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_transportBuilderMock = $this->getMockBuilder(TransportBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_filesystemMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_helperImageMock = $this->getMockBuilder(HelperImage::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_ruleColFactMock = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->_shippingLabelColFactMock = $this->getMockBuilder(ShippingLabelColFact::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $helper = new ObjectManager($this);

        $this->model = $helper->getObject(
            HelperData::class,
            [
                '_dateTime' => $this->_dateTimeMock,
                '_productRepository' => $this->_productRepositoryMock,
                '_systemStore' => $this->_systemStoreMock,
                '_escape' => $this->_escapeMock,
                '_customerSession' => $this->_customerSessionMock,
                '_httpContext' => $this->_httpContextMock,
                '_currencyLocator' => $this->_currencyLocatorMock,
                '_currencyFactory' => $this->_currencyFactoryMock,
                '_localeCurrency' => $this->_localeCurrencyMock,
                '_fileSize' => $this->_fileSizeMock,
                '_imageHelperFactory' => $this->_imageHelperFactoryMock,
                '_priceFormat' => $this->_priceFormatMock,
                '_transportBuilder' => $this->_transportBuilderMock,
                '_filesystem' => $this->_filesystemMock,
                '_helperImage' => $this->_helperImageMock,
                '_ruleColFact' => $this->_ruleColFactMock,
                '_shippingLabelColFact' => $this->_shippingLabelColFactMock,
            ]
        );
    }

    /**
     * test calculator review rating value
     */
    public function testGetTimeAgo()
    {
        $this->_dateTimeMock->expects($this->any())->method('date')->willReturn('2019-05-25 17:37:07');
        $actualResult = $this->model->getTimeAgo('2019-05-20');
        $expectResult = '6 days ago ';
        $this->assertEquals($expectResult, $actualResult);
    }
}
