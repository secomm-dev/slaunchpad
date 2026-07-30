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

use Exception;
use Magento\Config\Model\ResourceModel\Config as ModelConfig;
use Magento\Customer\Model\Attribute;
use Magento\Customer\Model\AttributeMetadataDataProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\GiftMessage\Helper\Message;
use Mageplaza\Osc\Helper\Data as OscHelper;
use Mageplaza\Osc\Observer\OscConfigObserver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OscConfigObserverTest extends TestCase
{
    /**
     * @var ModelConfig|MockObject
     */
    private $modelConfigMock;

    /**
     * @var OscHelper|MockObject
     */
    private $oscHelperMock;

    /**
     * @var AttributeMetadataDataProvider|MockObject
     */
    private $attributeMetadataDataProviderMock;

    private $observer;

    protected function setUp(): void
    {
        $this->modelConfigMock = $this->getMockBuilder(ModelConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->oscHelperMock = $this->getMockBuilder(OscHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->attributeMetadataDataProviderMock = $this->getMockBuilder(AttributeMetadataDataProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->observer = new OscConfigObserver(
            $this->modelConfigMock,
            $this->oscHelperMock,
            $this->attributeMetadataDataProviderMock
        );
    }

    /**
     * @return array
     */
    public static function providerTestExecute()
    {
        return [
            [1, 1, true],
            [0, 1, true],
            [1, 0, true],
            [0, 0, true],
            [0, 0, false]
        ];
    }

    /**
     * @param int  $store
     * @param int  $website
     * @param bool $hasAttribute
     *
     * @dataProvider providerTestExecute
     *
     * @throws Exception
     */
    public function testExecute($store, $website, $hasAttribute)
    {
        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = 0;

        /**
         * @var Observer $observerMock
         */
        $observerMock = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventMock = $this->getMockBuilder(Event::class)
            ->addMethods(['getStore', 'getWebsite'])
            ->disableOriginalConstructor()
            ->getMock();
        $observerMock->expects($this->exactly(2))->method('getEvent')->willReturn($eventMock);
        $isDisabledGiftMessage = true;
        $isEnableGiftMessageItems = true;
        $eventMock->expects($this->once())->method('getStore')->willReturn($store);
        $eventMock->expects($this->once())->method('getWebsite')->willReturn($website);
        $this->oscHelperMock->expects($this->once())
            ->method('isDisabledGiftMessage')
            ->willReturn($isDisabledGiftMessage);
        $this->oscHelperMock->expects($this->exactly(2))
            ->method('isEnableGiftMessageItems')
            ->willReturn($isEnableGiftMessageItems);
        $disabledPaymentTOC = false;
        $disabledReviewTOC = true;

        $this->oscHelperMock->expects($this->once())
            ->method('disabledPaymentTOC')
            ->willReturn($disabledPaymentTOC);
        $this->oscHelperMock->expects($this->once())
            ->method('disabledReviewTOC')
            ->willReturn($disabledReviewTOC);
        $this->modelConfigMock->expects($this->exactly(4))
            ->method('saveConfig')
            ->willReturnSelf();

        if (!$store && !$website) {
            $attribute = $hasAttribute
                ? $this->getMockBuilder(Attribute::class)->disableOriginalConstructor()->getMock()
                : false;

            $this->oscHelperMock->expects($this->once())
                ->method('getShowCustomerGrid')
                ->willReturn('');

            $getAttributeCallCount = 0;
            $this->attributeMetadataDataProviderMock->expects($this->exactly(3))
                ->method('getAttribute')
                ->willReturnCallback(function ($entityType, $fieldCode) use (&$getAttributeCallCount, $attribute) {
                    $getAttributeCallCount++;
                    $this->assertEquals('customer_address', $entityType);
                    match ($getAttributeCallCount) {
                        1 => $this->assertEquals('mposc_field_1', $fieldCode),
                        2 => $this->assertEquals('mposc_field_2', $fieldCode),
                        3 => $this->assertEquals('mposc_field_3', $fieldCode),
                    };
                    return $attribute;
                });
            if ($attribute) {
                $label = 'test';
                $labelCallCount = 0;
                $this->oscHelperMock->expects($this->exactly(3))
                    ->method('getCustomFieldLabel')
                    ->willReturnCallback(function ($fieldNum) use (&$labelCallCount, $label) {
                        $labelCallCount++;
                        match ($labelCallCount) {
                            1 => $this->assertEquals(1, $fieldNum),
                            2 => $this->assertEquals(2, $fieldNum),
                            3 => $this->assertEquals(3, $fieldNum),
                        };
                        return $label;
                    });
                $attribute->expects($this->exactly(3))
                    ->method('setDefaultFrontendLabel')
                    ->with($label)
                    ->willReturnSelf();
                $attribute->expects($this->exactly(3))
                    ->method('save')
                    ->willReturnSelf();

                $attribute->expects($this->exactly(12))
                    ->method('setData')
                    ->willReturnSelf();
            }
        }

        $this->observer->execute($observerMock);
    }
}
