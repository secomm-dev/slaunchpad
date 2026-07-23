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

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\Quote;
use Mageplaza\Osc\Block\Design;
use Mageplaza\Osc\Helper\Data as OscHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DesignTest extends TestCase
{
    /**
     * @var ThemeProviderInterface|MockObject
     */
    protected $themeProviderInterfaceMock;

    /**
     * @var CheckoutSession|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var OscHelper|MockObject
     */
    private $oscHelperMock;

    /**
     * @var Design|MockObject
     */
    private $designBock;

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
        $this->themeProviderInterfaceMock = $this->getMockForAbstractClass(ThemeProviderInterface::class);
        $this->checkoutSessionMock = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->designBock = new Design(
            $contextMock,
            $this->oscHelperMock,
            $this->themeProviderInterfaceMock,
            $this->checkoutSessionMock
        );
    }

    public function testEnableGoogleApi()
    {
        $this->oscHelperMock->expects($this->once())->method('getAutoDetectedAddress')->willReturn('google');
        $this->assertTrue($this->designBock->isEnableGoogleApi());
    }

    public function testDisableGoogleApi()
    {
        $this->oscHelperMock->expects($this->once())->method('getAutoDetectedAddress')->willReturn('test');
        $this->assertFalse($this->designBock->isEnableGoogleApi());
    }

    public function testGetGoogleApiKey()
    {
        $this->oscHelperMock->expects($this->once())->method('getGoogleApiKey');

        $this->designBock->getGoogleApiKey();
    }

    public function testDesignConfiguration()
    {
        $this->oscHelperMock->expects($this->once())->method('getDesignConfig');

        $this->designBock->getDesignConfiguration();
    }

    public function testGetCurrentTheme()
    {
        $themeId = 1;
        $this->oscHelperMock->expects($this->once())->method('getCurrentThemeId')->willReturn($themeId);
        $themeMock = $this->getMockForAbstractClass(ThemeInterface::class);

        $this->themeProviderInterfaceMock->expects($this->once())
            ->method('getThemeById')
            ->with($themeId)
            ->willReturn($themeMock);
        $themeMock->expects($this->once())->method('getCode');

        $this->designBock->getCurrentTheme();
    }

    public function testIsVirtual()
    {
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutSessionMock->expects($this->once())
            ->method('getQuote')
            ->willReturn($quoteMock);
        $quoteMock->expects($this->once())->method('isVirtual')->willReturn(true);

        $this->assertTrue($this->designBock->isVirtual());
    }
}
