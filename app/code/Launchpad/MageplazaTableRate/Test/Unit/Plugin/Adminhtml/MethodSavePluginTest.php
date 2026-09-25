<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Plugin\Adminhtml;

use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Launchpad\MageplazaTableRate\Model\Adminhtml\SettingsCapture;
use Launchpad\MageplazaTableRate\Plugin\Adminhtml\MethodSavePlugin;

/**
 * TASK-SEC-C1 — the controller seam only CAPTURES the posted payload; persistence happens
 * on the resource seam where the persisted id exists. CREATE payloads (no method_id) are
 * captured exactly like updates; no payload → nothing captured (native behavior intact).
 */
class MethodSavePluginTest extends TestCase
{
    private RequestInterface&MockObject $request;

    private SettingsCapture $capture;

    private MethodSavePlugin $plugin;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->capture = new SettingsCapture();
        $this->plugin = new MethodSavePlugin($this->capture, $this->request);
    }

    public function testCreatePayloadWithoutMethodIdIsCaptured(): void
    {
        $payload = ['show_to_customer' => '1', 'use_as_fallback' => '1', 'members' => ['ghn|STANDARD']];
        $this->request->method('getParam')->willReturnCallback(
            fn (string $key): mixed => $key === 'launchpad' ? $payload : null
        );

        $this->plugin->beforeExecute($this->createMock(\Mageplaza\TableRateShipping\Controller\Adminhtml\Method\Save::class));

        // The capture — not the request — now carries the payload: CREATE (no method_id) works.
        $this->assertSame($payload, $this->capture->consume());
    }

    public function testNoPayloadCapturesNothing(): void
    {
        $this->request->method('getParam')->willReturn(null);

        $this->plugin->beforeExecute($this->createMock(\Mageplaza\TableRateShipping\Controller\Adminhtml\Method\Save::class));

        $this->assertNull($this->capture->consume());
    }

    public function testConsumeIsExactlyOnce(): void
    {
        $this->request->method('getParam')->willReturn(['use_as_fallback' => '1']);
        $this->plugin->beforeExecute($this->createMock(\Mageplaza\TableRateShipping\Controller\Adminhtml\Method\Save::class));

        $this->assertNotNull($this->capture->consume());
        $this->assertNull($this->capture->consume(), 'A later unrelated save must not inherit the payload');
    }
}
