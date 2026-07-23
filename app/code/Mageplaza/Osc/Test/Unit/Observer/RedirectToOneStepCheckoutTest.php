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

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\UrlInterface;
use Mageplaza\Osc\Helper\Data as OscHelper;
use Mageplaza\Osc\Observer\RedirectToOneStepCheckout;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RedirectToOneStepCheckoutTest extends TestCase
{
    /**
     * @var UrlInterface|MockObject
     */
    private $urlMock;

    /**
     * @var OscHelper|MockObject
     */
    private $oscHelperMock;

    /**
     * @var RedirectToOneStepCheckout
     */
    private $observer;

    protected function setUp(): void
    {
        $this->oscHelperMock = $this->getMockBuilder(OscHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->urlMock = $this->getMockForAbstractClass(UrlInterface::class);

        $this->observer = new RedirectToOneStepCheckout(
            $this->urlMock,
            $this->oscHelperMock
        );
    }

    public function testExecute()
    {
        $oscRoute = 'osc';
        $url = 'url';

        /**
         * @var Observer|MockObject $observerMock
         */
        $observerMock = $this->getMockBuilder(Observer::class)
            ->setMethods(['getRequest'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->oscHelperMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $this->oscHelperMock->expects($this->once())->method('isRedirectToOneStepCheckout')->willReturn(true);
        $requestMock = $this->getMockBuilder(RequestInterface::class)
            ->setMethods(['setParam'])
            ->getMockForAbstractClass();
        $observerMock->expects($this->once())->method('getRequest')->willReturn($requestMock);
        $this->oscHelperMock->expects($this->once())->method('getOscRoute')->willReturn($oscRoute);
        $this->urlMock->expects($this->once())->method('getUrl')->with($oscRoute)->willReturn($url);
        $requestMock->expects($this->once())->method('setParam')->with('return_url', $url);

        $this->observer->execute($observerMock);
    }
}
