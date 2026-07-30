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

namespace Mageplaza\OrderAttributes\Helper {

    if (!class_exists(\Mageplaza\OrderAttributes\Helper\Data::class, false)) {
        class Data
        {
        }
    }
}

namespace Mageplaza\OrderAttributes\Model {

    if (!class_exists(\Mageplaza\OrderAttributes\Model\Attribute::class, false)) {
        class Attribute
        {
        }
    }
}

namespace Mageplaza\Osc\Test\Unit\Block\Adminhtml\Field {

    use Magento\Framework\App\ObjectManager as AppObjectManager;
    use Magento\Framework\DataObject;
    use Magento\Framework\ObjectManagerInterface;
    use Magento\Framework\Phrase;
    use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
    use Mageplaza\OrderAttributes\Helper\Data as OaHelper;
    use Mageplaza\OrderAttributes\Model\Attribute as OaAttribute;
    use Mageplaza\Osc\Block\Adminhtml\Field\Order;
    use Mageplaza\Osc\Helper\Address as HelperAddress;
    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;

    class OrderTest extends TestCase
    {
        /**
         * @var Order
         */
        private $orderBlock;

        /**
         * @var HelperAddress|MockObject
         */
        private $helperAddressMock;

        protected function setUp(): void
        {
            $objectManagerMock = $this->getMockForAbstractClass(ObjectManagerInterface::class);
            $objectManagerMock->method('get')->willReturn(new \stdClass());
            AppObjectManager::setInstance($objectManagerMock);

            $objectManager = new ObjectManager($this);

            $this->helperAddressMock = $this->getMockBuilder(HelperAddress::class)
                ->disableOriginalConstructor()
                ->getMock();

            $this->orderBlock = $objectManager->getObject(
                Order::class,
                [
                    'helper' => $this->helperAddressMock,
                ]
            );
        }

        protected function tearDown(): void
        {
            $objectManagerMock = $this->getMockForAbstractClass(ObjectManagerInterface::class);
            AppObjectManager::setInstance($objectManagerMock);
        }

        public function testGetFieldsWithEmptyField()
        {
            $this->helperAddressMock->expects($this->once())->method('isEnableOrderAttributes')->willReturn(false);
            $this->assertEquals([[], []], $this->orderBlock->getFields());
        }

        public function testGetFields()
        {
            $this->helperAddressMock->expects($this->once())
                ->method('isEnableOrderAttributes')
                ->willReturn(true);

            $oaHelperMock = $this->getMockBuilder(OaHelper::class)
                ->addMethods(['getOrderAttributesCollection'])
                ->getMock();

            $this->helperAddressMock->expects($this->once())
                ->method('getObject')
                ->with(OaHelper::class)
                ->willReturn($oaHelperMock);

            $attributeInScope = $this->getMockBuilder(OaAttribute::class)
                ->addMethods([
                    'getPosition',
                    'getAttributeCode',
                    'setColspan',
                    'setSortOrder',
                    'setColStyle',
                    'setIsRequired',
                    'setIsRequiredMp',
                ])
                ->getMock();
            $attributeInScope->method('getPosition')->willReturn(6);
            $attributeInScope->method('getAttributeCode')->willReturn('order_comment');
            $attributeInScope->method('setColspan')->willReturnSelf();
            $attributeInScope->method('setSortOrder')->willReturnSelf();
            $attributeInScope->method('setColStyle')->willReturnSelf();
            $attributeInScope->method('setIsRequired')->willReturnSelf();
            $attributeInScope->method('setIsRequiredMp')->willReturnSelf();

            $attributeOutScope = $this->getMockBuilder(OaAttribute::class)
                ->addMethods(['getPosition'])
                ->getMock();
            $attributeOutScope->method('getPosition')->willReturn(1);

            $oaHelperMock->expects($this->once())
                ->method('getOrderAttributesCollection')
                ->with(null, null, false)
                ->willReturn([$attributeInScope, $attributeOutScope]);

            $fieldPositions = [
                ['code' => 'order_comment', 'colspan' => 12, 'required' => true, 'bottom' => 0],
            ];
            $this->helperAddressMock->expects($this->once())
                ->method('getOAFieldPosition')
                ->willReturn($fieldPositions);

            $this->helperAddressMock->expects($this->once())
                ->method('getColStyle')
                ->with(12)
                ->willReturn('wide');

            $result = $this->orderBlock->getFields();

            $this->assertIsArray($result);
            $this->assertCount(2, $result);

            [$sortedFields, $availFields] = $result;

            $this->assertCount(2, $sortedFields);
            $this->assertSame($attributeInScope, $sortedFields[0]);
            $this->assertInstanceOf(DataObject::class, $sortedFields[1]);
            $this->assertEquals('Order Summary', (string) $sortedFields[1]->getData('frontend_label'));

            $this->assertEmpty($availFields);
        }

        public function testGetBlockTitle()
        {
            $result = (string) new Phrase('Order Summary');

            $this->assertEquals($result, $this->orderBlock->getBlockTitle());
        }

        public function testGetBlockId()
        {
            $this->assertEquals('mposc-order-summary', $this->orderBlock->getBlockId());
        }
    }
}
