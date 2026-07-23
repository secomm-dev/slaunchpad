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
use Mageplaza\Osc\Block\Order\View\Survey;
use Mageplaza\Osc\Helper\Data;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class Survey
 *
 */
class SurveyTest extends TestCase
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
     * @var Survey
     */
    protected $surveyBlock;

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

        $this->surveyBlock = new Survey(
            $contextMock,
            $this->coreRegistryMock,
            $this->helperMock
        );
    }

    public function testGetSurveyQuestion()
    {
        $orderMock = $this->getMockBuilder(Order::class)
            ->setMethods(['getOscSurveyQuestion'])
            ->disableOriginalConstructor()->getMock();
        $this->coreRegistryMock->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn($orderMock);
        $orderMock->expects($this->once())->method('getOscSurveyQuestion')->willReturn('question');

        $this->assertEquals('question', $this->surveyBlock->getSurveyQuestion());
    }

    public function testGetSurveyQuestionWithEmptyOrder()
    {
        $this->coreRegistryMock->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn(null);

        $this->assertEmpty($this->surveyBlock->getSurveyQuestion());
    }

    public function testGetSurveyAnswers()
    {
        $orderMock = $this->getMockBuilder(Order::class)
            ->setMethods(['getOscSurveyAnswers'])
            ->disableOriginalConstructor()->getMock();
        $this->coreRegistryMock->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn($orderMock);
        $orderMock->expects($this->once())->method('getOscSurveyAnswers')->willReturn('answers');

        $this->assertEquals('answers', $this->surveyBlock->getSurveyAnswers());
    }

    public function testGetSurveyAnswersWithEmptyOrder()
    {
        $this->coreRegistryMock->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn(null);

        $this->assertEmpty($this->surveyBlock->getSurveyAnswers());
    }
}
