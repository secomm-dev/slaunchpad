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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\CustomerAttributes;

use Magento\Eav\Model\Attribute;
use Mageplaza\CustomerAttributes\Helper\Data;
use Mageplaza\Osc\Helper\Address;
use Mageplaza\Osc\Model\Plugin\CustomerAttributes\Helper;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    /**
     * @var Address
     */
    private $helperMock;

    /**
     * @var Helper
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->helperMock = $this->getMockBuilder(Address::class)->disableOriginalConstructor()->getMock();

        $this->plugin = new Helper($this->helperMock);
    }

    public function testAfterGetAttributeWithFiltersWithInvalidOscPage()
    {
        $subject = $this->getMockBuilder(Data::class)->disableOriginalConstructor()->getMock();

        $this->helperMock->expects($this->once())->method('isOscPage')->willReturn(false);

        $this->plugin->afterGetAttributeWithFilters($subject, []);
    }

    public function testAfterGetAttributeWithFilters()
    {
        $subject = $this->getMockBuilder(Data::class)->disableOriginalConstructor()->getMock();

        $this->helperMock->expects($this->once())->method('isOscPage')->willReturn(true);
        $fieldPosition = [
            [
                'code' => 'my_attribute',
            ]
        ];
        $this->helperMock->expects($this->once())->method('getFieldPosition')->willReturn($fieldPosition);

        $attributeMock = $this->getMockBuilder(Attribute::class)->disableOriginalConstructor()->getMock();
        $resultMock = [$attributeMock];
        $attributeMock->expects($this->once())->method('getAttributeCode')->willReturn('my_attribute');

        $this->assertEquals(
            $resultMock,
            $this->plugin->afterGetAttributeWithFilters($subject, $resultMock)
        );
    }

    public function testAfterGetAttributeWithEmptyAttributes()
    {
        $subject = $this->getMockBuilder(Data::class)->disableOriginalConstructor()->getMock();

        $this->helperMock->expects($this->once())->method('isOscPage')->willReturn(true);
        $fieldPosition = [
            [
                'code' => 'my_attribute',
            ]
        ];
        $this->helperMock->expects($this->once())->method('getFieldPosition')->willReturn($fieldPosition);

        $attributeMock = $this->getMockBuilder(Attribute::class)->disableOriginalConstructor()->getMock();
        $resultMock = [$attributeMock];
        $attributeMock->expects($this->once())->method('getAttributeCode')->willReturn('test');

        $this->assertEquals(
            [],
            $this->plugin->afterGetAttributeWithFilters($subject, $resultMock)
        );
    }
}
