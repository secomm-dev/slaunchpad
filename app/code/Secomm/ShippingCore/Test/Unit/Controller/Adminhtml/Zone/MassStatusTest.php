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
use Secomm\ShippingCore\Controller\Adminhtml\Zone\MassStatus;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierZoneIndex;
use Secomm\ShippingCore\Model\CarrierCoverage\ZoneReferenceGuard;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\ShippingCore\Model\Zone;
use Secomm\ShippingCore\Model\ZoneFactory;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 (TL decision 3) — mass status: disabling zones still referenced
 * by a registered carrier proceeds but surfaces an EXPLICIT impact warning naming the zones
 * + carriers; enabling never warns.
 */
class MassStatusTest extends TestCase
{
    private Http $request;

    private Redirect $redirect;

    private ManagerInterface $messages;

    private CanonicalZoneRepositoryInterface $repository;

    private ZoneFactory&\PHPUnit\Framework\MockObject\MockObject $zoneFactory;

    private CarrierZoneIndex&\PHPUnit\Framework\MockObject\MockObject $zoneIndex;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->request->method('isPost')->willReturn(true);
        $this->redirect = $this->createMock(Redirect::class);
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->repository = $this->createMock(CanonicalZoneRepositoryInterface::class);
        $this->zoneFactory = $this->createMock(ZoneFactory::class);
        $this->zoneIndex = $this->createMock(CarrierZoneIndex::class);
    }

    private function build(): MassStatus
    {
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($this->messages);

        return new MassStatus(
            $context,
            $this->repository,
            $this->zoneFactory,
            $this->createMock(ZoneResource::class),
            new ZoneReferenceGuard($this->zoneIndex)
        );
    }

    public function testDisableReferencedZoneWarnsWithImpact(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                'selected' => ['5'],
                'status' => '0',
                default => null,
            }
        );
        $zone = $this->createMock(Zone::class);
        $zone->method('getId')->willReturn(5);
        $zone->method('getCode')->willReturn('HCM_INNER');
        $this->zoneFactory->method('create')->willReturn($zone);
        $this->zoneIndex->method('findReferences')->willReturnCallback(
            static fn (string $code): array => $code === 'HCM_INNER' ? [
                ['carrier' => 'secomm_ghn', 'carrier_label' => 'GHN (Giao Hàng Nhanh)', 'scope' => 'stores', 'scope_id' => 2, 'scope_label' => 'Store View: English'],
            ] : []
        );

        $this->repository->expects($this->once())->method('setEnabled')->with(5, false);
        $this->messages->expects($this->once())->method('addSuccessMessage');
        $this->messages->expects($this->once())->method('addWarningMessage')
            ->with($this->callback(static function ($message): bool {
                return str_contains((string) $message, 'HCM_INNER (GHN (Giao Hàng Nhanh) (Store View: English))');
            }));

        $this->build()->execute();
    }

    public function testEnableNeverWarns(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                'selected' => ['5'],
                'status' => '1',
                default => null,
            }
        );

        $this->repository->expects($this->once())->method('setEnabled')->with(5, true);
        $this->messages->expects($this->once())->method('addSuccessMessage');
        $this->messages->expects($this->never())->method('addWarningMessage');

        $this->build()->execute();
    }

    public function testDisableUnreferencedZoneDoesNotWarn(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                'selected' => ['5'],
                'status' => '0',
                default => null,
            }
        );
        $zone = $this->createMock(Zone::class);
        $zone->method('getId')->willReturn(5);
        $zone->method('getCode')->willReturn('HN_INNER');
        $this->zoneFactory->method('create')->willReturn($zone);
        $this->zoneIndex->method('findReferences')->willReturnCallback(
            static fn (string $code): array => []
        );

        $this->repository->expects($this->once())->method('setEnabled')->with(5, false);
        $this->messages->expects($this->never())->method('addWarningMessage');

        $this->build()->execute();
    }

    public function testGarbledStatusParamIsRejected(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                'selected' => ['5'],
                'status' => 'maybe',
                default => null,
            }
        );

        $this->repository->expects($this->never())->method('setEnabled');
        $this->messages->expects($this->once())->method('addErrorMessage');

        $this->build()->execute();
    }
}
