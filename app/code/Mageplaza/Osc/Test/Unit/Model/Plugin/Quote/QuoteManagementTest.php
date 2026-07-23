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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Quote;

use Magento\Quote\Model\Quote as QuoteEntity;
use Mageplaza\Osc\Model\CheckoutRegister;
use Mageplaza\Osc\Model\Plugin\Quote\QuoteManagement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteManagementTest extends TestCase
{
    /**
     * @var CheckoutRegister|MockObject
     */
    private $checkoutRegisterMock;

    /**
     * @var QuoteManagement
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->checkoutRegisterMock = $this->getMockBuilder(CheckoutRegister::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new QuoteManagement($this->checkoutRegisterMock);
    }

    public function testMethod()
    {
        $methods = get_class_methods(\Magento\Quote\Model\QuoteManagement::class);

        $this->assertTrue(in_array('submit', $methods));
    }

    public function testBeforeSubmit()
    {
        /**
         * @var \Magento\Quote\Model\QuoteManagement $subject
         */
        $subject = $this->getMockBuilder(\Magento\Quote\Model\QuoteManagement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutRegisterMock->expects($this->once())
            ->method('checkRegisterNewCustomer');

        /**
         * @var QuoteEntity $quoteMock
         */
        $quoteMock = $this->getMockBuilder(QuoteEntity::class)->disableOriginalConstructor()->getMock();

        $this->assertEquals([$quoteMock, []], $this->plugin->beforeSubmit($subject, $quoteMock, []));
    }
}
