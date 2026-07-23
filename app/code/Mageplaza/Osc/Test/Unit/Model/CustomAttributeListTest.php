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

namespace Mageplaza\Osc\Test\Unit\Model;

use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\Api\Data\AttributeMetadataInterface;
use Mageplaza\Osc\Model\CustomAttributeList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CustomAttributeListTest extends TestCase
{
    /**
     * @var AddressMetadataInterface|MockObject
     */
    private $addressMetadataMock;

    /**
     * @var CustomAttributeList
     */
    private $model;

    protected function setUp(): void
    {
        $this->addressMetadataMock = $this->getMockBuilder(AddressMetadataInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->model = new CustomAttributeList($this->addressMetadataMock);
    }

    public function testGetAttributes()
    {
        $attribute = $this->getMockForAbstractClass(AttributeMetadataInterface::class);

        $this->addressMetadataMock->expects($this->exactly(3))
            ->method('getAttributeMetadata')
            ->withConsecutive(['mposc_field_1'], ['mposc_field_2'], ['mposc_field_3'])
            ->willReturnOnConsecutiveCalls($attribute, $attribute, $attribute);

        $attribute->expects($this->exactly(3))
            ->method('getAttributeCode')
            ->willReturnOnConsecutiveCalls('attribute1', 'attribute2', 'attribute3');

        $this->assertEquals(
            [
                'attribute1' => $attribute,
                'attribute2' => $attribute,
                'attribute3' => $attribute
            ],
            $this->model->getAttributes()
        );
    }
}
