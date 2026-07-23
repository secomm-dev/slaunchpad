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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Eav\Model\Attribute;

use Magento\Customer\Model\Attribute\Data\Postcode as CorePostCode;
use Magento\Eav\Model\Attribute;
use Magento\Framework\Exception\LocalizedException;
use Mageplaza\Osc\Helper\Address;
use Mageplaza\Osc\Model\Plugin\Eav\Model\Attribute\Postcode;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount as InvokedCountMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PostcodeTest extends TestCase
{
    /**
     * @var Address|MockObject
     */
    private $helperMock;

    /**
     * @var Postcode
     */
    private $plugin;

    /**
     * @var CorePostCode|MockObject
     */
    private $subject;

    protected function setUp(): void
    {
        $this->helperMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new Postcode($this->helperMock);
        $this->subject = $this->getMockBuilder(CorePostCode::class)->disableOriginalConstructor()->getMock();
    }

    public function testMethod()
    {
        $methods = get_class_methods(CorePostCode::class);

        $this->assertTrue(in_array('validateValue', $methods));
    }

    /**
     * @return array
     */
    public function providerTestAfterValidateValue()
    {
        return [
            [
                true,
                [],
                self::never()
            ],
            [
                true,
                [
                    [
                        'code' => 'test'
                    ]
                ],
                self::once()
            ],
            [
                false,
                [
                    [
                        'code' => 'my_attribute',
                        'required' => true
                    ]
                ],
                self::once()
            ],
            [
                true,
                [
                    [
                        'code' => 'my_attribute',
                    ]
                ],
                self::once()
            ]
        ];
    }

    /**
     * @param boolean             $result
     * @param array               $fieldPosition
     * @param InvokedCountMatcher $attributeCodeExpects
     *
     * @dataProvider providerTestAfterValidateValue
     *
     * @throws LocalizedException
     */
    public function testAfterValidateValue($result, $fieldPosition, $attributeCodeExpects)
    {
        $attributeMock = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->subject->expects($this->once())->method('getAttribute')->willReturn($attributeMock);
        $this->helperMock->expects($this->once())->method('getFieldPosition')->willReturn($fieldPosition);
        $attributeMock->expects($attributeCodeExpects)->method('getAttributeCode')->willReturn('my_attribute');

        $this->assertEquals($result, $this->plugin->afterValidateValue($this->subject, $result));
    }
}
