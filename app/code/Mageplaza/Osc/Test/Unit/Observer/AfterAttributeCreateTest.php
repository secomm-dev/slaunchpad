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

use Magento\Customer\Model\Attribute as CustomerAttribute;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Mageplaza\Osc\Helper\Address;
use Mageplaza\Osc\Observer\AfterAttributeCreate;
use PHPUnit\Framework\TestCase;

class AfterAttributeCreateTest extends TestCase
{
    /**
     * @var Address
     */
    private $helperMock;

    /**
     * @var AfterAttributeCreate
     */
    private $observer;

    protected function setUp(): void
    {
        $this->helperMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->observer = new AfterAttributeCreate($this->helperMock);
    }

    /**
     * @return array
     */
    public function providerTestExecuteWithCustomerAttribute()
    {
        $fields = [
            [
                'code' => 'test1',
                'colspan' => 6,
                'required' => true,
                'bottom' => true,
                'isNewRow' => true
            ],
            [
                'code' => 'test2',
                'colspan' => 6,
                'required' => true,
                'bottom' => true,
                'isNewRow' => false,
            ]
        ];

        $fieldsJson = '[{"code":"test1","colspan":6,"required":true,"bottom":true,"isNewRow":true},{"code":"test2","colspan":6,"required":true,"bottom":true,"isNewRow":false}]';
        $newFields = $fields;
        $newFields[] = [
            'code' => 'test2',
            'colspan' => 6,
            'required' => true,
        ];

        $newFieldsJson = '[{"code":"test1","colspan":6,"required":true,"bottom":true,"isNewRow":true},{"code":"test2","colspan":6,"required":true,"bottom":true,"isNewRow":false},{"code":"test2","colspan":6,"required":true}]';

        return [
            [
                false,
                [
                    'isNewObject' => self::never(),
                    'OAField' => self::never(),
                    'useInForms' => self::never()
                ],
                '',
                $fields,
                $fieldsJson
            ],
            [
                true,
                [
                    'OAField' => self::never(),
                    'useInForms' => self::once()
                ],
                ['checkout_index_index'],
                $fields,
                $fieldsJson
            ],
            [
                true,
                [
                    'OAField' => self::never(),
                    'useInForms' => self::once()
                ],
                ['onestepcheckout_index_index'],
                $newFields,
                $newFieldsJson
            ]
        ];
    }

    /**
     * @param $isNewObject
     * @param $expects
     * @param array $usedInForm
     * @param $fields
     * @param $fieldJson
     *
     * @dataProvider providerTestExecuteWithCustomerAttribute
     */
    public function testExecuteWithCustomerAttribute(
        $isNewObject,
        $expects,
        $usedInForm,
        $fields,
        $fieldJson
    ) {
        /**
         * @var Observer $observerMock
         */
        $observerMock = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock = $this->getMockBuilder(Event::class)
            ->setMethods(['getAttribute'])
            ->disableOriginalConstructor()
            ->getMock();

        $observerMock->expects($this->once())->method('getEvent')->willReturn($eventMock);
        $methods = get_class_methods(CustomerAttribute::class);
        $methods[] = 'getPosition';

        $attributeMock = $this->getMockBuilder(CustomerAttribute::class)
            ->setMethods($methods)
            ->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->once())->method('getAttribute')->willReturn($attributeMock);
        $fieldPosition = [
            [
                'code' => 'test1',
                'colspan' => 6,
                'required' => true,
                'bottom' => true,
                'isNewRow' => true
            ],
            [
                'code' => 'test2',
                'colspan' => 6,
                'required' => true,
                'bottom' => true,
                'isNewRow' => false,
            ]
        ];

        $this->helperMock->expects($this->once())->method('getFieldPosition')->willReturn($fieldPosition);
        $attributeMock->expects($this->atLeastOnce())->method('getAttributeCode')->willReturn('test2');
        $attributeMock->expects($this->atLeastOnce())->method('getIsRequired')->willReturn(true);
        $attributeMock->expects($this->once())->method('isObjectNew')->willReturn($isNewObject);
        $attributeMock->expects($expects['OAField'])->method('getPosition')->willReturn(1);
        $attributeMock->expects($expects['useInForms'])->method('getUsedInForms')->willReturn($usedInForm);

        $this->helperMock->expects($this->once())
            ->method('jsonEncodeData')
            ->with($fields)
            ->willReturn($fieldJson);
        $this->helperMock->expects($this->once())
            ->method('saveOscConfig')
            ->with($fieldJson, ADDRESS::SORTED_FIELD_POSITION);

        $this->observer->execute($observerMock);
    }
}
