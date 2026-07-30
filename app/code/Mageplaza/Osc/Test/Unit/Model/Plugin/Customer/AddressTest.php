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

use Magento\Checkout\Model\Session;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Model\Address as CustomerAddress;
use Magento\Eav\Model\Config;
use Mageplaza\Osc\Model\Plugin\Customer\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class AddressTest extends TestCase
{
    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var Config|MockObject
     */
    private $configMock;

    /**
     * @var Address
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->addMethods(['getOscData'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new Address(
            $this->checkoutSessionMock,
            $this->configMock
        );
    }

    public function testAfterUpdateData()
    {
        /**
         * @var CustomerAddress $subject
         */
        $subject = $this->getMockBuilder(CustomerAddress::class)
            ->addMethods(['setShouldIgnoreValidation'])
            ->disableOriginalConstructor()->getMock();
        $subject->expects($this->once())->method('setShouldIgnoreValidation')->with(true);

        $this->plugin->afterUpdateData($subject, $subject);
    }

    /**
     * @return array
     */
    public static function providerTestBeforeUpdateData()
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
