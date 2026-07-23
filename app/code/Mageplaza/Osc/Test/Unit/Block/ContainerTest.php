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

namespace Mageplaza\Osc\Test\Unit\Block;

use Magento\Framework\View\Element\Template\Context;
use Mageplaza\Osc\Block\Container;
use Mageplaza\Osc\Helper\Data as OscHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    /**
     * @var OscHelper|MockObject
     */
    private $oscHelperMock;

    /**
     * @var Container|MockObject
     */
    private $containerBock;

    protected function setUp(): void
    {
        /**
         * @var Context $contextMock
         */
        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->oscHelperMock = $this->getMockBuilder(OscHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->containerBock = new Container(
            $contextMock,
            $this->oscHelperMock
        );
    }

    public function testGetCheckoutDescription()
    {
        $this->oscHelperMock->expects($this->once())
            ->method('getConfigGeneral')
            ->with('description');
        $this->containerBock->getCheckoutDescription();
    }
}
