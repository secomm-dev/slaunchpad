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

namespace Mageplaza\Osc\Test\Unit\Controller\Survey;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json as JsonHelper;
use Magento\Sales\Model\Order;
use Mageplaza\Osc\Controller\Survey\Save;
use Mageplaza\Osc\Helper\Data as OscHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    /**
     * @var JsonHelper|MockObject
     */
    private $jsonHelperMock;

    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var Order|MockObject
     */
    private $orderMock;

    /**
     * @var OscHelper|MockObject
     */
    private $oscHelperMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    private $responseMock;

    /**
     * @var Save
     */
    private $surveySaveController;

    protected function setUp(): void
    {
        /**
         * @var Context|MockObject $context
         */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->requestMock = $this->getMockForAbstractClass(RequestInterface::class);
        $this->responseMock = $this->getMockBuilder(ResponseInterface::class)
            ->setMethods(['representJson'])
            ->getMockForAbstractClass();
        $context->method('getRequest')->willReturn($this->requestMock);
        $context->method('getResponse')->willReturn($this->responseMock);

        $this->jsonHelperMock = $this->getMockBuilder(JsonHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->setMethods(
                [
                'getOscData',
                'unsOscData'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->oscHelperMock = $this->getMockBuilder(OscHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->surveySaveController = new Save(
            $context,
            $this->jsonHelperMock,
            $this->checkoutSessionMock,
            $this->orderMock,
            $this->oscHelperMock
        );
    }

    public function testExecute()
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('answerChecked', [])
            ->willReturn(['value 1', 'value 2']);
        $this->checkoutSessionMock->expects($this->exactly(2))
            ->method('getOscData')
            ->willReturn(['survey' => ['orderId' => 52]]);

        $this->orderMock->expects($this->once())
            ->method('load')
            ->with(52)
            ->willReturnSelf();
        $this->oscHelperMock->expects($this->once())->method('getSurveyQuestion')->willReturn('Question');
        $this->orderMock->expects($this->exactly(2))
            ->method('setData')
            ->withConsecutive(['osc_survey_question', 'Question'], ['osc_survey_answers', 'value 1 - value 2 '])
            ->willReturnSelf();

        $this->orderMock->expects($this->once())->method('save')->willReturnSelf();
        $this->checkoutSessionMock->expects($this->once())->method('unsOscData');
        $httpMock = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()->getMock();
        $responseData = [
            'status' => 'success',
            'message' => new Phrase('Thank you for completing our survey!')
        ];
        $responseJson = '{"status":"success","message":"Thank you for completing our survey!"}';
        $this->jsonHelperMock->expects($this->once())
            ->method('serialize')
            ->with($responseData)->willReturn($responseJson);
        $this->responseMock->expects($this->once())
            ->method('representJson')
            ->with($responseJson)
            ->willReturn($httpMock);

        $this->surveySaveController->execute();
    }

    public function testExecuteWithException()
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('answerChecked', [])
            ->willReturn(['value 1', 'value 2']);
        $this->checkoutSessionMock->expects($this->exactly(2))
            ->method('getOscData')
            ->willReturn(['survey' => ['orderId' => 52]]);

        $this->orderMock->expects($this->once())
            ->method('load')
            ->with(52)
            ->willReturnSelf();
        $this->oscHelperMock->expects($this->once())->method('getSurveyQuestion')->willReturn('Question');
        $this->orderMock->expects($this->exactly(2))
            ->method('setData')
            ->withConsecutive(['osc_survey_question', 'Question'], ['osc_survey_answers', 'value 1 - value 2 '])
            ->willReturnSelf();

        $this->orderMock->expects($this->once())->method('save')->willThrowException(new Exception());

        $httpMock = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()->getMock();
        $responseData = [
            'status' => 'error',
            'message' => new Phrase('Can\'t save survey answer. Please try again!')
        ];
        $responseJson = '{"status":"error","message":"Can\'t save survey answer. Please try again!"}';
        $this->jsonHelperMock->expects($this->once())
            ->method('serialize')
            ->with($responseData)->willReturn($responseJson);
        $this->responseMock->expects($this->once())
            ->method('representJson')
            ->with($responseJson)
            ->willReturn($httpMock);

        $this->surveySaveController->execute();
    }
}
