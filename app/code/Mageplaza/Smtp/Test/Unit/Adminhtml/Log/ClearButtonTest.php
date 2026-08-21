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
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
declare(strict_types=1);

namespace Mageplaza\Smtp\Test\Unit\Adminhtml\Log;

use Magento\Framework\UrlInterface;
use Mageplaza\Smtp\Block\Adminhtml\Log\ClearButton;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClearButton::class)]
class ClearButtonTest extends TestCase
{
    private UrlInterface&MockObject $urlBuilder;

    private ClearButton $subject;

    protected function setUp(): void
    {
        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $this->subject = new ClearButton($this->urlBuilder);
    }


    public function testGetButtonDataReturnsExpectedButtonConfig(): void
    {
        $this->urlBuilder->method('getUrl')
            ->with('*/*/clear')
            ->willReturn('https://x/clear');

        $result = $this->subject->getButtonData();

        $this->assertSame('Clear All Logs', (string) $result['label']);
        $this->assertSame('primary', $result['class']);
        $this->assertSame(10, $result['sort_order']);
        $this->assertStringContainsString('https://x/clear', $result['on_click']);
        $this->assertStringContainsString(
            'Are you sure you want to clear all email logs?',
            $result['on_click']
        );
        $this->assertStringStartsWith('deleteConfirm(', $result['on_click']);
    }

    public function testGetButtonDataCallsUrlBuilderWithClearRoute(): void
    {
        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('*/*/clear')
            ->willReturn('https://x/clear');

        $this->subject->getButtonData();
    }
}
