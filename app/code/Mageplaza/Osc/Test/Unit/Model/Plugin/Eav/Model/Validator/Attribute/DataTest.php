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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Eav\Model\Validator\Attribute;

use Magento\Eav\Model\AttributeDataFactory;
use Magento\Eav\Model\Validator\Attribute\Data as AttributeData;
use Mageplaza\Osc\Helper\Data as HelperData;
use Mageplaza\Osc\Model\Plugin\Eav\Model\Validator\Attribute\Data;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{
    /**
     * @var HelperData|MockObject
     */
    private $oscHelperDataMock;

    /**
     * @var AttributeDataFactory|MockObject
     */
    private $attributeDataFactoryMock;

    /**
     * @var Data
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->attributeDataFactoryMock = $this->getMockBuilder(AttributeDataFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->oscHelperDataMock = $this->getMockBuilder(HelperData::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new Data(
            $this->attributeDataFactoryMock,
            $this->oscHelperDataMock
        );
    }

    public function testMethod()
    {
        $methods = get_class_methods(AttributeData::class);

        $this->assertTrue(in_array('isValid', $methods));
    }

    /**
     * @return array
     */
    public function providerAfterIsValid()
    {
        return [
            [true, true],
            [false, false]
        ];
    }

    /**
     * @param boolean $result
     * @param boolean $isFlagOscMethodRegister
     *
     * @dataProvider providerAfterIsValid
     */
    public function testAfterIsValid($result, $isFlagOscMethodRegister)
    {
        /**
         * @var AttributeData $subject
         */
        $subject = $this->getMockBuilder(AttributeData::class)->disableOriginalConstructor()->getMock();
        $this->oscHelperDataMock->expects($this->once())
            ->method('isFlagOscMethodRegister')
            ->willReturn($isFlagOscMethodRegister);

        $this->assertEquals($result, $this->plugin->afterIsValid($subject, $result));
    }
}
