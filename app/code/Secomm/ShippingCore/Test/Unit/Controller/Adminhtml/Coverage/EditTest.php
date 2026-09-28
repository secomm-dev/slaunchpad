<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Controller\Adminhtml\Coverage;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetInterface;
use Secomm\ShippingCore\Controller\Adminhtml\Coverage\Edit;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;

/**
 * TASK-WY6WP5 — edit/create page resolution (directive §6/§7): create mode (no
 * target_code) renders the New Coverage page; a registered target resolves and is put in
 * the backend registry (buttons); the reserved METHOD type and unregistered codes are
 * refused with explicit messages.
 */
class EditTest extends TestCase
{
    private Http&MockObject $request;

    private ManagerInterface&MockObject $messages;

    private CarrierCoverageConfigAdapter&MockObject $configAdapter;

    private Registry $registry;

    private Page&MockObject $resultPage;

    private Edit $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->configAdapter = $this->createMock(CarrierCoverageConfigAdapter::class);
        $this->registry = new Registry();
        $this->resultPage = $this->createMock(Page::class);
        $pageConfig = $this->createMock(Config::class);
        $title = $this->createMock(\Magento\Framework\View\Page\Title::class);
        $pageConfig->method('getTitle')->willReturn($title);
        $this->resultPage->method('getConfig')->willReturn($pageConfig);
        $this->resultPage->method('setActiveMenu')->willReturnSelf();

        $resultPageFactory = $this->createMock(PageFactory::class);
        $resultPageFactory->method('create')->willReturn($this->resultPage);

        $redirect = $this->createMock(Redirect::class);
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($this->messages);

        $targetRegistry = new CoverageTargetRegistry([
            'secomm_ghn' => ['type' => 'CARRIER', 'code' => 'secomm_ghn', 'label' => 'GHN (Giao Hàng Nhanh)'],
        ]);
        $this->controller = new Edit($context, $resultPageFactory, $targetRegistry, $this->configAdapter, $this->registry);
    }

    private function withParams(array $params): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, $default = null) => $params[$key] ?? $default
        );
    }

    /** Create mode — no target_code renders the New Coverage form. */
    public function testNoTargetCodeRendersCreatePage(): void
    {
        $this->withParams([]);
        $this->configAdapter->expects($this->never())->method('hasExplicitConfig');

        $result = $this->controller->execute();

        $this->assertInstanceOf(Page::class, $result);
        $this->assertNull($this->registry->registry(Edit::REGISTRY_KEY));
    }

    /** A registered target resolves and is registered for the form buttons. */
    public function testRegisteredTargetIsPlacedInRegistry(): void
    {
        $this->withParams(['target_type' => 'CARRIER', 'target_code' => 'secomm_ghn']);
        $this->configAdapter->method('hasExplicitConfig')->willReturn(true);

        $result = $this->controller->execute();

        $this->assertInstanceOf(Page::class, $result);
        $target = $this->registry->registry(Edit::REGISTRY_KEY);
        $this->assertInstanceOf(CoverageTargetInterface::class, $target);
        $this->assertSame('secomm_ghn', $target->getIdentity()->code());
    }

    /** The reserved METHOD type is refused with its own explicit message. */
    public function testMethodTypeIsRefused(): void
    {
        $this->withParams(['target_type' => 'METHOD', 'target_code' => 'flatrate']);
        $this->messages->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(static fn ($message): bool => str_contains((string) $message, 'reserved')));

        $result = $this->controller->execute();

        $this->assertInstanceOf(Redirect::class, $result);
        $this->assertNull($this->registry->registry(Edit::REGISTRY_KEY));
    }

    public function testUnregisteredTargetIsRefused(): void
    {
        $this->withParams(['target_type' => 'CARRIER', 'target_code' => 'ghost']);
        $this->messages->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(static fn ($message): bool => str_contains((string) $message, 'not registered')));

        $result = $this->controller->execute();

        $this->assertInstanceOf(Redirect::class, $result);
    }
}
