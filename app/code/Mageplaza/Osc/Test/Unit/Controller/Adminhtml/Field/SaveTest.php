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

namespace Mageplaza\Osc\Test\Unit\Controller\Adminhtml\Field;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Phrase;
use Mageplaza\Osc\Controller\Adminhtml\Field\Save;
use Mageplaza\Osc\Helper\Data as OscHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    /**
     * @var Config|MockObject
     */
    private $resourceConfigMock;

    /**
     * @var ReinitableConfigInterface|MockObject
     */
    private $appConfigMock;

    /**
     * @var JsonFactory|MockObject
     */
    private $resultJsonFactoryMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var Save
     */
    private $fieldSaveController;

    protected function setUp(): void
    {
        /**
         * @var Context|MockObject $context
         */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestMock = $this->getMockForAbstractClass(RequestInterface::class);
        $context->method('getRequest')->willReturn($this->requestMock);
        $this->appConfigMock = $this->getMockForAbstractClass(ReinitableConfigInterface::class);
        $this->resourceConfigMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()->getMock();
        $this->resultJsonFactoryMock = $this->getMockBuilder(JsonFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->fieldSaveController = new Save(
            $context,
            $this->resourceConfigMock,
            $this->appConfigMock,
            $this->resultJsonFactoryMock
        );
    }

    public function testExecute()
    {
        $jsonMock = $this->getMockBuilder(Json::class)->disableOriginalConstructor()->getMock();
        $this->resultJsonFactoryMock->expects($this->once())->method('create')->willReturn($jsonMock);
        $getParamCount = 0;
        $this->requestMock->expects($this->exactly(2))
            ->method('getParam')
            ->willReturnCallback(function ($param, $default = null) use (&$getParamCount) {
                $getParamCount++;
                if ($getParamCount === 1) {
                    $this->assertEquals('fields', $param);
                    $this->assertFalse($default);
                } else {
                    $this->assertEquals('oaFields', $param);
                    $this->assertFalse($default);
                }
                return 'test';
            });

        $saveConfigCount = 0;
        $this->resourceConfigMock->expects($this->exactly(2))
            ->method('saveConfig')
            ->willReturnCallback(function ($path, $value, $scope, $scopeId) use (&$saveConfigCount) {
                $saveConfigCount++;
                if ($saveConfigCount === 1) {
                    $this->assertEquals(OscHelper::SORTED_FIELD_POSITION, $path);
                } else {
                    $this->assertEquals(OscHelper::OA_FIELD_POSITION, $path);
                }
                $this->assertEquals('test', $value);
                $this->assertEquals(ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scope);
                $this->assertEquals(0, $scopeId);
                return $this->resourceConfigMock;
            });

        $this->appConfigMock->expects($this->once())->method('reinit')->willReturnSelf();
        $jsonMock->expects($this->once())
            ->method('setData')
            ->with(
                [
                    'message' => (string)new Phrase('All fields have been saved.'),
                    'type' => 'success'
                ]
            )->willReturnSelf();

        $this->fieldSaveController->execute();
    }

    public function testExecuteWithException()
    {
        $jsonMock = $this->getMockBuilder(Json::class)->disableOriginalConstructor()->getMock();
        $this->resultJsonFactoryMock->expects($this->once())->method('create')->willReturn($jsonMock);
        $getParamCount2 = 0;
        $this->requestMock->expects($this->exactly(2))
            ->method('getParam')
            ->willReturnCallback(function ($param, $default = null) use (&$getParamCount2) {
                $getParamCount2++;
                if ($getParamCount2 === 1) {
                    $this->assertEquals('fields', $param);
                    $this->assertFalse($default);
                } else {
                    $this->assertEquals('oaFields', $param);
                    $this->assertFalse($default);
                }
                return 'test';
            });

        $this->resourceConfigMock->expects($this->atLeastOnce())
            ->method('saveConfig')
            ->willThrowException(new Exception(__('Test')));

        $jsonMock->expects($this->once())
            ->method('setData')
            ->with(
                [
                    'message' => 'Test',
                    'type' => 'error'
                ]
            )->willReturnSelf();

        $this->fieldSaveController->execute();
    }
}
