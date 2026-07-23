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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Authorization;

use Magento\Authorization\Model\CompositeUserContext;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote as QuoteCore;
use Mageplaza\Osc\Helper\Data as OscHelper;
use Mageplaza\Osc\Model\Plugin\Authorization\UserContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class UserContextTest extends TestCase
{
    /**
     * @var OscHelper|MockObject
     */
    private $oscHelperMock;

    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var UserContext|MockObject
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->oscHelperMock = $this->getMockBuilder(OscHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new UserContext($this->oscHelperMock, $this->checkoutSessionMock);
    }

    public function testMethod()
    {
        $methods = get_class_methods(CompositeUserContext::class);

        $this->assertTrue(in_array('getUserType', $methods));
        $this->assertTrue(in_array('getUserId', $methods));
    }

    /**
     * @return array
     */
    public function providerTestAfterGetUserType()
    {
        return [
            [
                UserContextInterface::USER_TYPE_CUSTOMER,
                true
            ],
            [
                'test',
                false
            ]
        ];
    }

    /**
     * @param string  $result
     * @param boolean $flagOsc
     *
     * @dataProvider providerTestAfterGetUserType
     * @throws       ReflectionException
     */
    public function testAfterGetUserType($result, $flagOsc)
    {
        /**
         * @var UserContextInterface $userContextMock
         */
        $userContextMock = $this->getMockForAbstractClass(UserContextInterface::class);

        $this->oscHelperMock->expects($this->once())->method('isFlagOscMethodRegister')->willReturn($flagOsc);

        $this->assertEquals($result, $this->plugin->afterGetUserType($userContextMock, $result));
    }

    /**
     * @return array
     */
    public function providerTestAfterGetUserId()
    {
        return [
            [
                1,
                true
            ],
            [
                0,
                false
            ]
        ];
    }

    /**
     * @param string  $result
     * @param boolean $flagOsc
     *
     * @dataProvider providerTestAfterGetUserId
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ReflectionException
     */
    public function testAfterGetUserId($result, $flagOsc)
    {
        /**
         * @var UserContextInterface $userContextMock
         */
        $userContextMock = $this->getMockForAbstractClass(UserContextInterface::class);

        $this->oscHelperMock->expects($this->once())->method('isFlagOscMethodRegister')->willReturn($flagOsc);
        if ($flagOsc) {
            $quoteMock = $this->getMockBuilder(QuoteCore::class)
                ->setMethods(['getCustomerId'])
                ->disableOriginalConstructor()->getMock();
            $this->checkoutSessionMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
            $quoteMock->expects($this->once())->method('getCustomerId')->willReturn(1);
        }

        $this->assertEquals($result, $this->plugin->afterGetUserId($userContextMock, $result));
    }
}
