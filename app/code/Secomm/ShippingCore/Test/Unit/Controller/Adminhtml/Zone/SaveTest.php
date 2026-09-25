<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Controller\Adminhtml\Zone;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRepositoryInterface;
use Secomm\ShippingCore\Controller\Adminhtml\Zone\Save;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierZoneIndex;
use Secomm\ShippingCore\Model\CarrierCoverage\ZoneReferenceGuard;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\ShippingCore\Model\Zone;
use Secomm\ShippingCore\Model\ZoneFactory;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — save controller: POST-only, validation failures reject
 * with the validator message + preserved input, unknown-zone update redirects cleanly.
 * TASK-G3K9V2 (TL conditional approval): an edit save WITHOUT the `exclude_ward_codes` key
 * PRESERVES the persisted list; a POST carrying the key still sets it explicitly; saving a
 * zone as disabled while carrier-referenced surfaces an impact warning.
 */
class SaveTest extends TestCase
{
    private Http $request;

    private Redirect $redirect;

    private RedirectFactory $redirectFactory;

    private ManagerInterface $messages;

    private DataPersistorInterface $dataPersistor;

    private CanonicalZoneRepositoryInterface $zoneRepository;

    private Zone&\PHPUnit\Framework\MockObject\MockObject $zoneModel;

    private CarrierZoneIndex&\PHPUnit\Framework\MockObject\MockObject $zoneIndex;

    private Save $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->redirect = $this->createMock(Redirect::class);
        $this->redirectFactory = $this->createMock(RedirectFactory::class);
        $this->redirectFactory->method('create')->willReturn($this->redirect);
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->dataPersistor = $this->createMock(DataPersistorInterface::class);
        $this->zoneRepository = $this->createMock(CanonicalZoneRepositoryInterface::class);
        $this->zoneModel = $this->createMock(Zone::class);
        $this->zoneIndex = $this->createMock(CarrierZoneIndex::class);

        $zoneFactory = $this->createMock(ZoneFactory::class);
        $zoneFactory->method('create')->willReturn($this->zoneModel);
        $zoneResource = $this->createMock(ZoneResource::class);
        $guard = new ZoneReferenceGuard($this->zoneIndex);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getResultRedirectFactory')->willReturn($this->redirectFactory);
        $context->method('getMessageManager')->willReturn($this->messages);

        $this->controller = new Save(
            $context,
            $this->zoneRepository,
            $this->dataPersistor,
            $zoneFactory,
            $zoneResource,
            $guard
        );
    }

    /**
     * TL decision 1 — the core regression guard: a UI edit save (no exclude key in POST)
     * keeps whatever excluded-ward list is persisted.
     */
    public function testEditSaveWithoutExcludeKeyPreservesPersistedList(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->request->method('getParam')->with('zone_id')->willReturn('7');
        $this->request->method('getPostValue')->willReturn([
            'code' => 'HCM_INNER',
            'label' => 'Nội thành',
            'enabled' => '1',
            'include_province_codes' => ['VN-79'],
            'include_ward_codes' => [],
        ]);
        $this->zoneModel->method('getId')->willReturn(7);
        $this->zoneModel->method('getExcludeWardCodes')->willReturn(['VNA25-OLD1', 'VNA25-OLD2']);

        $captured = null;
        $this->zoneRepository->expects($this->once())->method('save')
            ->with($this->callback(static function (CanonicalZone $zone) use (&$captured): bool {
                $captured = $zone;

                return true;
            }), 7);
        $this->messages->expects($this->once())->method('addSuccessMessage');
        $this->messages->expects($this->never())->method('addWarningMessage');

        $this->controller->execute();

        $this->assertNotNull($captured);
        $this->assertSame(['VNA25-OLD1', 'VNA25-OLD2'], $captured->getExcludeWardCodes());
    }

    /**
     * A POST that DOES carry the key sets the list explicitly — including an intentional clear.
     */
    public function testExplicitExcludeKeyStillWins(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->request->method('getParam')->with('zone_id')->willReturn('7');
        $this->request->method('getPostValue')->willReturn([
            'code' => 'HCM_INNER',
            'label' => 'Nội thành',
            'enabled' => '1',
            'exclude_ward_codes' => ['VNA25-NEW'],
        ]);
        $this->zoneModel->method('getId')->willReturn(7);
        $this->zoneModel->method('getExcludeWardCodes')->willReturn(['VNA25-OLD1']);
        $this->zoneIndex->method('findReferences')->willReturnCallback(
            static fn (string $code): array => []
        );

        $captured = null;
        $this->zoneRepository->expects($this->once())->method('save')
            ->with($this->callback(static function (CanonicalZone $zone) use (&$captured): bool {
                $captured = $zone;

                return true;
            }), 7);

        $this->controller->execute();

        $this->assertSame(['VNA25-NEW'], $captured->getExcludeWardCodes());
    }

    public function testNewZoneWithoutExcludeKeyStartsEmpty(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->request->method('getParam')->with('zone_id')->willReturn(null);
        $this->request->method('getPostValue')->willReturn([
            'code' => 'NEW_ZONE',
            'label' => 'New',
            'enabled' => '1',
            'include_province_codes' => ['VN-01'],
        ]);
        $this->zoneIndex->method('findReferences')->willReturnCallback(
            static fn (string $code): array => []
        );

        $captured = null;
        $this->zoneRepository->expects($this->once())->method('save')
            ->with($this->callback(static function (CanonicalZone $zone) use (&$captured): bool {
                $captured = $zone;

                return true;
            }), null);

        $this->controller->execute();

        $this->assertSame([], $captured->getExcludeWardCodes());
    }

    /**
     * TL decision 3 — disabled + referenced save still succeeds but WARNS with the carriers.
     */
    public function testDisabledReferencedZoneWarnsOnSave(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->request->method('getParam')->with('zone_id')->willReturn('7');
        $this->request->method('getPostValue')->willReturn([
            'code' => 'HCM_INNER',
            'label' => 'Nội thành',
            'enabled' => '0',
            'include_province_codes' => ['VN-79'],
        ]);
        $this->zoneModel->method('getId')->willReturn(7);
        $this->zoneModel->method('getExcludeWardCodes')->willReturn([]);
        $this->zoneIndex->method('findReferences')->willReturnCallback(
            static fn (string $code): array => $code === 'HCM_INNER' ? [
                ['carrier' => 'secomm_ghn', 'carrier_label' => 'GHN (Giao Hàng Nhanh)', 'scope' => 'default', 'scope_id' => 0, 'scope_label' => 'Default'],
            ] : []
        );

        $this->zoneRepository->expects($this->once())->method('save');
        $this->messages->expects($this->once())->method('addSuccessMessage');
        $this->messages->expects($this->once())->method('addWarningMessage')
            ->with($this->callback(static function ($message): bool {
                return str_contains((string) $message, 'HCM_INNER')
                    && str_contains((string) $message, 'GHN (Giao Hàng Nhanh)');
            }));

        $this->controller->execute();
    }

    public function testEnabledSaveDoesNotWarn(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->request->method('getParam')->with('zone_id')->willReturn(null);
        $this->request->method('getPostValue')->willReturn([
            'code' => 'HCM_INNER',
            'label' => 'Nội thành',
            'enabled' => '1',
            'include_province_codes' => ['VN-79'],
        ]);
        $this->zoneIndex->method('findReferences')->willReturnCallback(
            static fn (string $code): array => $code === 'HCM_INNER' ? [
                ['carrier' => 'secomm_ghn', 'carrier_label' => 'GHN (Giao Hàng Nhanh)', 'scope' => 'default', 'scope_id' => 0, 'scope_label' => 'Default'],
            ] : []
        );

        $this->zoneRepository->expects($this->once())->method('save');
        $this->messages->expects($this->never())->method('addWarningMessage');

        $this->controller->execute();
    }

    public function testRedirectsToIndexOnSuccess(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->request->method('getParam')->with('zone_id')->willReturn(null);
        $this->request->method('getPostValue')->willReturn([
            'code' => 'HCM_INNER',
            'label' => 'Nội thành',
            'enabled' => '1',
            'include_province_codes' => ['VN-79'],
            'include_ward_codes' => ['VNA25-AAA'],
            'exclude_ward_codes' => [],
        ]);
        $this->zoneIndex->method('findReferences')->willReturnCallback(
            static fn (string $code): array => []
        );
        $this->zoneRepository->expects($this->once())->method('save')
            ->with($this->anything(), null)
            ->willReturn(5);
        $this->messages->expects($this->once())->method('addSuccessMessage');
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index');

        $this->controller->execute();
    }

    public function testGetRequestIsRejected(): void
    {
        $this->request->method('isPost')->willReturn(false);
        $this->zoneRepository->expects($this->never())->method('save');
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index');

        $this->controller->execute();
    }

    public function testValidationFailureRejectsAndPersistsInput(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->request->method('getParam')->with('zone_id')->willReturn(null);
        $this->request->method('getPostValue')->willReturn([
            'code' => 'BAD ZONE',
            'label' => 'X',
        ]);
        $this->zoneRepository->method('save')->willThrowException(
            new LocalizedException(new Phrase('Zone code "%1" may only contain A-Z, 0-9, underscore and dash.', ['BAD ZONE']))
        );
        $this->messages->expects($this->once())->method('addErrorMessage')
            ->with($this->stringContains('may only contain A-Z'));
        $this->dataPersistor->expects($this->once())->method('set')
            ->with('secomm_shippingcore_zone_form', $this->callback(
                static function (array $data): bool {
                    return $data['code'] === 'BAD ZONE' && $data['zone_id'] === null;
                }
            ));
        $this->redirect->expects($this->once())->method('setPath')
            ->with('*/*/edit', []);

        $this->controller->execute();
    }

    public function testUnknownZoneIdSurfacesCleanError(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->request->method('getParam')->with('zone_id')->willReturn('99');
        $this->request->method('getPostValue')->willReturn(['code' => 'X', 'label' => 'Y']);
        $this->zoneModel->method('getId')->willReturn(null); // id no longer resolves
        $repository = $this->createMock(CanonicalZoneRepositoryInterface::class);
        $repository->method('save')
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(new Phrase('missing')));
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getResultRedirectFactory')->willReturn($this->redirectFactory);
        $context->method('getMessageManager')->willReturn($this->messages);
        $zoneFactory = $this->createMock(ZoneFactory::class);
        $zoneFactory->method('create')->willReturn($this->zoneModel);
        $controller = new Save(
            $context,
            $repository,
            $this->dataPersistor,
            $zoneFactory,
            $this->createMock(ZoneResource::class),
            new ZoneReferenceGuard($this->zoneIndex)
        );
        $this->messages->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(static function ($message): bool {
                return str_contains((string) $message, 'no longer exists');
            }));

        $controller->execute();
    }
}
