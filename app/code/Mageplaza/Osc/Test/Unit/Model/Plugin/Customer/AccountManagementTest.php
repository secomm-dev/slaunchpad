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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Customer;

use Magento\Checkout\Model\Session;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\AccountManagement as AM;
use Magento\Eav\Model\Config;
use Mageplaza\Osc\Model\Plugin\Customer\AccountManagement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AccountManagementTest extends TestCase
{
    /**
     * @var Session|MockObject
     */
    protected $checkoutSessionMock;

    /**
     * @var Config|MockObject
     */
    private $configMock;

    /**
     * @var AccountManagement
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->setMethods(['getOscData'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new AccountManagement(
            $this->checkoutSessionMock,
            $this->configMock
        );
    }

    public function testMethod()
    {
        $methods = get_class_methods(AM::class);

        $this->assertTrue(in_array('createAccount', $methods));
    }

    public function testBeforeCreateAccount()
    {
        /**
         * @var AM $subject
         */
        $subject = $this->getMockBuilder(AM::class)->disableOriginalConstructor()->getMock();

        /**
         * @var CustomerInterface $customerMock
         */
        $customerMock = $this->getMockBuilder(CustomerInterface::class)
            ->setMethods(['setData'])
            ->getMockForAbstractClass();
        $oscData = [
            'register' => true,
            'password' => 'Test123',
            'customerAttributes' => [
                'my_attribute' => 1
            ]
        ];
        $this->checkoutSessionMock->expects($this->once())->method('getOscData')->willReturn($oscData);
        $this->configMock->expects($this->once())->method('getAttribute')
            ->with('customer', 'my_attribute')->willReturn(true);
        $customerMock->expects($this->once())->method('setData')->with('my_attribute', 1);

        $this->assertEquals(
            [$customerMock, 'Test123', ''],
            $this->plugin->beforeCreateAccount($subject, $customerMock)
        );
    }

    public function testBeforeCreateAccountWithPassword()
    {
        /**
         * @var AM $subject
         */
        $subject = $this->getMockBuilder(AM::class)->disableOriginalConstructor()->getMock();

        /**
         * @var CustomerInterface $customerMock
         */
        $customerMock = $this->getMockBuilder(CustomerInterface::class)
            ->getMockForAbstractClass();
        $oscData = [
            'register' => true,
            'password' => 'Test123',
            'customerAttributes' => []
        ];
        $this->checkoutSessionMock->expects($this->once())->method('getOscData')->willReturn($oscData);

        $this->assertEquals(
            [$customerMock, 'Test123', ''],
            $this->plugin->beforeCreateAccount($subject, $customerMock)
        );
    }
}
