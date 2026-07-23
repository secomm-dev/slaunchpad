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

namespace Mageplaza\Osc\Test\Unit\Block\Adminhtml\Field;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Phrase;
use Mageplaza\Osc\Block\Adminhtml\Field\Address;
use Mageplaza\Osc\Helper\Address as HelperAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class Address
 *
 */
class AddressTest extends TestCase
{
    /**
     * @var Address
     */
    private $addressBlock;

    protected function setUp(): void
    {
        /**
         * @var Context|MockObject $contextMock
         */
        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        /**
         * @var HelperAddress|MockObject $helperAddressMock
         */
        $helperAddressMock = $this->getMockBuilder(HelperAddress::class)
            ->disableOriginalConstructor()
            ->getMock();
        $helperAddressMock->expects($this->once())
            ->method('getSortedField')
            ->with(false);

        $this->addressBlock = new Address(
            $contextMock,
            $helperAddressMock
        );
    }

    public function testGetBlockTitle()
    {
        $result = (string)new Phrase('Address Information');

        $this->assertEquals($result, $this->addressBlock->getBlockTitle());
    }

    public function testGetBlockId()
    {
        $this->assertEquals('mposc-address-information', $this->addressBlock->getBlockId());
    }
}
