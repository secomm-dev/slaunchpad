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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\OrderAttributes;

use Mageplaza\OrderAttributes\Helper\Data;
use Mageplaza\OrderAttributes\Model\Attribute;
use Mageplaza\Osc\Helper\Address;
use Mageplaza\Osc\Model\Plugin\OrderAttributes\Helper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    /**
     * @var Address|MockObject
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

    /**
     * @return array
     */
    public function providerTestAfterGetFilteredAttributes()
    {
        return [
            [2, true, 3],
            [2, false, 2],
            [4, true, 5],
            [5, false, 4],
        ];
    }

    /**
     * @param int     $position
     * @param boolean $bottom
     * @param int     $positionResult
     *
     * @dataProvider providerTestAfterGetFilteredAttributes
     */
    public function testAfterGetFilteredAttributes($position, $bottom, $positionResult)
    {
        $this->helperMock->expects($this->once())->method('isOscPage')->willReturn(true);

        /**
         * @var Data $subject
         */
        $subject = $this->getMockBuilder(Data::class)->disableOriginalConstructor()->getMock();
        $OAFields = [
            [
                'code' => 'image',
                'colspan' => 6,
                'required' => false,
                'bottom' => $bottom,
                'isNewRow' => false,
            ],
        ];
        $this->helperMock->expects($this->once())->method('getOAFieldPosition')->willReturn($OAFields);
        $attributeMock = $this->getMockBuilder(Attribute::class)
            ->setMethods(
                [
                'getPosition',
                'getAttributeCode',
                'setPosition',
                'setSortOrder',
                'setIsRequired'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $attributeMock->expects($this->once())->method('getPosition')->willReturn($position);
        $attributeMock->expects($this->once())->method('getAttributeCode')->willReturn('image');

        $attributeMock->expects($this->once())->method('setPosition')->willReturn($positionResult);
        $attributeMock->expects($this->once())->method('setSortOrder')->willReturn(1);
        $attributeMock->expects($this->once())->method('setIsRequired')->willReturn(false);

        $this->assertEquals([$attributeMock], $this->plugin->afterGetFilteredAttributes($subject, [$attributeMock]));
    }
}
