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

namespace Mageplaza\Smtp\Test\Unit\Plugin;

use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilderByStore;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Plugin\Message;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Message::class)]
class MessageTest extends TestCase
{
    private Mail&MockObject $resourceMail;
    private SenderResolverInterface&MockObject $senderResolver;
    private Message $plugin;

    protected function setUp(): void
    {
        $this->resourceMail   = $this->createMock(Mail::class);
        $this->senderResolver = $this->createMock(SenderResolverInterface::class);
        $this->plugin         = new Message($this->resourceMail, $this->senderResolver);
    }

    public function testBeforeSetFromByStoreResolvesAndForwardsArguments(): void
    {
        $subject = $this->createMock(TransportBuilderByStore::class);
        $from    = 'general';
        $store   = 1;

        $this->senderResolver->expects($this->once())
            ->method('resolve')
            ->with($from, $store)
            ->willReturn(['email' => 'store@example.com', 'name' => 'Store Owner']);

        $this->resourceMail->expects($this->once())
            ->method('setFromByStore')
            ->with('store@example.com', 'Store Owner');

        $result = $this->plugin->beforeSetFromByStore($subject, $from, $store);

        $this->assertSame([$from, $store], $result);
    }

    public function testBeforeSetFromByStorePropagatesResolverException(): void
    {
        $subject = $this->createMock(TransportBuilderByStore::class);

        $this->senderResolver->method('resolve')
            ->willThrowException(new MailException(__('resolve failed')));
        $this->resourceMail->expects($this->never())->method('setFromByStore');

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('resolve failed');

        $this->plugin->beforeSetFromByStore($subject, 'general', 1);
    }
}
