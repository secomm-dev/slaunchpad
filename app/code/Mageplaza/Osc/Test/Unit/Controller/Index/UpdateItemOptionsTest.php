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

namespace Mageplaza\Osc\Test\Unit\Controller\Index;

use Exception;
use Magento\Checkout\Model\Cart as CustomerCart;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Osc\Controller\Index\UpdateItemOptions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class UpdateItemOptions
 *
 */
class UpdateItemOptionsTest extends TestCase
{
    /**
     * @var ResolverInterface|MockObject
     */
    private $resolverMock;

    /**
     * @var JsonFactory|MockObject
     */
    private $resultJsonFactoryMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfigMock;

    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManagerMock;

    /**
     * @var Validator|MockObject
     */
    private $formKeyValidatorMock;

    /**
     * @var CustomerCart|MockObject
     */
    private $cartMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private $eventMock;

    /**
     * @var ResponseInterface|MockObject
     */
    private $responseMock;

    /**
     * @var UpdateItemOptions
     */
    private $updateItemOptionsController;

    protected function setUp(): void
    {
        /**
         * @var Context|MockObject $context
         */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestMock = $this->getMockForAbstractClass(RequestInterface::class);
        $this->eventMock = $this->getMockForAbstractClass(ManagerInterface::class);
        $this->responseMock = $this->getMockForAbstractClass(ResponseInterface::class);
        $context->method('getRequest')->willReturn($this->requestMock);
        $context->method('getEventManager')->willReturn($this->eventMock);
        $context->method('getResponse')->willReturn($this->responseMock);
        $this->scopeConfigMock = $this->getMockForAbstractClass(ScopeConfigInterface::class);
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()->getMock();
        $this->storeManagerMock = $this->getMockForAbstractClass(StoreManagerInterface::class);
        $this->formKeyValidatorMock = $this->getMockBuilder(Validator::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartMock = $this->getMockBuilder(CustomerCart::class)
            ->disableOriginalConstructor()->getMock();
        $this->resolverMock = $this->getMockForAbstractClass(ResolverInterface::class);
        $this->resultJsonFactoryMock = $this->getMockBuilder(JsonFactory::class)
            ->disableOriginalConstructor()->getMock();
        $this->loggerMock = $this->getMockForAbstractClass(LoggerInterface::class);

        $this->updateItemOptionsController = new UpdateItemOptions(
            $context,
            $this->scopeConfigMock,
            $this->checkoutSessionMock,
            $this->storeManagerMock,
            $this->formKeyValidatorMock,
            $this->cartMock,
            $this->resolverMock,
            $this->resultJsonFactoryMock,
            $this->loggerMock
        );
    }

    public function testExecute()
    {
        $id = 1;
        $paramsMock = [
            'id' => '86',
            'super_attribute' =>
                [
                    142 => '5596',
                    93 => '5486',
                ],
            'qty' => 1,
        ];
        $locale = 'en_US';
        $jsonMock = $this->getMockBuilder(Json::class)->disableOriginalConstructor()->getMock();
        $this->resultJsonFactoryMock->expects($this->once())->method('create')->willReturn($jsonMock);

        $this->requestMock->expects($this->once())->method('getParam')->with('id')->willReturn($id);
        $paramsMock['options'] = [];
        $this->requestMock->expects($this->once())->method('getParams')->willReturn($paramsMock);

        $this->resolverMock->expects($this->once())->method('getLocale')->willReturn($locale);
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $itemMock = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->getMock();

        $quoteMock->expects($this->once())->method('getItemById')->with($id)->willReturn($itemMock);
        $this->cartMock->expects($this->once())
            ->method('updateItem')
            ->with($id, new DataObject($paramsMock))
            ->willReturn($itemMock);

        $this->cartMock->expects($this->once())->method('save')->willReturnSelf();
        $this->eventMock->expects($this->once())
            ->method('dispatch')
            ->with(
                'checkout_cart_update_item_complete',
                [
                    'item' => $itemMock,
                    'request' => $this->requestMock,
                    'response' => $this->responseMock
                ]
            );

        $jsonMock->expects($this->once())->method('setData')->with(['success' => true])->willReturnSelf();

        $this->updateItemOptionsController->execute();
    }

    public function testExecuteWithQuoteItemNotFound()
    {
        $id = 1;
        $paramsMock = [
            'id' => '86',
            'super_attribute' =>
                [
                    142 => '5596',
                    93 => '5486',
                ],
            'qty' => 1,
        ];
        $locale = 'en_US';
        $jsonMock = $this->getMockBuilder(Json::class)->disableOriginalConstructor()->getMock();
        $this->resultJsonFactoryMock->expects($this->once())->method('create')->willReturn($jsonMock);

        $this->requestMock->expects($this->once())->method('getParam')->with('id')->willReturn($id);
        $paramsMock['options'] = [];
        $this->requestMock->expects($this->once())->method('getParams')->willReturn($paramsMock);

        $this->resolverMock->expects($this->once())->method('getLocale')->willReturn($locale);
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);

        $quoteMock->expects($this->once())->method('getItemById')->with($id)->willReturn(false);

        $jsonMock->expects($this->once())
            ->method('setData')
            ->with(
                [
                    'error' => new Phrase(
                        "The quote item isn't found. Verify the item and try again."
                    )
                ]
            )->willReturnSelf();

        $this->updateItemOptionsController->execute();
    }

    public function testExecuteWithErrorMessage()
    {
        $id = 1;
        $paramsMock = [
            'id' => '86',
            'super_attribute' =>
                [
                    142 => '5596',
                    93 => '5486',
                ],
            'qty' => 1,
        ];
        $locale = 'en_US';
        $jsonMock = $this->getMockBuilder(Json::class)->disableOriginalConstructor()->getMock();
        $this->resultJsonFactoryMock->expects($this->once())->method('create')->willReturn($jsonMock);

        $this->requestMock->expects($this->once())->method('getParam')->with('id')->willReturn($id);
        $paramsMock['options'] = [];
        $this->requestMock->expects($this->once())->method('getParams')->willReturn($paramsMock);

        $this->resolverMock->expects($this->once())->method('getLocale')->willReturn($locale);
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $itemMock = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->once())->method('getItemById')->with($id)->willReturn($itemMock);

        $this->cartMock->expects($this->once())
            ->method('updateItem')
            ->with($id, new DataObject($paramsMock))
            ->willReturn('This quote item does not exist.');

        $jsonMock->expects($this->once())
            ->method('setData')
            ->with(
                [
                    'error' => 'This quote item does not exist.'
                ]
            )->willReturnSelf();

        $this->updateItemOptionsController->execute();
    }

    public function testExecuteWithItemHasError()
    {
        $id = 1;
        $paramsMock = [
            'id' => '86',
            'super_attribute' =>
                [
                    142 => '5596',
                    93 => '5486',
                ],
            'qty' => 1,
        ];
        $locale = 'en_US';
        $jsonMock = $this->getMockBuilder(Json::class)->disableOriginalConstructor()->getMock();
        $this->resultJsonFactoryMock->expects($this->once())->method('create')->willReturn($jsonMock);

        $this->requestMock->expects($this->once())->method('getParam')->with('id')->willReturn($id);
        $paramsMock['options'] = [];
        $this->requestMock->expects($this->once())->method('getParams')->willReturn($paramsMock);

        $this->resolverMock->expects($this->once())->method('getLocale')->willReturn($locale);
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $itemMock = $this->getMockBuilder(Item::class)
            ->setMethods(
                [
                'getHasError',
                'getMessage'
                ]
            )->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->once())->method('getItemById')->with($id)->willReturn($itemMock);

        $this->cartMock->expects($this->once())
            ->method('updateItem')
            ->with($id, new DataObject($paramsMock))
            ->willReturn($itemMock);
        $itemMock->expects($this->once())->method('getHasError')->willReturn(true);
        $itemMock->expects($this->once())->method('getMessage')->willReturn('test');

        $jsonMock->expects($this->once())
            ->method('setData')
            ->with(
                [
                    'error' => 'test'
                ]
            )->willReturnSelf();

        $this->updateItemOptionsController->execute();
    }

    public function testExecuteWithLocalizedException()
    {
        $id = 1;
        $paramsMock = [
            'id' => '86',
            'super_attribute' =>
                [
                    142 => '5596',
                    93 => '5486',
                ],
            'qty' => 1,
        ];
        $locale = 'en_US';
        $jsonMock = $this->getMockBuilder(Json::class)->disableOriginalConstructor()->getMock();
        $this->resultJsonFactoryMock->expects($this->once())->method('create')->willReturn($jsonMock);

        $this->requestMock->expects($this->once())->method('getParam')->with('id')->willReturn($id);
        $paramsMock['options'] = [];
        $this->requestMock->expects($this->once())->method('getParams')->willReturn($paramsMock);

        $this->resolverMock->expects($this->once())->method('getLocale')->willReturn($locale);
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $itemMock = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->once())->method('getItemById')->with($id)->willReturn($itemMock);

        $this->cartMock->expects($this->once())
            ->method('updateItem')
            ->with($id, new DataObject($paramsMock))
            ->willThrowException(new LocalizedException(new Phrase('test')));

        $jsonMock->expects($this->once())
            ->method('setData')
            ->with(
                [
                    'error' => 'test'
                ]
            )->willReturnSelf();

        $this->updateItemOptionsController->execute();
    }

    public function testExecuteWithException()
    {
        $id = 1;
        $paramsMock = [
            'id' => '86',
            'super_attribute' =>
                [
                    142 => '5596',
                    93 => '5486',
                ],
            'qty' => 1,
        ];
        $locale = 'en_US';
        $jsonMock = $this->getMockBuilder(Json::class)->disableOriginalConstructor()->getMock();
        $this->resultJsonFactoryMock->expects($this->once())->method('create')->willReturn($jsonMock);

        $this->requestMock->expects($this->once())->method('getParam')->with('id')->willReturn($id);
        $paramsMock['options'] = [];
        $this->requestMock->expects($this->once())->method('getParams')->willReturn($paramsMock);

        $this->resolverMock->expects($this->once())->method('getLocale')->willReturn($locale);
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $itemMock = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->once())->method('getItemById')->with($id)->willReturn($itemMock);

        $exception = new Exception(new Phrase('test'));
        $this->cartMock->expects($this->once())
            ->method('updateItem')
            ->with($id, new DataObject($paramsMock))
            ->willThrowException($exception);
        $this->loggerMock->expects($this->once())->method('critical')->with($exception);

        $jsonMock->expects($this->once())
            ->method('setData')
            ->with(
                [
                    'error' => new Phrase('We can\'t update the item right now.')
                ]
            )->willReturnSelf();

        $this->updateItemOptionsController->execute();
    }
}
