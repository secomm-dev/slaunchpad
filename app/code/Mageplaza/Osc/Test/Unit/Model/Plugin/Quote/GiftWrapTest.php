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

use Closure;
use Magento\Quote\Api\Data\TotalSegmentExtension;
use Magento\Quote\Api\Data\TotalSegmentExtensionFactory;
use Magento\Quote\Model\Cart\TotalsConverter;
use Magento\Quote\Model\Quote\Address\Total;
use Mageplaza\Osc\Model\Plugin\Quote\GiftWrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GiftWrapTest extends TestCase
{
    /**
     * @var TotalSegmentExtensionFactory|MockObject
     */
    protected $totalSegmentExtensionFactoryMock;

    /**
     * @var GiftWrap
     */
    private $plugin;

    /**
     * @var TotalsConverter|MockObject
     */
    private $subject;

    /**
     * @var Closure
     */
    private $closureMock;

    protected function setUp(): void
    {
        $this->totalSegmentExtensionFactoryMock = $this->getMockBuilder(TotalSegmentExtensionFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->subject = $this->getMockBuilder(TotalsConverter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $subject = $this->subject;
        $this->closureMock = function () use ($subject) {
            return $subject;
        };

        $this->plugin = new GiftWrap($this->totalSegmentExtensionFactoryMock);
    }

    public function testMethod()
    {
        $methods = get_class_methods(TotalsConverter::class);

        $this->assertTrue(in_array('process', $methods));
    }

    public function testAroundProcessWithEmptyGiftWrap()
    {
        $quoteAddressTotalMock = $this->getMockBuilder(Total::class)
            ->disableOriginalConstructor()
            ->getMock();
        $addressTotalsMock = [$quoteAddressTotalMock];

        $this->assertEquals(
            $this->subject,
            $this->plugin->aroundProcess($this->subject, $this->closureMock, $addressTotalsMock)
        );
    }

    public function testAroundProcessWithEmptyKeyGiftWrapAmount()
    {
        $quoteAddressTotalMock = $this->getMockBuilder(Total::class)
            ->disableOriginalConstructor()
            ->getMock();
        $addressTotalsMock = ['osc_gift_wrap' => $quoteAddressTotalMock];
        $quoteAddressTotalMock->expects($this->once())
            ->method('getData')
            ->willReturn([]);

        $this->assertEquals(
            $this->subject,
            $this->plugin->aroundProcess($this->subject, $this->closureMock, $addressTotalsMock)
        );
    }

    public function testAroundProcessWithEmptyExtensionAttributes()
    {
        $quoteAddressTotalMock = $this->getMockBuilder(Total::class)
            ->setMethods(['getExtensionAttributes', 'setExtensionAttributes', 'getData'])
            ->disableOriginalConstructor()
            ->getMock();
        $addressTotalsMock = ['osc_gift_wrap' => $quoteAddressTotalMock];

        $this->closureMock = function () use ($addressTotalsMock) {
            return $addressTotalsMock;
        };
        $quoteAddressTotalMock->expects($this->once())
            ->method('getData')
            ->willReturn(['gift_wrap_amount' => 10]);
        $quoteAddressTotalMock->expects($this->once())->method('getExtensionAttributes')->willReturn(null);
        $totalExtension = $this->getMockBuilder(TotalSegmentExtension::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->totalSegmentExtensionFactoryMock->expects($this->once())->method('create')->willReturn($totalExtension);
        $totalExtension->expects($this->once())->method('setGiftWrapAmount')->with(10);
        $quoteAddressTotalMock->expects($this->once())->method('setExtensionAttributes')->with($totalExtension);

        $this->plugin->aroundProcess($this->subject, $this->closureMock, $addressTotalsMock);
    }
}
