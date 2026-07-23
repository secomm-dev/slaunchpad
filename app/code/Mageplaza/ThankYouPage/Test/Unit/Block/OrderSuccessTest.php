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
 * @category    Mageplaza
 * @package     Mageplaza_RequiredLogin
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Test\Unit\Block;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\Registry;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\OrderFactory;
use Magento\SalesRule\Model\Data\Rule as DataRule;
use Magento\SalesRule\Model\Rule;
use Mageplaza\ThankYouPage\Block\OrderSuccess;
use Mageplaza\ThankYouPage\Helper\Data;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;
use Mageplaza\ThankYouPage\Model\Generate;
use Mageplaza\ThankYouPage\Model\Template;
use Mageplaza\ThankYouPage\Model\TemplateFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * Class ActionTest
 * @package Mageplaza\RequiredLogin\Test\Unit\Block
 */
class OrderSuccessTest extends TestCase
{
    /**
     * @var Context|PHPUnit_Framework_MockObject_MockObject
     */
    private $context;

    /**
     * @var Registry|PHPUnit_Framework_MockObject_MockObject
     */
    private $registry;

    /**
     * @var CheckoutSession|PHPUnit_Framework_MockObject_MockObject
     */
    protected $checkoutSession;

    /**
     * @var CustomerSession|PHPUnit_Framework_MockObject_MockObject
     */
    protected $customerSession;

    /**
     * @var OrderFactory|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_orderFactory;

    /**
     * @var Data|PHPUnit_Framework_MockObject_MockObject
     */
    public $helperData;

    /**
     * @var Generate|PHPUnit_Framework_MockObject_MockObject
     */
    protected $generateCoupon;

    /**
     * @var PricingHelper|PHPUnit_Framework_MockObject_MockObject
     */
    protected $pricingHelper;

    /**
     * @var TemplateFactory|PHPUnit_Framework_MockObject_MockObject
     */
    protected $templateFactory;

    /**
     * @var SessionManagerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    protected $session;

    /**
     * @var OrderSuccess
     */
    private $object;

    protected function setUp()
    {
        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)->disableOriginalConstructor()->getMock();
        $this->customerSession = $this->getMockBuilder(CustomerSession::class)->disableOriginalConstructor()->getMock();
        $this->_orderFactory   = $this->getMockBuilder(OrderFactory::class)->disableOriginalConstructor()->getMock();
        $this->helperData      = $this->getMockBuilder(Data::class)->disableOriginalConstructor()->getMock();
        $this->generateCoupon  = $this->getMockBuilder(Generate::class)->disableOriginalConstructor()->getMock();
        $this->pricingHelper   = $this->getMockBuilder(PricingHelper::class)->disableOriginalConstructor()->getMock();
        $this->session         = $this->getMockBuilder(SessionManagerInterface::class)->getMock();
        $this->templateFactory = $this->getMockBuilder(TemplateFactory::class)->disableOriginalConstructor()->getMock();
        $this->context         = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->registry        = $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock();

        $this->object = new OrderSuccess(
            $this->checkoutSession,
            $this->customerSession,
            $this->_orderFactory,
            $this->helperData,
            $this->generateCoupon,
            $this->pricingHelper,
            $this->context,
            $this->registry,
            $this->session,
            $this->templateFactory,
            []
        );
    }

    public function testAdminInstance()
    {
        $this->assertInstanceOf(OrderSuccess::class, $this->object);
    }

    /**
     * @param $page
     * @param $enable
     * @param $ruleId
     * @param $code
     * @param $result
     *
     * @dataProvider ruleProvide
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testGetCoupon($page, $enable, $ruleId, $code, $result)
    {
        $template = $this->getMockBuilder(Template::class)->disableOriginalConstructor()->getMock();

        $this->helperData->method('getTemplateData')->with($page)->willReturn($template);

        $config = [
            'rule_id'       => $ruleId,
            'enable_coupon' => $enable
        ];
        $template->method('getEnableCoupon')->willReturn($enable);
        $template->method('getRuleId')->willReturn($ruleId);
        $template->method('getData')->willReturn($config);
        $rule = $this->getMockBuilder(DataRule::class)->disableOriginalConstructor()->getMock();
        $this->generateCoupon->method('getRule')->with($ruleId)->willReturn($rule);
        $this->generateCoupon->method('generateCoupon')->with($config)->willReturn($code);
        $rule->method('getSimpleAction')->willReturn(Rule::BY_PERCENT_ACTION);
        $rule->method('getRuleId')->willReturn($ruleId);
        $this->helperData->method('getCouponInfo')->with($config, $code)->willReturn($result);

        $this->assertEquals($result, $this->object->getCoupon());
    }

    /**
     * @return array
     */
    public function ruleProvide()
    {
        $result = [
            'rule_id'         => 1,
            'description'     => 'description',
            'discount_amount' => '%',
            'code'            => 'code'
        ];

        return [
            [Pagetype::ORDER, true, $result['rule_id'], $result['code'], $result],
            [Pagetype::ORDER, true, 0, $result['code'], []],
            [Pagetype::ORDER, false, 0, $result['code'], []]
        ];
    }
}
