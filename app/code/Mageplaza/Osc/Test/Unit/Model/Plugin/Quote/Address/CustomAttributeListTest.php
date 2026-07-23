<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Quote\Address;

use Mageplaza\Osc\Model\Plugin\Quote\Address\CustomAttributeList;
use PHPUnit\Framework\TestCase;

class CustomAttributeListTest extends TestCase
{
    /**
     * @var \Mageplaza\Osc\Model\CustomAttributeList
     */
    private $customAttributeListMock;

    /**
     * @var CustomAttributeList
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->customAttributeListMock = $this->getMockBuilder(\Mageplaza\Osc\Model\CustomAttributeList::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new CustomAttributeList($this->customAttributeListMock);
    }

    public function testMethod()
    {
        $methods = get_class_methods(\Magento\Quote\Model\Quote\Address\CustomAttributeList::class);

        $this->assertTrue(in_array('getAttributes', $methods));
    }

    public function testAfterGetAttributes()
    {
        /**
         * @var \Magento\Quote\Model\Quote\Address\CustomAttributeList $subject
         */
        $subject = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address\CustomAttributeList::class)
            ->disableOriginalConstructor()->getMock();
        $attributes = [
            [
                'attribute_id' => 1
            ]
        ];
        $this->customAttributeListMock->expects($this->once())
            ->method('getAttributes')
            ->willReturn($attributes);

        $this->assertEquals($attributes, $this->plugin->afterGetAttributes($subject, []));
    }
}
