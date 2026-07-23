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
    public function providerTestExecute()
    {
        $attribute = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()->getMock();

        return [
            [1, 1, $attribute],
            [0, 1, $attribute],
            [1, 0, $attribute],
            [0, 0, $attribute],
            [0, 0, false]
        ];
    }

    /**
     * @param int              $store
     * @param int              $website
     * @param false|MockObject $attribute
     *
     * @dataProvider providerTestExecute
     *
     * @throws Exception
     */
    public function testExecute($store, $website, $attribute)
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
            ->setMethods(['getStore', 'getWebsite'])
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
        $this->oscHelperMock->expects($this->once())
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
        $this->modelConfigMock->expects($this->exactly(3))
            ->method('saveConfig')
            ->willReturnOnConsecutiveCalls(
                [
                    Message::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ITEMS,
                    $isDisabledGiftMessage,
                    $scope,
                    $scopeId
                ],
                [
                    Message::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ITEMS,
                    $isEnableGiftMessageItems,
                    $scope,
                    $scopeId
                ],
                [
                    'checkout/options/enable_agreements',
                    $disabledPaymentTOC || $disabledReviewTOC,
                    $scope,
                    $scopeId
                ]
            )->willReturnSelf();

        if (!$store && !$website) {
            $this->attributeMetadataDataProviderMock->expects($this->at(0))
                ->method('getAttribute')
                ->with('customer_address', 'mposc_field_1')
                ->willReturn($attribute);
            $this->attributeMetadataDataProviderMock->expects($this->at(1))
                ->method('getAttribute')
                ->with('customer_address', 'mposc_field_2')
                ->willReturn($attribute);
            $this->attributeMetadataDataProviderMock->expects($this->at(2))
                ->method('getAttribute')
                ->with('customer_address', 'mposc_field_3')
                ->willReturn($attribute);
            if ($attribute) {
                $label = 'test';
                $this->oscHelperMock->expects($this->exactly(3))
                    ->method('getCustomFieldLabel')
                    ->withConsecutive([1], [2], [3])
                    ->willReturn($label);
                $attribute->expects($this->exactly(3))
                    ->method('setDefaultFrontendLabel')
                    ->with($label)
                    ->willReturnSelf();
                $attribute->expects($this->exactly(3))
                    ->method('save')
                    ->willReturnSelf();
            }
        }

        $this->observer->execute($observerMock);
    }
}
