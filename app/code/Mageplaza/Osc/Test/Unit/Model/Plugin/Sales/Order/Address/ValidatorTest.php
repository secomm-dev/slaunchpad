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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Sales\Order\Address;

use Magento\Sales\Model\Order\Address;
use Mageplaza\Osc\Helper\Data;
use Mageplaza\Osc\Model\Plugin\Sales\Order\Address\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Class Validator
 *
 */
class ValidatorTest extends TestCase
{
    /**
     * @var Data
     */
    private $helperMock;

    /**
     * @var Validator
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->helperMock = $this->getMockBuilder(Data::class)->disableOriginalConstructor()->getMock();

        $this->plugin = new Validator($this->helperMock);
    }

    public function testMethod()
    {
        $methods = get_class_methods(Address\Validator::class);

        $this->assertTrue(in_array('validateForCustomer', $methods));
    }

    public function testBeforeValidateForCustomer()
    {
        /**
         * @var Address\Validator $subject
         */
        $subject = $this->getMockBuilder(Address\Validator::class)->disableOriginalConstructor()->getMock();

        /**
         * @var Address $addressMock
         */
        $addressMock = $this->getMockBuilder(Address::class)
            ->setMethods(['setShouldIgnoreValidation'])
            ->disableOriginalConstructor()->getMock();
        $this->helperMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $addressMock->expects($this->once())->method('setShouldIgnoreValidation')->with(true);

        $this->plugin->beforeValidateForCustomer($subject, $addressMock);
    }
}
