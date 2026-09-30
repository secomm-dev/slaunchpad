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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Controller\Adminhtml\Coverage\Save;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;

/**
 * TASK-WY6WP5 — coverage save: POST-only + registry guard; the CREATE flow
 * (`is_create=1`) is REJECTED without writing when an explicit config already exists for
 * the same type+code (directive §6F — one explicit config per target); edit submissions
 * and clean creates persist through the adapter; validation failures surface the
 * validator message.
 */
class SaveTest extends TestCase
{
    private Http&MockObject $request;

    private Redirect&MockObject $redirect;

    private ManagerInterface&MockObject $messages;

    private CarrierCoverageConfigAdapter&MockObject $configAdapter;

    private Save $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->redirect = $this->createMock(Redirect::class);
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->configAdapter = $this->createMock(CarrierCoverageConfigAdapter::class);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($this->messages);

        // Every execution path ends with a redirect back to the grid.
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index');

        $registry = new CoverageTargetRegistry([
            'secomm_ghn' => ['type' => 'CARRIER', 'code' => 'secomm_ghn', 'label' => 'GHN (Giao Hàng Nhanh)'],
        ]);
        $this->controller = new Save($context, $this->configAdapter, $registry);
    }

    private function postParams(array $overrides = []): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static function (string $key, $default = null) use ($overrides) {
                if (array_key_exists($key, $overrides)) {
                    return $overrides[$key];
                }

                return match ($key) {
                    'target_type' => 'CARRIER',
                    'target_code' => 'secomm_ghn',
                    'is_create' => '1',
                    'destination_scope' => 'SELECTED_ZONES',
                    'allowed_zone_codes' => ['HCM_INNER'],
                    'rate_source_mode' => 'CARRIER_WITH_FALLBACK',
                    'address_resolution_policy' => 'FALLBACK',
                    default => $default,
                };
            }
        );
    }

    /** F — a create submission for an already-configured target is rejected, no write. */
    public function testDuplicateCreateIsRejectedWithoutWrite(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->postParams();
        $this->configAdapter->method('hasExplicitConfig')->willReturn(true);
        $this->configAdapter->expects($this->never())->method('save');
        $this->messages->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(static fn ($message): bool => str_contains((string) $message, 'already exists')));

        $this->controller->execute();
    }

    /** G — a clean create persists through the adapter with the form values. */
    public function testCleanCreateSavesThroughAdapter(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->postParams();
        $this->configAdapter->method('hasExplicitConfig')->willReturn(false);
        $this->configAdapter->expects($this->once())->method('save')->with(
            $this->callback(
                static fn ($identity) => $identity instanceof \Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity
                    && $identity->key() === 'CARRIER:secomm_ghn'
            ),
            'SELECTED_ZONES',
            ['HCM_INNER'],
            'CARRIER_WITH_FALLBACK',
            'FALLBACK'
        )->willReturn(['HCM_INNER']);
        $this->messages->expects($this->once())->method('addSuccessMessage')
            ->with($this->callback(static fn ($message): bool => str_contains((string) $message, 'has been saved')));
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index');

        $this->controller->execute();
    }

    /** An edit submission (is_create=0) always targets the one existing config. */
    public function testEditSubmissionBypassesDuplicateGuard(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->postParams(['is_create' => '0']);
        $this->configAdapter->method('hasExplicitConfig')->willReturn(true);
        $this->configAdapter->expects($this->once())->method('save')->willReturn([]);
        $this->messages->expects($this->once())->method('addSuccessMessage');

        $this->controller->execute();
    }

    public function testNonPostIsRejectedWithoutSave(): void
    {
        $this->request->method('isPost')->willReturn(false);
        $this->configAdapter->expects($this->never())->method('save');
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index');

        $this->controller->execute();
    }

    public function testUnregisteredTargetIsRejectedWithoutSave(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->postParams(['target_code' => 'ghost']);
        $this->configAdapter->expects($this->never())->method('save');
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index');

        $this->controller->execute();
    }

    public function testMethodTargetIsRefusedWithoutSave(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->postParams(['target_type' => 'METHOD']);
        $this->configAdapter->expects($this->never())->method('save');
        $this->messages->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(static fn ($message): bool => str_contains((string) $message, 'reserved')));
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index');

        $this->controller->execute();
    }

    public function testValidationFailureSurfacesMessage(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->postParams();
        $this->configAdapter->method('hasExplicitConfig')->willReturn(false);
        $this->configAdapter->method('save')->willThrowException(
            new LocalizedException(new \Magento\Framework\Phrase('Zone "GHOST" does not exist'))
        );
        $this->messages->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(static fn ($message): bool => str_contains((string) $message, 'GHOST')));

        $this->controller->execute();
    }
}
