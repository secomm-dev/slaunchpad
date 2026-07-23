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

namespace Mageplaza\Osc\Test\Unit\Block\Order;

use Magento\Framework\DataObject;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Model\Order;
use Mageplaza\Osc\Block\Order\Totals;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class GiftWrap
 *
 */
class TotalsTest extends TestCase
{
    /**
     * @var Totals
     */
    protected $totalBlock;

    public function setUp(): void
    {
        /**
         * @var \Magento\Backend\Block\Widget\Context|MockObject $contextMock
         */
        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->totalBlock = new Totals($contextMock);
    }

    /**
     * Init Totals
     */
    public function testInitTotals()
    {
        /**
         * @var LayoutInterface|MockObject $layoutMock
         */
        $layoutMock = $this->getMockForAbstractClass(LayoutInterface::class);

        $blockMock = $this->getMockBuilder(BlockInterface::class)
            ->setMethods(['getSource', 'addTotal'])
            ->getMockForAbstractClass();
        $this->totalBlock->setLayout($layoutMock);
        $this->totalBlock->setNameInLayout('test');
        $layoutMock->expects($this->once())->method('getParentName')->with('test')->willReturn('parentName');
        $layoutMock->expects($this->once())->method('getblock')->with('parentName')->willReturn($blockMock);

        $orderMock = $this->getMockBuilder(Order::class)
            ->setMethods(['getOscGiftWrapAmount'])
            ->disableOriginalConstructor()
            ->getMock();
        $blockMock->expects($this->once())->method('getSource')->willReturn($orderMock);
        $oscGiftWrapAmount = 10;
        $orderMock->expects($this->exactly(2))->method('getOscGiftWrapAmount')->willReturn($oscGiftWrapAmount);
        $dataObject = new DataObject(
            [
            'code' => 'gift_wrap',
            'field' => 'osc_gift_wrap_amount',
            'label' => new Phrase('Gift Wrap'),
            'value' => $oscGiftWrapAmount,
            ]
        );
        $blockMock->expects($this->once())
            ->method('addTotal')
            ->with($dataObject)
            ->willReturnSelf();

        $this->totalBlock->initTotals();
    }
}
