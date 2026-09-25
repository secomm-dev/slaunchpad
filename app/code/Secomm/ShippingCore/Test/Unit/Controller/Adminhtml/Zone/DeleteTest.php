<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Controller\Adminhtml\Zone;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRepositoryInterface;
use Secomm\ShippingCore\Controller\Adminhtml\Zone\Delete;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierZoneIndex;
use Secomm\ShippingCore\Model\CarrierCoverage\ZoneReferenceGuard;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\ShippingCore\Model\Zone;
use Secomm\ShippingCore\Model\ZoneFactory;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — delete controller: POST-only, repository delegation,
 * clean redirect + message mapping. TASK-G3K9V2 (TL conditional approval, decision 2): a
 * zone referenced by a registered carrier is BLOCKED from deletion with the carriers named.
 */
class DeleteTest extends TestCase
{
    private Http $request;

    private Redirect $redirect;

    private ManagerInterface $messages;

    private CanonicalZoneRepositoryInterface $repository;

    private Zone&\PHPUnit\Framework\MockObject\MockObject $zoneModel;

    private CarrierZoneIndex&\PHPUnit\Framework\MockObject\MockObject $zoneIndex;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->request->method('isPost')->willReturn(true);
        $this->redirect = $this->createMock(Redirect::class);
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->repository = $this->createMock(CanonicalZoneRepositoryInterface::class);
        $this->zoneModel = $this->createMock(Zone::class);
        $this->zoneIndex = $this->createMock(CarrierZoneIndex::class);
    }

    private function build(): Delete
    {
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getResultRedirectFactory')->willReturn($this->redirectFactory());
        $context->method('getMessageManager')->willReturn($this->messages);
        $zoneFactory = $this->createMock(ZoneFactory::class);
        $zoneFactory->method('create')->willReturn($this->zoneModel);

        return new Delete(
            $context,
            $this->repository,
            $zoneFactory,
            $this->createMock(ZoneResource::class),
            new ZoneReferenceGuard($this->zoneIndex)
        );
    }

    private function redirectFactory(): RedirectFactory
    {
        $factory = $this->createMock(RedirectFactory::class);
        $factory->method('create')->willReturn($this->redirect);

        return $factory;
    }

    public function testDeletesAndRedirectsOnPost(): void
    {
        $this->request->method('getParam')->with('zone_id')->willReturn('3');
        $this->zoneModel->method('getId')->willReturn(3);
        $this->zoneModel->method('getCode')->willReturn('HN_INNER');
        $this->zoneIndex->method('findReferences')->willReturnCallback(
            static fn (string $code): array => []
        );

        $this->repository->expects($this->once())->method('deleteById')->with(3);
        $this->messages->expects($this->once())->method('addSuccessMessage');
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index');

        $this->build()->execute();
    }

    /**
     * TL decision 2 — referenced zone: deletion refused, repository untouched, carriers named.
     */
    public function testReferencedZoneDeletionIsBlocked(): void
    {
        $this->request->method('getParam')->with('zone_id')->willReturn('3');
        $this->zoneModel->method('getId')->willReturn(3);
        $this->zoneModel->method('getCode')->willReturn('HCM_INNER');
        $this->zoneIndex->method('findReferences')->willReturnCallback(
            static fn (string $code): array => $code === 'HCM_INNER' ? [
                ['carrier' => 'secomm_ghn', 'carrier_label' => 'GHN (Giao Hàng Nhanh)', 'scope' => 'default', 'scope_id' => 0, 'scope_label' => 'Default'],
            ] : []
        );

        $this->repository->expects($this->never())->method('deleteById');
        $this->messages->expects($this->never())->method('addSuccessMessage');
        $this->messages->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(static function ($message): bool {
                return str_contains((string) $message, 'cannot be deleted')
                    && str_contains((string) $message, 'HCM_INNER')
                    && str_contains((string) $message, 'GHN (Giao Hàng Nhanh)');
            }));

        $this->build()->execute();
    }

    public function testGetRequestIsRejected(): void
    {
        $this->request->method('isPost')->willReturn(false);
        $this->repository->expects($this->never())->method('deleteById');

        $this->build()->execute();
    }

    public function testUnknownZoneSurfacesError(): void
    {
        $this->request->method('getParam')->with('zone_id')->willReturn('99');
        $this->zoneModel->method('getId')->willReturn(null);

        $this->repository->expects($this->never())->method('deleteById');
        $this->messages->expects($this->once())->method('addErrorMessage');

        $this->build()->execute();
    }
}
