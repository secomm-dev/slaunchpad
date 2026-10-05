<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalData;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalPersister;
use Secomm\ShippingCore\Model\Physical\StoreWeightConverter;
use Secomm\ShippingCore\Model\Shipment\FulfillmentMetadataPersister;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;
use Secomm\ShippingCore\Model\Shipment\OfflineEligibilitySession;
use Secomm\ShippingCore\Observer\ShipmentOfflineFulfillmentObserver;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — the commit_after recorder: metadata marker + package
 * facts (RECORDED, never validated) + one structured log line. Guards: no intent = no-op;
 * the in-flight static guard absorbs the persisters' re-save re-fire.
 */
class ShipmentOfflineFulfillmentObserverTest extends TestCase
{
    private FulfillmentModeResolver&MockObject $resolver;

    private FulfillmentMetadataPersister&MockObject $metadataPersister;

    private ShipmentPhysicalPersister&MockObject $physicalPersister;

    private OfflineEligibilitySession&MockObject $eligibilitySession;

    private DateTime&MockObject $dateTime;

    private LoggerInterface&MockObject $logger;

    private HttpRequest&MockObject $request;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(FulfillmentModeResolver::class);
        $this->metadataPersister = $this->createMock(FulfillmentMetadataPersister::class);
        $this->physicalPersister = $this->createMock(ShipmentPhysicalPersister::class);
        $this->eligibilitySession = $this->createMock(OfflineEligibilitySession::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->dateTime->method('gmtDate')->willReturn('2026-09-30 10:00:00');
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->request = $this->createMock(HttpRequest::class);
    }

    private function observer(?StoreWeightConverter $weightConverter = null): ShipmentOfflineFulfillmentObserver
    {
        return new ShipmentOfflineFulfillmentObserver(
            $this->resolver,
            $this->metadataPersister,
            $this->physicalPersister,
            $weightConverter ?? new StoreWeightConverter($this->scopeConfig('kgs')),
            $this->eligibilitySession,
            $this->request,
            $this->dateTime,
            $this->logger
        );
    }

    public function testNoIntentIsNoOp(): void
    {
        $this->resolver->method('isOfflineIntent')->willReturn(false);
        $this->metadataPersister->expects($this->never())->method('persist');
        $this->physicalPersister->expects($this->never())->method('persist');

        $this->observer()->execute(new Observer(['shipment' => $this->shipment()]));
    }

    public function testOfflineIntentPersistsMetadataPackagesAndLogsTheStructuredLine(): void
    {
        $this->givenOfflineIntent();
        $this->givenPosted([
            'offline_reason_code' => 'INVALID_PARCEL',
            'offline_reason_message' => 'package #1 length is 300 cm — above the 200 cm length limit.',
            'offline_note' => 'Booked manually with a local trucking company.',
            'physical_packages' => [['weight' => 2.5, 'length' => 60, 'width' => 50, 'height' => 40]],
        ]);
        $shipment = $this->shipment();
        $this->metadataPersister->expects($this->once())->method('persist')->with(
            $shipment,
            $this->callback(function (array $metadata): bool {
                return ($metadata[FulfillmentMetadataPersister::MODE] ?? '') === 'OFFLINE'
                    && ($metadata[FulfillmentMetadataPersister::INTENDED_CARRIER] ?? '') === 'secomm_ghn'
                    && ($metadata[FulfillmentMetadataPersister::REASON_CODE] ?? '') === 'INVALID_PARCEL'
                    && str_contains(
                        (string) ($metadata[FulfillmentMetadataPersister::REASON_MESSAGE] ?? ''),
                        'above the 200 cm length limit'
                    )
                    && ($metadata[FulfillmentMetadataPersister::NOTE] ?? '') === 'Booked manually with a local trucking company.'
                    && ($metadata[FulfillmentMetadataPersister::CREATED_AT] ?? '') === '2026-09-30 10:00:00';
            })
        );
        $this->physicalPersister->expects($this->once())->method('persist')->with(
            $shipment,
            $this->callback(function (ShipmentPhysicalData $physical): bool {
                $packages = $physical->getPackages();

                return $physical->getTotalWeightG() === 2500
                    && count($packages) === 1
                    && [$packages[0]->getWeightG(), $packages[0]->getLengthCm(), $packages[0]->getWidthCm(), $packages[0]->getHeightCm()] === [2500, 60, 50, 40];
            })
        );
        $this->logger->expects($this->once())->method('info')->with(
            'Offline shipment recorded.',
            $this->callback(fn (array $context): bool => $context['order_id'] === 21
                && $context['shipment_id'] === 42
                && $context['fulfillment_mode'] === 'OFFLINE'
                && $context['intended_carrier'] === 'secomm_ghn'
                && $context['reason_code'] === 'INVALID_PARCEL')
        );

        $this->observer()->execute(new Observer(['shipment' => $shipment]));
    }

    public function testMissingPostedReasonFallsBackToTheStash(): void
    {
        $this->givenOfflineIntent();
        $this->givenPosted([]);
        $this->eligibilitySession->expects($this->once())->method('pull')->with(21)->willReturn(
            ['reason_code' => 'INVALID_CONFIGURATION', 'message' => 'the stash message']
        );
        $this->metadataPersister->expects($this->once())->method('persist')->with(
            $this->anything(),
            $this->callback(fn (array $metadata): bool => ($metadata[FulfillmentMetadataPersister::REASON_CODE] ?? '') === 'INVALID_CONFIGURATION')
        );

        $this->observer()->execute(new Observer(['shipment' => $this->shipment()]));
    }

    public function testConverterFailureIsContainedAndMetadataIsStillWritten(): void
    {
        $this->givenOfflineIntent();
        $this->givenPosted([
            'physical_packages' => [['weight' => 2.5, 'length' => 60, 'width' => 50, 'height' => 40]],
        ]);
        $this->metadataPersister->expects($this->once())->method('persist');
        $this->physicalPersister->expects($this->never())->method('persist');
        $this->logger->expects($this->once())->method('warning')->with(
            'Offline shipment: package facts not recorded.',
            $this->callback(fn (array $context): bool => str_contains((string) $context['reason'], 'kgs'))
        );

        $brokenConverter = new StoreWeightConverter($this->scopeConfig('stone'));
        $this->observer($brokenConverter)->execute(new Observer(['shipment' => $this->shipment()]));
    }

    public function testPersistReFireIsGuarded(): void
    {
        $this->givenOfflineIntent();
        $this->givenPosted([]);
        $shipment = $this->shipment();

        // The metadata persist re-saves the shipment → the event re-fires synchronously; the
        // inner invocation must be a no-op (no second metadata persist, no second log line).
        $observer = $this->observer();
        $this->metadataPersister->expects($this->once())->method('persist')->willReturnCallback(
            function () use ($observer, $shipment): void {
                $observer->execute(new Observer(['shipment' => $shipment]));
            }
        );
        $this->logger->expects($this->once())->method('info');

        $observer->execute(new Observer(['shipment' => $shipment]));
    }

    // ---------- helpers ----------

    private function givenOfflineIntent(): void
    {
        $this->resolver->method('isOfflineIntent')->willReturn(true);
        $this->resolver->method('resolveCarrierCode')->with('secomm_ghn_secomm_ghn')->willReturn('secomm_ghn');
    }

    private function givenPosted(array $value): void
    {
        $this->request->method('getParam')->with('shipment')->willReturn($value);
    }

    private function scopeConfig(string $weightUnit): ScopeConfigInterface&MockObject
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($weightUnit);

        return $scopeConfig;
    }

    private function shipment(): Shipment&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getShippingMethod')->willReturn('secomm_ghn_secomm_ghn');

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getEntityId')->willReturn(42);
        $shipment->method('getOrderId')->willReturn(21);
        $shipment->method('getStoreId')->willReturn(1);
        $shipment->method('getOrder')->willReturn($order);

        return $shipment;
    }
}
