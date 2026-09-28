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
use Secomm\ShippingCore\Controller\Adminhtml\Zone\MassDelete;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierZoneIndex;
use Secomm\ShippingCore\Model\CarrierCoverage\ZoneReferenceGuard;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\ShippingCore\Model\Zone;
use Secomm\ShippingCore\Model\ZoneFactory;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 (TL conditional approval, decision 2) — mass delete: the WHOLE
 * batch is blocked (no partial delete) when any selected zone is referenced by a registered
 * carrier; unreferenced batches delete normally.
 */
class MassDeleteTest extends TestCase
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

    private function build(): MassDelete
    {
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($this->messages);

        return new MassDelete(
            $context,
            $this->repository,
            $this->zoneFactory,
            $this->createMock(ZoneResource::class),
            new ZoneReferenceGuard($this->zoneIndex)
        );
    }

    private function willLoadZone(int $id, ?string $code): void
    {
        $zone = $this->createMock(Zone::class);
        $zone->method('getId')->willReturn($code !== null ? $id : null);
        $zone->method('getCode')->willReturn($code ?? '');
        $this->zoneFactory->method('create')->willReturn($zone);
    }

    public function testUnreferencedBatchDeletes(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key): array => $key === 'selected' ? ['1', '2'] : []
        );
        $this->willLoadZone(1, 'HN_INNER');
        $this->zoneIndex->method('findReferences')->willReturnCallback(
            static fn (string $code): array => []
        );

        $this->repository->expects($this->exactly(2))->method('deleteById');
        $this->messages->expects($this->once())->method('addSuccessMessage')
            ->with($this->callback(static function ($message): bool {
                return str_contains((string) $message, '2 zone(s)');
            }));

        $this->build()->execute();
    }

    /**
     * E — one zone referenced only at WEBSITE scope blocks the whole batch.
     */
    public function testWebsiteScopedReferencedZoneBlocksWholeBatch(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key): array => $key === 'selected' ? ['1', '2'] : []
        );
        // Two different zone models for two ids — the factory returns a fresh mock per create().
        $zone1 = $this->createMock(Zone::class);
        $zone1->method('getId')->willReturn(1);
        $zone1->method('getCode')->willReturn('HCM_INNER');
        $zone2 = $this->createMock(Zone::class);
        $zone2->method('getId')->willReturn(2);
        $zone2->method('getCode')->willReturn('HN_INNER');
        $this->zoneFactory->method('create')->willReturnOnConsecutiveCalls($zone1, $zone2);
        $this->zoneIndex->method('findReferences')->willReturnCallback(
            static fn (string $code): array => $code === 'HCM_INNER' ? [
                ['carrier' => 'secomm_ghn', 'carrier_label' => 'GHN (Giao Hàng Nhanh)', 'scope' => 'websites', 'scope_id' => 1, 'scope_label' => 'Website: Vietnam Store'],
            ] : []
        );

        $this->repository->expects($this->never())->method('deleteById');
        $this->messages->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(static function ($message): bool {
                return str_contains((string) $message, 'No zones were deleted')
                    && str_contains((string) $message, 'HCM_INNER (GHN (Giao Hàng Nhanh) (Website: Vietnam Store))');
            }));

        $this->build()->execute();
    }

    public function testEmptySelectionIsRejected(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key): array => $key === 'selected' ? [] : []
        );

        $this->repository->expects($this->never())->method('deleteById');
        $this->messages->expects($this->once())->method('addErrorMessage');

        $this->build()->execute();
    }
}
