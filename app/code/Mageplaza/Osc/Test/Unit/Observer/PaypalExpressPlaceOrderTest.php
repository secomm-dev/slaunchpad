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

namespace Mageplaza\Osc\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Mageplaza\Osc\Model\CheckoutRegister;
use Mageplaza\Osc\Observer\PaypalExpressPlaceOrder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaypalExpressPlaceOrderTest extends TestCase
{
    /**
     * @var CheckoutRegister|MockObject
     */
    private $checkoutRegisterMock;

    /**
     * @var PaypalExpressPlaceOrder
     */
    private $observer;

    protected function setUp(): void
    {
        $this->checkoutRegisterMock = $this->getMockBuilder(CheckoutRegister::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->observer = new PaypalExpressPlaceOrder($this->checkoutRegisterMock);
    }

    public function testExecute()
    {
        /**
         * @var Observer $observerMock
         */
        $observerMock = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutRegisterMock->expects($this->once())->method('checkRegisterNewCustomer');

        $this->observer->execute($observerMock);
    }
}
