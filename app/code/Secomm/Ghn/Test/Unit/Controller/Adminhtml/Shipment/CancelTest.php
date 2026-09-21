<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\AuthorizationInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Controller\Adminhtml\Shipment\Cancel;
use Secomm\Ghn\Model\Admin\GhnActionOutcomeNotifier;
use Secomm\Ghn\Model\Shipment\GhnActionOutcome;
use Secomm\Ghn\Model\Shipment\GhnCancelService;
use Secomm\Ghn\Model\Tracking\ShipmentReconciler;

/**
 * TASK-PWHG0V (GHN-E3-B) — the Admin Cancel controller contract: POST-only entry, server-side
 * reason length cap (rejected, never truncated), service delegation, outcome notification via
 * the shared notifier, reconcile-after-success (non-fatal), and the ACL resource identity.
 * Message wording itself is locked in GhnActionOutcomeNotifierTest.
 */
class CancelTest extends TestCase
{
    private Http&MockObject $request;

    private ManagerInterface&MockObject $messages;

    private AuthorizationInterface&MockObject $authorization;

    private GhnCancelService&MockObject $cancelService;

    private ShipmentReconciler&MockObject $reconciler;

    private GhnActionOutcomeNotifier&MockObject $notifier;

    private ShipmentRepositoryInterface&MockObject $shipmentRepository;

    private Redirect&MockObject $redirect;

    /** @var array<string, string> captured redirect path */
    private array $redirectPath = [];

    private string $reason = '';

    private bool $isPost = true;

    private Cancel $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->authorization = $this->createMock(AuthorizationInterface::class);
        $this->cancelService = $this->createMock(GhnCancelService::class);
        $this->reconciler = $this->createMock(ShipmentReconciler::class);
        $this->notifier = $this->createMock(GhnActionOutcomeNotifier::class);
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);

        $this->redirect = $this->createMock(Redirect::class);
        $that = $this;
        $this->redirect->method('setPath')->willReturnCallback(
            function (string $path) use ($that) {
                $that->redirectPath[] = $path;

                return $this->redirect;
            }
        );
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getMessageManager')->willReturn($this->messages);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getAuthorization')->willReturn($this->authorization);

        $this->request->method('getParam')->willReturnCallback(
            function (string $key) {
                return match ($key) {
                    'shipment_id' => 16,
                    'reason_code' => 'GHN-CO003',
                    'reason' => $this->reason,
                    default => null,
                };
            }
        );
        $this->request->method('isPost')->willReturnCallback(fn (): bool => $this->isPost);

        $shipment = $this->createMock(\Magento\Sales\Model\Order\Shipment::class);
        $shipment->method('getEntityId')->willReturn(16);
        $this->shipmentRepository->method('get')->willReturn($shipment);

        $this->controller = new Cancel(
            $context,
            $this->shipmentRepository,
            $this->cancelService,
            $this->reconciler,
            $this->notifier
        );
    }

    public function testAclResourceIsDedicated(): void
    {
        $this->authorization->expects($this->once())->method('isAllowed')
            ->with('Secomm_Ghn::cancel_shipment')->willReturn(true);

        $ref = new \ReflectionClass(Cancel::class);
        $this->assertSame('Secomm_Ghn::cancel_shipment', $ref->getConstant('ADMIN_RESOURCE'));
        $this->assertTrue($this->invokeIsAllowed());
    }

    public function testSuccessDelegatesNotifiesAndReconciles(): void
    {
        $this->reason = 'QC probe';
        $outcome = GhnActionOutcome::success(GhnActionOutcome::ACTION_CANCEL, 'L8NEW');
        $this->cancelService->expects($this->once())->method('cancel')
            ->with($this->anything(), 'GHN-CO003', 'QC probe')->willReturn($outcome);
        $this->notifier->expects($this->once())->method('notify')->with($outcome);
        $this->reconciler->expects($this->once())->method('reconcileByOrderCode')->with('L8NEW');

        $this->controller->execute();
        $this->assertContains('sales_shipment/view', $this->redirectPath);
    }

    public function testFailureOutcomeNotifiesWithoutReconcile(): void
    {
        $outcome = GhnActionOutcome::businessRejected(
            GhnActionOutcome::ACTION_CANCEL,
            'L8NEW',
            'PROVIDER_REJECTED',
            'Đơn đã giao'
        );
        $this->cancelService->method('cancel')->willReturn($outcome);
        $this->notifier->expects($this->once())->method('notify')->with($outcome);
        $this->reconciler->expects($this->never())->method('reconcileByOrderCode');

        $this->controller->execute();
    }

    public function testReasonOf255MultibyteCharactersIsAccepted(): void
    {
        // Multibyte Vietnamese — proves character-count (mb_strlen), not raw bytes (255 × 2 bytes).
        $this->reason = str_repeat('Đơn', 85); // 255 chars / 510 bytes
        $this->cancelService->expects($this->once())->method('cancel')
            ->with($this->anything(), 'GHN-CO003', $this->reason)
            ->willReturn(GhnActionOutcome::success(GhnActionOutcome::ACTION_CANCEL, 'L8NEW'));

        $this->controller->execute();
    }

    public function testReasonOf256CharactersIsRejectedAndServiceNeverCalled(): void
    {
        $this->reason = str_repeat('x', 256);
        $this->cancelService->expects($this->never())->method('cancel');
        $this->notifier->expects($this->never())->method('notify');
        $this->messages->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(fn (\Magento\Framework\Phrase $p) => str_contains((string) $p, '255')));

        $this->controller->execute();
        $this->assertContains('sales_shipment/view', $this->redirectPath);
    }

    public function testNonPostRequestIsRejected(): void
    {
        $this->isPost = false;
        $this->cancelService->expects($this->never())->method('cancel');
        $this->messages->expects($this->once())->method('addErrorMessage')->with($this->callback(
            fn (\Magento\Framework\Phrase $p) => str_contains((string) $p, 'Invalid request method')
        ));

        $this->controller->execute();
        $this->assertContains('dashboard', $this->redirectPath);
    }

    public function testUnknownShipmentRedirectsToGrid(): void
    {
        $this->shipmentRepository->method('get')->willThrowException(
            new \Magento\Framework\Exception\NoSuchEntityException(new \Magento\Framework\Phrase('nope'))
        );
        $this->cancelService->expects($this->never())->method('cancel');

        $this->controller->execute();
        $this->assertContains('sales_shipment/index', $this->redirectPath);
    }

    private function invokeIsAllowed(): bool
    {
        $method = new \ReflectionMethod(Cancel::class, '_isAllowed');
        $method->setAccessible(true);

        return (bool) $method->invoke($this->controller);
    }
}
