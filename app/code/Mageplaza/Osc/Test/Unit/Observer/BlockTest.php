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
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Mageplaza\Osc\Helper\Data;
use Mageplaza\Osc\Observer\Block;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BlockTest extends TestCase
{
    /**
     * @var Data|MockObject
     */
    private $helperDataMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var Block
     */
    private $observer;

    protected function setUp(): void
    {
        $this->helperDataMock = $this->getMockBuilder(Data::class)->disableOriginalConstructor()->getMock();
        $this->requestMock = $this->getMockBuilder(RequestInterface::class)
            ->setMethods(['getFullActionName'])
            ->getMockForAbstractClass();

        $this->observer = new Block(
            $this->helperDataMock,
            $this->requestMock
        );
    }

    public function testExecute()
    {
        /**
         * @var Observer $observerMock
         */
        $observerMock = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestMock->expects($this->once())
            ->method('getFullActionName')
            ->willReturn('onestepcheckout_index_index');
        $eventMock = $this->getMockBuilder(Event::class)
            ->setMethods(['getBlock', 'getTransport'])
            ->disableOriginalConstructor()
            ->getMock();

        $observerMock->method('getEvent')->willReturn($eventMock);
        $blockMock = $this->getMockBuilder(Template::class)
            ->disableOriginalConstructor()
            ->getMock();
        $transportMock = $this->getMockBuilder(DataObject::class)
            ->setMethods(['getHtml', 'setHtml'])
            ->disableOriginalConstructor()->getMock();

        $eventMock->expects($this->once())->method('getBlock')->willReturn($blockMock);
        $eventMock->expects($this->once())->method('getTransport')->willReturn($transportMock);
        $oscRoute = 'osc';
        $this->helperDataMock->expects($this->once())->method('getOscRoute')->willReturn($oscRoute);
        $html = 'test';
        $transportMock->expects($this->once())->method('getHtml')->willReturn($html);
        $this->helperDataMock->expects($this->once())->method('jsonEncodeData')->with($oscRoute)->willReturn('"osc"');
        $layoutMock = $this->getMockForAbstractClass(LayoutInterface::class);
        $blockMock->expects($this->once())->method('getLayout')->willReturn($layoutMock);
        $layoutMock->expects($this->once())->method('isBlock')->with('require.js')->willReturn(true);
        $html .= '<script> window.oscRoute = "osc"</script>';
        $transportMock->expects($this->once())->method('setHtml')->with($html);

        $this->observer->execute($observerMock);
    }
}
