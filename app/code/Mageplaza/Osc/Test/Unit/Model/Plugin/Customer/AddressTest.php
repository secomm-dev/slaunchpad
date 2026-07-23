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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Customer;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Model\Address as CustomerAddress;
use Mageplaza\Osc\Model\Plugin\Customer\Address;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class AddressTest extends TestCase
{
    /**
     * @var Address
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->plugin = new Address();
    }

    public function testAfterUpdateData()
    {
        /**
         * @var CustomerAddress $subject
         */
        $subject = $this->getMockBuilder(CustomerAddress::class)
            ->setMethods(['setShouldIgnoreValidation'])
            ->disableOriginalConstructor()->getMock();
        $subject->expects($this->once())->method('setShouldIgnoreValidation')->with(true);

        $this->plugin->afterUpdateData($subject, $subject);
    }

    /**
     * @return array
     */
    public function providerTestBeforeUpdateData()
    {
        return [
            [
                [
                    'mposc_field_1' => ''
                ],
                'mposc_field_1'
            ],
            [
                [
                    'mposc_field_2' => ''
                ],
                'mposc_field_2'
            ],
            [
                [
                    'mposc_field_3' => ''
                ],
                'mposc_field_3'
            ]
        ];
    }

    /**
     * @param array  $customAttribute
     * @param string $key
     *
     * @dataProvider providerTestBeforeUpdateData
     * @throws       ReflectionException
     */
    public function testBeforeUpdateData($customAttribute, $key)
    {
        /**
         * @var CustomerAddress $subject
         */
        $subject = $this->getMockBuilder(CustomerAddress::class)->disableOriginalConstructor()->getMock();

        /**
         * @var AddressInterface $addressMock
         */
        $addressMock = $this->getMockForAbstractClass(AddressInterface::class);
        $addressMock->expects($this->once())->method('getCustomAttributes')->willReturn($customAttribute);
        $addressMock->expects($this->once())
            ->method('setCustomAttribute')
            ->with($key, '');

        $this->plugin->beforeUpdateData($subject, $addressMock);
    }
}
