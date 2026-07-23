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

namespace Mageplaza\Osc\Test\Unit\Block\Order\View;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Mageplaza\Osc\Block\Order\View\Comment;
use Mageplaza\Osc\Helper\Data;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CommentTest extends TestCase
{
    /**
     * @var Registry|MockObject
     */
    protected $coreRegistryMock;

    /**
     * @var Data|MockObject
     */
    protected $helperMock;

    /**
     * @var Comment
     */
    protected $commentBlock;

    public function setUp(): void
    {
        /**
         * @var Context|MockObject $contextMock
         */
        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->coreRegistryMock = $this->getMockBuilder(Registry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->helperMock = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->commentBlock = new Comment(
            $contextMock,
            $this->coreRegistryMock,
            $this->helperMock
        );
    }

    public function testGetOrderComment()
    {
        $orderMock = $this->getMockBuilder(Order::class)
            ->setMethods(['getOscOrderComment'])
            ->disableOriginalConstructor()->getMock();
        $this->coreRegistryMock->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn($orderMock);
        $orderMock->expects($this->once())->method('getOscOrderComment')->willReturn('test');

        $this->assertEquals('test', $this->commentBlock->getOrderComment());
    }

    public function testGetOrderCommentWithEmptyOrder()
    {
        $this->coreRegistryMock->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn(null);

        $this->assertEmpty($this->commentBlock->getOrderComment());
    }
}
