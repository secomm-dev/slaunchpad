<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 12/01/2019
 * Time: 12:23 PM
 */

namespace Mageplaza\ThankYouPage\Test\Unit\Block;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\Currency;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Productslider\Block\Widget\Slider;
use Mageplaza\ThankYouPage\Block\Subscribe;
use Mageplaza\ThankYouPage\Helper\Data;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;
use Mageplaza\ThankYouPage\Model\Template;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * Class SubscribeTest
 * @package Mageplaza\ThankYouPage\Test\Unit\Block
 */
class SubscribeTest extends TestCase
{
    /**
     * @var Context|PHPUnit_Framework_MockObject_MockObject
     */
    private $context;

    /**
     * @var Data|PHPUnit_Framework_MockObject_MockObject
     */
    protected $helperData;

    /**
     * @var CustomerSession|PHPUnit_Framework_MockObject_MockObject
     */
    protected $customerSession;

    /**
     * @var SessionManagerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    protected $session;

    /**
     * @var Currency|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_currency;

    /**
     * @var StoreManagerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $_storeMananger;

    /**
     * @var LayoutInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $_layout;

    /**
     * @var Subscribe
     */
    private $object;

    protected function setUp()
    {
        $this->context         = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->helperData      = $this->getMockBuilder(Data::class)->disableOriginalConstructor()->getMock();
        $this->customerSession = $this->getMockBuilder(CustomerSession::class)->disableOriginalConstructor()->getMock();
        $this->session         = $this->getMockBuilder(SessionManagerInterface::class)->disableOriginalConstructor()->getMock();
        $this->_currency       = $this->getMockBuilder(Currency::class)->disableOriginalConstructor()->getMock();

        $this->_storeMananger = $this->getMockBuilder(StoreManagerInterface::class)->getMock();
        $this->context->method('getStoreManager')->willReturn($this->_storeMananger);

        $this->_layout = $this->getMockBuilder(LayoutInterface::class)->getMock();
        $this->context->method('getLayout')->willReturn($this->_layout);

        $this->object = new Subscribe(
            $this->context,
            $this->helperData,
            $this->customerSession,
            $this->session,
            $this->_currency
        );
    }

    public function testAdminInstance()
    {
        $this->assertInstanceOf(Subscribe::class, $this->object);
    }

    /**
     * @param $page
     * @param $enable
     * @param $enableSlider
     * @param $type
     * @param $result
     *
     * @dataProvider sliderProvide
     * @throws LocalizedException
     */
    public function testGetProductSlider($page, $enable, $enableSlider, $type, $result)
    {
        $template = $this->getMockBuilder(Template::class)
            ->disableOriginalConstructor()
            ->getMock();
        $store    = $this->getMockBuilder(StoreInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->helperData->method('getStoreData')->willReturn($store);
        $this->helperData->method('isNewsletterSuccessPageEnable')->willReturn($enable);
        $this->helperData->method('getTemplateData')->with($page)->willReturn($template);
        $template->method('getProductSliderId')->willReturn($type);
        $this->helperData->method('getConfigValue')->with(
            'productslider/general/enabled',
            0
        )->willReturn($enableSlider);

        $slider = $this->getMockBuilder(Slider::class)
            ->setMethods(['setProductType', 'toHtml'])
            ->disableOriginalConstructor()->getMock();
        $this->_layout->method('createBlock')->with('\Mageplaza\Productslider\Block\Widget\Slider')->willReturn($slider);
        $slider->method('setProductType')->with($type)->willReturnSelf();

        $slider->method('toHtml')->willReturn($result);

        $this->assertEquals($result, $this->object->getProductSlider());
    }

    /**
     * @return array
     */
    public function sliderProvide()
    {
        return [
            [Pagetype::NEWSLETTER, 1, 1, 'new', '<div>newsletter</div>'],
            [Pagetype::NEWSLETTER, 1, 1, '', ''],
            [Pagetype::NEWSLETTER, 1, 0, 'new', ''],
            [Pagetype::NEWSLETTER, 0, 1, '', '']
        ];
    }
}
