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

namespace Mageplaza\Smtp\Test\Unit\Mail\Template;

use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilder as CoreTransportBuilder;
use Magento\Framework\Registry;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Mail\Template\TransportBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransportBuilder::class)]
class TransportBuilderTest extends TestCase
{
    private Registry&MockObject $registry;
    private Mail&MockObject $resourceMail;
    private SenderResolverInterface&MockObject $senderResolver;
    private CoreTransportBuilder&MockObject $subject;

    private TransportBuilder $plugin;

    protected function setUp(): void
    {
        $this->registry       = $this->createMock(Registry::class);
        $this->resourceMail   = $this->createMock(Mail::class);
        $this->senderResolver = $this->createMock(SenderResolverInterface::class);
        $this->subject        = $this->createMock(CoreTransportBuilder::class);

        $this->plugin = new TransportBuilder(
            $this->registry,
            $this->resourceMail,
            $this->senderResolver
        );
    }

    public function testBeforeSetTemplateOptionsRegistersStoreWhenStoreKeyPresent(): void
    {
        $this->registry->expects($this->once())
            ->method('unregister')->with('mp_smtp_store_id');
        $this->registry->expects($this->once())
            ->method('register')->with('mp_smtp_store_id', 3);

        $result = $this->plugin->beforeSetTemplateOptions($this->subject, ['store' => 3]);

        $this->assertSame([['store' => 3]], $result);
    }

    public function testBeforeSetTemplateOptionsSkipsRegisterWhenStoreKeyAbsent(): void
    {
        $this->registry->expects($this->once())->method('unregister')->with('mp_smtp_store_id');
        $this->registry->expects($this->never())->method('register');

        $result = $this->plugin->beforeSetTemplateOptions($this->subject, ['area' => 'frontend']);

        $this->assertSame([['area' => 'frontend']], $result);
    }

    public function testBeforeSetFromResolvesStringSenderThroughResolver(): void
    {
        $this->senderResolver->expects($this->once())
            ->method('resolve')
            ->with('general')
            ->willReturn(['email' => 'general@example.com', 'name' => 'General']);
        $this->resourceMail->expects($this->once())
            ->method('setFromByStore')
            ->with('general@example.com', 'General');

        $result = $this->plugin->beforeSetFrom($this->subject, 'general');

        $this->assertSame(['general'], $result);
    }

    public function testBeforeSetFromUsesArraySenderDirectlyWithoutResolver(): void
    {
        $from = ['email' => 'custom@example.com', 'name' => 'Custom'];

        $this->senderResolver->expects($this->never())->method('resolve');
        $this->resourceMail->expects($this->once())
            ->method('setFromByStore')
            ->with('custom@example.com', 'Custom');

        $result = $this->plugin->beforeSetFrom($this->subject, $from);

        $this->assertSame([$from], $result);
    }
}
