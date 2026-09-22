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
use Secomm\Ghn\Controller\Adminhtml\Shipment\ReturnShipment;
use Secomm\Ghn\Model\Admin\GhnActionOutcomeNotifier;
use Secomm\Ghn\Model\Shipment\GhnActionOutcome;
use Secomm\Ghn\Model\Shipment\GhnReturnService;
use Secomm\Ghn\Model\Tracking\ShipmentReconciler;

/**
 * TASK-PWHG0V (GHN-E3-B) — the Admin "Request GHN Return" controller contract: delegates to
 * {@see GhnReturnService} (carrier R2S request only — no RMA/refund), dedicated ACL, POST-only,
 * shared outcome notifier, reconcile-after-success (non-fatal). Message wording is locked in
 * GhnActionOutcomeNotifierTest.
 */
class ReturnShipmentTest extends TestCase
{
    private Http&MockObject $request;

    private ManagerInterface&MockObject $messages;

    private AuthorizationInterface&MockObject $authorization;

    private GhnReturnService&MockObject $returnService;

    private ShipmentReconciler&MockObject $reconciler;

    private GhnActionOutcomeNotifier&MockObject $notifier;

    private ShipmentRepositoryInterface&MockObject $shipmentRepository;

    private ReturnShipment $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->authorization = $this->createMock(AuthorizationInterface::class);
        $this->returnService = $this->createMock(GhnReturnService::class);
        $this->reconciler = $this->createMock(ShipmentReconciler::class);
        $this->notifier = $this->createMock(GhnActionOutcomeNotifier::class);
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);

        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getMessageManager')->willReturn($this->messages);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getAuthorization')->willReturn($this->authorization);

        $this->request->method('getParam')->willReturnCallback(
            fn (string $key) => $key === 'shipment_id' ? 16 : null
        );
        $this->request->method('isPost')->willReturn(true);

        $shipment = $this->createMock(\Magento\Sales\Model\Order\Shipment::class);
        $shipment->method('getEntityId')->willReturn(16);
        $this->shipmentRepository->method('get')->willReturn($shipment);

        $this->controller = new ReturnShipment(
            $context,
            $this->shipmentRepository,
            $this->returnService,
            $this->reconciler,
            $this->notifier
        );
    }

    public function testAclResourceIsDedicated(): void
    {
        $ref = new \ReflectionClass(ReturnShipment::class);
        $this->assertSame('Secomm_Ghn::return_shipment', $ref->getConstant('ADMIN_RESOURCE'));

        $this->authorization->expects($this->once())->method('isAllowed')
            ->with('Secomm_Ghn::return_shipment')->willReturn(true);
        $method = new \ReflectionMethod(ReturnShipment::class, '_isAllowed');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($this->controller));
    }

    public function testSuccessDelegatesNotifiesAndReconciles(): void
    {
        $outcome = GhnActionOutcome::success(GhnActionOutcome::ACTION_RETURN, 'L8NEW');
        $this->returnService->expects($this->once())->method('requestReturn')->willReturn($outcome);
        $this->notifier->expects($this->once())->method('notify')->with($outcome);
        $this->reconciler->expects($this->once())->method('reconcileByOrderCode')->with('L8NEW');

        $this->controller->execute();
    }

    public function testFailureOutcomeNotifiesWithoutReconcile(): void
    {
        $outcome = GhnActionOutcome::businessRejected(
            GhnActionOutcome::ACTION_RETURN,
            'L8NEW',
            'PROVIDER_REJECTED',
            'Trạng thái không hợp lệ'
        );
        $this->returnService->method('requestReturn')->willReturn($outcome);
        $this->notifier->expects($this->once())->method('notify')->with($outcome);
        $this->reconciler->expects($this->never())->method('reconcileByOrderCode');

        $this->controller->execute();
    }

    public function testUnknownOutcomeStillNotified(): void
    {
        $outcome = GhnActionOutcome::unknownResult(GhnActionOutcome::ACTION_RETURN, 'L8NEW', 'timeout');
        $this->returnService->method('requestReturn')->willReturn($outcome);
        $this->notifier->expects($this->once())->method('notify')->with($outcome);

        $this->controller->execute();
    }
}
