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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;
use Secomm\ShippingCore\Controller\Adminhtml\Coverage\Reset;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;

/**
 * TASK-WY6WP5 — Reset to Defaults (directive §8): POST-only; a not-configured target is
 * a no-op notice; a configured target has its DEFAULT-scope values removed (count
 * reported, target stays registered) and any surviving WEBSITE/STORE overrides surface
 * an explicit warning. GET is refused.
 */
class ResetTest extends TestCase
{
    private Http&MockObject $request;

    private Redirect&MockObject $redirect;

    private ManagerInterface&MockObject $messages;

    private CarrierCoverageConfigAdapter&MockObject $configAdapter;

    private Reset $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->redirect = $this->createMock(Redirect::class);
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->configAdapter = $this->createMock(CarrierCoverageConfigAdapter::class);

        // Every execution path ends with a redirect back to the grid.
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index');

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($this->messages);

        $registry = new CoverageTargetRegistry([
            'secomm_ghn' => ['type' => 'CARRIER', 'code' => 'secomm_ghn', 'label' => 'GHN (Giao Hàng Nhanh)'],
        ]);
        $this->controller = new Reset($context, $this->configAdapter, $registry);
    }

    private function postParams(string $code = 'secomm_ghn', bool $isPost = true): void
    {
        $this->request->method('isPost')->willReturn($isPost);
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, ?string $default = null) => match ($key) {
                'target_type' => 'CARRIER',
                'target_code' => $code,
                default => $default,
            }
        );
    }

    public function testGetRequestIsRefused(): void
    {
        $this->postParams(isPost: false);
        $this->configAdapter->expects($this->never())->method('reset');

        $this->controller->execute();
    }

    public function testUnregisteredTargetIsRefused(): void
    {
        $this->postParams('ghost');
        $this->configAdapter->expects($this->never())->method('reset');

        $this->controller->execute();
    }

    /** Not Configured → informational no-op; nothing is deleted. */
    public function testNotConfiguredTargetIsAnInformedNoOp(): void
    {
        $this->postParams();
        $this->configAdapter->method('hasExplicitConfig')->willReturn(false);
        $this->configAdapter->expects($this->never())->method('reset');
        $this->messages->expects($this->once())->method('addNoticeMessage')
            ->with($this->callback(static fn ($message): bool => str_contains((string) $message, 'not explicitly configured')));

        $this->controller->execute();
    }

    /** Configured → DEFAULT rows removed, target stays registered, count reported. */
    public function testConfiguredTargetResetsAndReportsCount(): void
    {
        $this->postParams();
        $this->configAdapter->method('hasExplicitConfig')->willReturn(true);
        $this->configAdapter->method('nonDefaultScopeRows')->willReturn([]);
        $this->configAdapter->expects($this->once())->method('reset')->with(
            $this->callback(
                static fn ($identity) => $identity instanceof CoverageTargetIdentity
                    && $identity->key() === 'CARRIER:secomm_ghn'
            )
        )->willReturn(4);
        $this->messages->expects($this->once())->method('addSuccessMessage')
            ->with($this->callback(static fn ($message): bool => str_contains((string) $message, 'remains registered')));

        $this->controller->execute();
    }

    /** Surviving scoped overrides must be named in a warning (they stay live). */
    public function testScopedOverridesWarnAfterReset(): void
    {
        $this->postParams();
        $this->configAdapter->method('hasExplicitConfig')->willReturn(true);
        $this->configAdapter->method('nonDefaultScopeRows')->willReturn(['Website: Vietnam Store']);
        $this->configAdapter->method('reset')->willReturn(4);
        $this->messages->expects($this->once())->method('addSuccessMessage');
        $this->messages->expects($this->once())->method('addWarningMessage')
            ->with($this->callback(static fn ($message): bool => str_contains((string) $message, 'Website: Vietnam Store')));

        $this->controller->execute();
    }
}
