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

namespace Mageplaza\Osc\Test\Unit\Block\Checkout;

use Magento\Framework\View\Element\Template\Context;
use Mageplaza\Osc\Block\Checkout\CompatibleConfig;
use Mageplaza\Osc\Helper\Data as OscHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CompatibleConfigTest extends TestCase
{
    /**
     * @var OscHelper|MockObject
     */
    private $oscHelperMock;

    /**
     * @var CompatibleConfig
     */
    private $compatibleConfigBlock;

    protected function setup(): void
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

        $this->compatibleConfigBlock = new CompatibleConfig($contextMock, $this->oscHelperMock);
    }

    public function testIsEnableModulePostNL()
    {
        $this->oscHelperMock->expects($this->once())->method('isEnableModulePostNL');

        $this->compatibleConfigBlock->isEnableModulePostNL();
    }
}
