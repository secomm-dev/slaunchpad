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

namespace Mageplaza\Smtp\Test\Unit\Block\Adminhtml\System\Config;

use Magento\Framework\App\RequestInterface;
use Mageplaza\Smtp\Block\Adminhtml\System\Config\Sync;
use Mageplaza\Smtp\Model\Config\Source\SyncType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sync::class)]
class SyncTest extends TestCase
{
    // getUrl()/getRequest() are declared on AbstractBlock (not magic) —
    // vendor/magento/framework/View/Element/AbstractBlock.php:778 / :241.
    private function createSut(): Sync&MockObject
    {
        return $this->getMockBuilder(Sync::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUrl', 'getRequest'])
            ->getMock();
    }


    public function testGetEstimateUrlDelegatesToGetUrl(): void
    {
        $sut = $this->createSut();
        $sut->expects($this->once())
            ->method('getUrl')
            ->with('adminhtml/smtp_sync/estimate', ['_current' => true])
            ->willReturn('https://example.com/admin/smtp_sync/estimate');

        $this->assertSame('https://example.com/admin/smtp_sync/estimate', $sut->getEstimateUrl());
    }


    public function testGetWebsiteIdReturnsRequestParam(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('website')->willReturn('2');

        $sut = $this->createSut();
        $sut->method('getRequest')->willReturn($request);

        $this->assertSame('2', $sut->getWebsiteId());
    }

    public function testGetWebsiteIdReturnsNullWhenParamAbsent(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('website')->willReturn(null);

        $sut = $this->createSut();
        $sut->method('getRequest')->willReturn($request);

        $this->assertNull($sut->getWebsiteId());
    }


    public function testGetStoreIdReturnsRequestParam(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('store')->willReturn('2');

        $sut = $this->createSut();
        $sut->method('getRequest')->willReturn($request);

        $this->assertSame('2', $sut->getStoreId());
    }

    public function testGetStoreIdReturnsNullWhenParamAbsent(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('store')->willReturn(null);

        $sut = $this->createSut();
        $sut->method('getRequest')->willReturn($request);

        $this->assertNull($sut->getStoreId());
    }


    public function testGetSyncSuccessMessageReturnsMessagesKeyedBySyncType(): void
    {
        $result = $this->createSut()->getSyncSuccessMessage();

        $this->assertArrayHasKey(SyncType::CUSTOMERS, $result);
        $this->assertArrayHasKey(SyncType::ORDERS, $result);
        $this->assertArrayHasKey(SyncType::SUBSCRIBERS, $result);
        $this->assertSame(
            'Customer synchronization has been completed.',
            (string) $result[SyncType::CUSTOMERS]
        );
        $this->assertSame(
            'Order synchronization has been completed.',
            (string) $result[SyncType::ORDERS]
        );
        $this->assertSame(
            'Subscriber synchronization has been completed.',
            (string) $result[SyncType::SUBSCRIBERS]
        );
    }


    public function testGetElementIdReturnsMpSynchronize(): void
    {
        $this->assertSame('mp-synchronize', $this->createSut()->getElementId());
    }


    public function testGetComponentReturnsJsSyncPath(): void
    {
        $this->assertSame('Mageplaza_Smtp/js/sync/sync', $this->createSut()->getComponent());
    }
}
