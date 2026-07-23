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

namespace Mageplaza\Osc\Test\Unit\Block;

use Magento\Checkout\Model\Session;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Mageplaza\Osc\Block\Survey;
use Mageplaza\Osc\Helper\Data as OscHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SurveyTest extends TestCase
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
     * @var Survey
     */
    private $surveyBlock;

    protected function setUp(): void
    {
        /**
         * @var Context $contextMock
         */
        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->oscHelperMock = $this->getMockBuilder(OscHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->checkoutSessionMock = $this->getMockBuilder(CheckoutSession::class)
            ->setMethods(
                [
                'getLastRealOrder',
                'setOscData'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $this->initGetLastOrderId();

        $this->surveyBlock = new Survey(
            $contextMock,
            $this->oscHelperMock,
            $this->checkoutSessionMock
        );
    }

    public function testEnableSurvey()
    {
        $this->oscHelperMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $this->oscHelperMock->expects($this->once())->method('isDisableSurvey')->willReturn(false);
        $this->assertTrue($this->surveyBlock->isEnableSurvey());
    }

    public function testDisableSurvey()
    {
        $this->oscHelperMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $this->oscHelperMock->expects($this->once())->method('isDisableSurvey')->willReturn(true);
        $this->assertFalse($this->surveyBlock->isEnableSurvey());
    }

    public function initGetLastOrderId()
    {
        $entityId = 1;
        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($orderMock);

        $orderMock->expects($this->once())->method('getEntityId')->willReturn($entityId);
        $this->checkoutSessionMock->expects($this->once())
            ->method('setOscData')
            ->with(['survey' => ['orderId' => $entityId]]);
    }

    public function testGetSurveyQuestion()
    {
        $this->oscHelperMock->expects($this->once())->method('getSurveyQuestion');

        $this->surveyBlock->getSurveyQuestion();
    }

    public function testGetAllSurveyAnswer()
    {
        $surveyAnswers = [
            'key_1' => [
                'value' => 'test'
            ]
        ];
        $this->oscHelperMock->expects($this->once())->method('getSurveyAnswers')->willReturn($surveyAnswers);

        $this->assertEquals(
            [['id' => 'key_1', 'value' => 'test']],
            $this->surveyBlock->getAllSurveyAnswer()
        );
    }

    public function testIsAllowCustomerAddOtherOption()
    {
        $this->oscHelperMock->expects($this->once())->method('getSurveyQuestion');

        $this->surveyBlock->getSurveyQuestion();
    }

    public function testGetOscRoute()
    {
        $this->oscHelperMock->expects($this->once())->method('getOscRoute');

        $this->surveyBlock->getOscRoute();
    }
}
